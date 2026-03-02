<?php

namespace App\Services\Payment;

use App\Jobs\Payment\SendGepgBillSubmissionJob;
use App\Models\Payment\Bill;
use App\Models\Payment\FeeRule;
use App\Models\Payment\GepgBillConfig;
use App\Models\Payment\GepgRequest;
use App\Models\Payment\Invoice;
use App\Models\Payment\InvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceBillingService
{
    public function __construct(private readonly GepgCryptoService $crypto)
    {
    }

    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $appCode = $this->resolveAppCode($data);
            $profileCode = $this->resolveProfileCode($data);
            $triggerReference = $this->generateTriggerReference($appCode);

            $feeRule = FeeRule::query()
                ->with('gfs')
                ->where('app_code', $appCode)
                ->where('code', $data['fee_rule_code'])
                ->where('active', true)
                ->firstOrFail();

            if ($feeRule->amount === null) {
                abort(422, 'Fee rule amount is not configured yet.');
            }

            if (! $feeRule->gfs_id) {
                abort(422, 'Fee rule GFS mapping is not configured yet.');
            }

            $invoiceMeta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
            $invoiceMeta['app_code'] = $appCode;
            $invoiceMeta['profile_code'] = $profileCode;

            $invoice = Invoice::create([
                'uuid' => (string) Str::uuid(),
                'invoice_number' => 'TMP-' . Str::uuid(),
                'trigger_code' => $feeRule->code,
                'trigger_reference' => $triggerReference,
                'module' => $feeRule->module,
                'sub_module' => $feeRule->sub_module,
                'payer_name' => $data['payer_name'],
                'payer_phone' => $data['payer_phone'] ?? null,
                'payer_email' => $data['payer_email'] ?? null,
                'amount_total' => $feeRule->amount,
                'currency' => $feeRule->currency,
                'status' => 'DRAFT',
                'meta' => $invoiceMeta,
                'created_by' => (string) (auth()->id() ?? 'system'),
                'updated_by' => (string) (auth()->id() ?? 'system'),
            ]);

            $invoice->invoice_number = sprintf('INV-%s-%06d', now()->format('Ymd'), $invoice->id);
            $invoice->save();

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'fee_rule_id' => $feeRule->id,
                'item_code' => $feeRule->code,
                'description' => $feeRule->payment_type,
                'quantity' => 1,
                'unit_amount' => $feeRule->amount,
                'line_total' => $feeRule->amount,
                'meta' => [
                    'trigger_action' => $feeRule->trigger_action,
                    'trigger_condition' => $feeRule->trigger_condition,
                    'gfs_id' => $feeRule->gfs_id,
                    'gfs_code' => $feeRule->gfs?->gepg_gfs_code ?: $feeRule->gfs?->code,
                ],
                'created_by' => (string) (auth()->id() ?? 'system'),
                'updated_by' => (string) (auth()->id() ?? 'system'),
            ]);

            return $invoice->fresh('items');
        });
    }

    public function issueBill(Invoice $invoice, ?string $expiresAt = null): Bill
    {
        return DB::transaction(function () use ($invoice, $expiresAt) {
            $existing = Bill::query()->where('invoice_id', $invoice->id)->first();
            if ($existing && ! in_array($existing->status, ['FAILED', 'EXPIRED', 'CANCELLED'], true)) {
                abort(409, 'Bill already exists for this invoice. Duplicate bill generation is blocked.');
            }

            if ($invoice->status === 'PAID') {
                abort(422, 'Invoice already paid.');
            }

            $billReference = sprintf('TTPB-BILL-%s-%06d', now()->format('Ymd'), $invoice->id);
            $reqId = 'SP' . now()->format('YmdHisv') . $invoice->id;

            $bodyXml = $this->buildBillSubmissionXml($invoice, $billReference, $reqId, $expiresAt);
            $signature = $this->crypto->sign($bodyXml);
            $payload = '<Gepg>' . $bodyXml . '<signature>' . $signature . '</signature></Gepg>';

            $bill = Bill::updateOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'bill_reference' => $billReference,
                    'req_id' => $reqId,
                    'grp_bill_id' => $billReference,
                    'amount' => $invoice->amount_total,
                    'currency' => $invoice->currency,
                    'status' => 'QUEUED',
                    'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : $invoice->expires_at,
                    'submit_payload' => $payload,
                    'submit_response' => null,
                    'created_by' => (string) (auth()->id() ?? 'system'),
                    'updated_by' => (string) (auth()->id() ?? 'system'),
                ]
            );

            $gepgRequest = GepgRequest::create([
                'invoice_id' => $invoice->id,
                'bill_id' => $bill->id,
                'request_type' => 'BILL_SUBMISSION',
                'request_id' => $reqId,
                'payload' => $payload,
                'signature' => $signature,
                'status' => 'QUEUED',
                'created_by' => (string) (auth()->id() ?? 'system'),
                'updated_by' => (string) (auth()->id() ?? 'system'),
            ]);

            DB::afterCommit(function () use ($gepgRequest): void {
                SendGepgBillSubmissionJob::dispatch($gepgRequest->id)
                    ->onQueue((string) config('gepg.queues.outbound', 'gepg-outbound'));
            });

            $invoice->status = 'ISSUED';
            $invoice->expires_at = $expiresAt ? Carbon::parse($expiresAt) : $invoice->expires_at;
            $invoice->updated_by = (string) (auth()->id() ?? 'system');
            $invoice->save();

            return $bill->fresh('invoice');
        });
    }

    private function buildBillSubmissionXml(Invoice $invoice, string $billReference, string $reqId, ?string $expiresAt): string
    {
        $meta = is_array($invoice->meta) ? $invoice->meta : [];
        $appCode = (string) ($meta['app_code'] ?? config('gepg.default_app_code', 'DEFAULT'));
        $profileCode = (string) ($meta['profile_code'] ?? config('gepg.default_profile_code', 'DEFAULT'));

        $billConfig = $this->resolveBillConfig($appCode, $profileCode);
        $ttlDays = (int) ($billConfig['bill_ttl_days'] ?? 30);
        $expires = $expiresAt ? now()->parse($expiresAt) : now()->addDays($ttlDays);

        $feeRule = FeeRule::query()
            ->with('gfs')
            ->where('app_code', $appCode)
            ->where('code', $invoice->trigger_code)
            ->first();

        if (! $feeRule || ! $feeRule->gfs) {
            abort(422, 'Fee rule GFS mapping is not configured yet.');
        }

        $gfsCode = (string) ($feeRule->gfs->gepg_gfs_code ?: $feeRule->gfs->code);
        if ($gfsCode === '') {
            abort(422, 'Fee rule GePG GFS code is not configured yet.');
        }

        $custTin = (string) ($meta['cust_tin'] ?? $invoice->trigger_reference ?? '');
        $custId = (string) ($meta['cust_id'] ?? $invoice->trigger_reference ?? '');
        $custAccnt = (string) ($meta['cust_accnt'] ?? $custId ?: ($invoice->payer_phone ?? ''));
        $custIdTyp = (string) ($meta['cust_id_typ'] ?? $billConfig['cust_id_typ']);
        $billDesc = (string) ($meta['bill_desc'] ?? ($invoice->sub_module ?? $invoice->module ?? ''));
        $billItemRef = (string) ($meta['bill_item_ref'] ?? $custId ?: ($invoice->trigger_reference ?? $billReference));
        $minPayAmt = $this->toMoney($meta['min_pay_amt'] ?? $billConfig['min_pay_amt']);
        $payLimAmt = $this->toMoney($billConfig['pay_lim_amt']);
        $amount = $this->toMoney($invoice->amount_total);
        $collSp = (string) ($billConfig['coll_sp'] ?: $billConfig['sp_code']);
        $subSpCode = (string) ($billConfig['sub_sp_code'] ?? '');
        $useItemRefOnPay = (string) ($billConfig['use_item_ref_on_pay'] ?? 'N');
        $collPsp = (string) ($billConfig['coll_psp'] ?? '');
        $billGenBy = (string) ($billConfig['bill_gen_by'] ?? 'System');
        $billApprBy = (string) ($billConfig['bill_appr_by'] ?? 'System');
        $collCentCode = (string) ($billConfig['coll_cent_code'] ?? '');
        $currency = (string) ($invoice->currency ?: 'TZS');

        return '<billSubReq><BillHdr>'
            . '<ReqId>' . e($reqId) . '</ReqId>'
            . '<SpGrpCode>' . e((string) $billConfig['sp_grp_code']) . '</SpGrpCode>'
            . '<SysCode>' . e((string) $billConfig['sys_code']) . '</SysCode>'
            . '<BillTyp>' . e((string) $billConfig['bill_typ']) . '</BillTyp>'
            . '<PayTyp>' . e((string) $billConfig['pay_typ']) . '</PayTyp>'
            . '<GrpBillId>' . e($billReference) . '</GrpBillId>'
            . '</BillHdr><BillDtls><BillDtl>'
            . '<BillId>' . e($billReference) . '</BillId>'
            . '<SpCode>' . e((string) $billConfig['sp_code']) . '</SpCode>'
            . '<CollCentCode>' . e($collCentCode) . '</CollCentCode>'
            . '<BillDesc>' . e($billDesc) . '</BillDesc>'
            . '<CustTin>' . e($custTin) . '</CustTin>'
            . '<CustId>' . e($custId) . '</CustId>'
            . '<CustIdTyp>' . e($custIdTyp) . '</CustIdTyp>'
            . '<CustAccnt>' . e($custAccnt) . '</CustAccnt>'
            . '<CustName>' . e($invoice->payer_name) . '</CustName>'
            . '<CustCellNum>' . e((string) ($invoice->payer_phone ?? '')) . '</CustCellNum>'
            . '<CustEmail>' . e((string) ($invoice->payer_email ?? '')) . '</CustEmail>'
            . '<BillGenDt>' . now()->format('Y-m-d\\TH:i:s') . '</BillGenDt>'
            . '<BillExprDt>' . $expires->format('Y-m-d\\TH:i:s') . '</BillExprDt>'
            . '<BillGenBy>' . e($billGenBy) . '</BillGenBy>'
            . '<BillApprBy>' . e($billApprBy) . '</BillApprBy>'
            . '<BillAmt>' . $amount . '</BillAmt>'
            . '<BillEqvAmt>' . $amount . '</BillEqvAmt>'
            . '<MinPayAmt>' . $minPayAmt . '</MinPayAmt>'
            . '<Ccy>' . e($currency) . '</Ccy>'
            . '<ExchRate>1.00</ExchRate>'
            . '<BillPayOpt>' . e((string) $billConfig['bill_pay_opt']) . '</BillPayOpt>'
            . '<PayPlan>' . e((string) $billConfig['pay_plan']) . '</PayPlan>'
            . '<PayLimTyp>' . e((string) $billConfig['pay_lim_typ']) . '</PayLimTyp>'
            . '<PayLimAmt>' . $payLimAmt . '</PayLimAmt>'
            . '<CollPsp>' . e($collPsp) . '</CollPsp>'
            . '<BillItems><BillItem>'
            . '<RefBillId>' . e($billReference) . '</RefBillId>'
            . '<SubSpCode>' . e($subSpCode) . '</SubSpCode>'
            . '<GfsCode>' . e($gfsCode) . '</GfsCode>'
            . '<BillItemRef>' . e($billItemRef) . '</BillItemRef>'
            . '<UseItemRefOnPay>' . e($useItemRefOnPay) . '</UseItemRefOnPay>'
            . '<BillItemAmt>' . $amount . '</BillItemAmt>'
            . '<BillItemEqvAmt>' . $amount . '</BillItemEqvAmt>'
            . '<CollSp>' . e($collSp) . '</CollSp>'
            . '</BillItem></BillItems>'
            . '</BillDtl></BillDtls></billSubReq>';
    }

    private function resolveBillConfig(string $appCode, string $profileCode): array
    {
        $row = GepgBillConfig::query()
            ->where('app_code', $appCode)
            ->where('profile_code', $profileCode)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            $row = GepgBillConfig::query()
                ->where('app_code', $appCode)
                ->where('profile_code', (string) config('gepg.default_profile_code', 'DEFAULT'))
                ->where('active', true)
                ->orderByDesc('id')
                ->first();
        }

        if (! $row) {
            $row = GepgBillConfig::query()
                ->where('app_code', (string) config('gepg.default_app_code', 'DEFAULT'))
                ->where('profile_code', (string) config('gepg.default_profile_code', 'DEFAULT'))
                ->where('active', true)
                ->orderByDesc('id')
                ->first();
        }

        if (! $row) {
            $row = GepgBillConfig::query()
            ->where('active', true)
            ->orderByDesc('id')
            ->first();
        }

        if ($row) {
            return [
                'sp_grp_code' => $row->sp_grp_code,
                'sys_code' => $row->sys_code,
                'sp_code' => $row->sp_code,
                'bill_typ' => $row->bill_typ,
                'pay_typ' => $row->pay_typ,
                'coll_cent_code' => $row->coll_cent_code,
                'cust_id_typ' => $row->cust_id_typ,
                'bill_gen_by' => $row->bill_gen_by,
                'bill_appr_by' => $row->bill_appr_by,
                'bill_pay_opt' => $row->bill_pay_opt,
                'pay_plan' => $row->pay_plan,
                'pay_lim_typ' => $row->pay_lim_typ,
                'pay_lim_amt' => $row->pay_lim_amt,
                'min_pay_amt' => $row->min_pay_amt,
                'sub_sp_code' => $row->sub_sp_code,
                'use_item_ref_on_pay' => $row->use_item_ref_on_pay,
                'coll_sp' => $row->coll_sp,
                'coll_psp' => $row->coll_psp,
                'bill_ttl_days' => $row->bill_ttl_days,
            ];
        }

        return [
            'sp_grp_code' => (string) config('gepg.sp_code', 'SP00000'),
            'sys_code' => (string) config('gepg.system_code', 'TTPB001'),
            'sp_code' => (string) config('gepg.sp_code', 'SP00000'),
            'bill_typ' => 1,
            'pay_typ' => 2,
            'coll_cent_code' => '',
            'cust_id_typ' => 1,
            'bill_gen_by' => 'System',
            'bill_appr_by' => 'System',
            'bill_pay_opt' => 3,
            'pay_plan' => 1,
            'pay_lim_typ' => 1,
            'pay_lim_amt' => 0,
            'min_pay_amt' => 100,
            'sub_sp_code' => '',
            'use_item_ref_on_pay' => 'N',
            'coll_sp' => (string) config('gepg.sp_code', 'SP00000'),
            'coll_psp' => '',
            'bill_ttl_days' => 30,
        ];
    }

    private function toMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function resolveAppCode(array $data): string
    {
        return (string) ($data['app_code'] ?? config('gepg.default_app_code', 'DEFAULT'));
    }

    private function resolveProfileCode(array $data): string
    {
        return (string) ($data['profile_code'] ?? config('gepg.default_profile_code', 'DEFAULT'));
    }

    private function generateTriggerReference(string $appCode): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($appCode)) ?: 'APP';
        $timePart = now()->format('YmdHisv');
        $randPart = strtoupper(Str::random(6));

        return sprintf('%s-%s-%s', $prefix, $timePart, $randPart);
    }
}
