<?php

namespace App\Services\Payment;

use App\Jobs\Payment\SendGepgBillSubmissionJob;
use App\Models\Payment\Bill;
use App\Models\Payment\FeeRule;
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
            $feeRule = FeeRule::query()
                ->where('code', $data['fee_rule_code'])
                ->where('active', true)
                ->firstOrFail();

            if ($feeRule->amount === null) {
                abort(422, 'Fee rule amount is not configured yet.');
            }

            if (! $feeRule->gfs_id) {
                abort(422, 'Fee rule GFS mapping is not configured yet.');
            }

            $invoice = Invoice::create([
                'uuid' => (string) Str::uuid(),
                'invoice_number' => 'TMP-' . Str::uuid(),
                'trigger_code' => $feeRule->code,
                'trigger_reference' => $data['trigger_reference'] ?? null,
                'module' => $feeRule->module,
                'sub_module' => $feeRule->sub_module,
                'payer_name' => $data['payer_name'],
                'payer_phone' => $data['payer_phone'] ?? null,
                'payer_email' => $data['payer_email'] ?? null,
                'amount_total' => $feeRule->amount,
                'currency' => $feeRule->currency,
                'status' => 'DRAFT',
                'meta' => $data['meta'] ?? null,
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
        $expires = $expiresAt ? now()->parse($expiresAt) : now()->addDays(30);

        return '<billSubReq><BillHdr>'
            . '<ReqId>' . e($reqId) . '</ReqId>'
            . '<SpGrpCode>' . e((string) config('gepg.sp_code', 'SP00000')) . '</SpGrpCode>'
            . '<SysCode>' . e((string) config('gepg.system_code', 'TTPB001')) . '</SysCode>'
            . '<BillTyp>1</BillTyp><PayTyp>1</PayTyp>'
            . '<GrpBillId>' . e($billReference) . '</GrpBillId>'
            . '</BillHdr><BillDtls><BillDtl>'
            . '<BillId>' . e($billReference) . '</BillId>'
            . '<SpCode>' . e((string) config('gepg.sp_code', 'SP00000')) . '</SpCode>'
            . '<BillDesc>' . e($invoice->sub_module ?? $invoice->module) . '</BillDesc>'
            . '<CustName>' . e($invoice->payer_name) . '</CustName>'
            . '<CustCellNum>' . e((string) ($invoice->payer_phone ?? '')) . '</CustCellNum>'
            . '<CustEmail>' . e((string) ($invoice->payer_email ?? '')) . '</CustEmail>'
            . '<BillGenDt>' . now()->format('Y-m-d\\TH:i:s') . '</BillGenDt>'
            . '<BillExprDt>' . $expires->format('Y-m-d\\TH:i:s') . '</BillExprDt>'
            . '<BillAmt>' . number_format((float) $invoice->amount_total, 2, '.', '') . '</BillAmt>'
            . '<BillEqvAmt>' . number_format((float) $invoice->amount_total, 2, '.', '') . '</BillEqvAmt>'
            . '<MinPayAmt>' . number_format((float) $invoice->amount_total, 2, '.', '') . '</MinPayAmt>'
            . '<Ccy>' . e($invoice->currency) . '</Ccy>'
            . '<ExchRate>1.00</ExchRate><BillPayOpt>1</BillPayOpt>'
            . '</BillDtl></BillDtls></billSubReq>';
    }
}
