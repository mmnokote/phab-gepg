<?php

namespace App\Services\Payment;

use App\Models\Payment\Bill;
use App\Models\Payment\GepgControlNumberInbox;
use App\Models\Payment\GepgPaymentInbox;
use App\Models\Payment\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GepgInboxProcessorService
{
    public function processControlNumberInbox(int $inboxId): void
    {
        $inbox = GepgControlNumberInbox::query()->findOrFail($inboxId);
        if ($inbox->processed) {
            return;
        }

        DB::transaction(function () use ($inbox): void {
            $bill = Bill::query()
                ->where('grp_bill_id', $inbox->grp_bill_id)
                ->orWhere('bill_reference', $inbox->bill_id)
                ->first();

            if (! $bill) {
                throw new RuntimeException('Bill not found for control number callback.');
            }

            if ($inbox->status_code === '7101' && $inbox->control_number && $inbox->control_number !== '0') {
                $bill->control_number = $inbox->control_number;
                $bill->status = 'CONTROL_NUMBER_ISSUED';
                $bill->submit_response = $inbox->payload;
                $bill->save();

                $invoice = $bill->invoice;
                $invoice->status = 'ISSUED';
                $invoice->save();
            } else {
                $bill->status = 'FAILED';
                $bill->submit_response = $inbox->payload;
                $bill->save();
            }

            $inbox->processed = true;
            $inbox->processed_at = now();
            $inbox->last_error = null;
            $inbox->save();
        });
    }

    public function processPaymentInbox(int $inboxId): void
    {
        $inbox = GepgPaymentInbox::query()->findOrFail($inboxId);
        if ($inbox->processed) {
            return;
        }

        $xml = $inbox->payload;
        $trxId = $this->tag($xml, 'TrxId') ?? '';
        $payRefId = $this->tag($xml, 'PayRefId');
        $billedAmount = $this->toMoney($this->tag($xml, 'BillAmt'));
        $paidAmount = $this->toMoney($this->tag($xml, 'PaidAmt'));
        $ccy = $this->tag($xml, 'Ccy') ?? 'TZS';
        $payerName = $this->tag($xml, 'PyrName');
        $payerPhone = $this->tag($xml, 'PyrCellNum');
        $payerEmail = $this->tag($xml, 'PyrEmail');
        $trxDt = $this->tag($xml, 'TrxDtTm');

        DB::transaction(function () use ($inbox, $trxId, $payRefId, $billedAmount, $paidAmount, $ccy, $payerName, $payerPhone, $payerEmail, $trxDt, $xml): void {
            $bill = Bill::query()
                ->where('control_number', $inbox->control_number)
                ->orWhere('grp_bill_id', $inbox->grp_bill_id)
                ->orWhere('bill_reference', $inbox->bill_id)
                ->first();

            if (! $bill) {
                throw new RuntimeException('Bill not found for payment callback.');
            }

            $invoice = $bill->invoice;
            $expectedAmount = $this->toMoney((string) $bill->amount);
            $isFullPayment = bccomp($paidAmount, $expectedAmount, 2) === 0;

            $payment = Payment::firstOrNew(['transaction_id' => $trxId]);
            $payment->fill([
                'uuid' => (string) Str::uuid(),
                'invoice_id' => $invoice->id,
                'bill_id' => $bill->id,
                'pay_ref_id' => $payRefId,
                'billed_amount' => $billedAmount,
                'paid_amount' => $paidAmount,
                'currency' => $ccy,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_email' => $payerEmail,
                'paid_at' => $trxDt ? Carbon::parse($trxDt) : now(),
                'raw_payload' => ['xml' => $xml],
                'status' => $isFullPayment ? 'POSTED' : 'REJECTED',
                'failure_reason' => $isFullPayment ? null : 'FULL_PAYMENT_REQUIRED',
                'created_by' => 'system',
                'updated_by' => 'system',
            ]);
            $payment->save();

            if ($isFullPayment) {
                $bill->status = 'PAID';
                $bill->save();

                $invoice->status = 'PAID';
                $invoice->save();
            }

            $inbox->processed = true;
            $inbox->processed_at = now();
            $inbox->last_error = null;
            $inbox->save();
        });
    }

    private function tag(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $m)) {
            return trim(html_entity_decode($m[1]));
        }

        return null;
    }

    private function toMoney(?string $value): string
    {
        $amount = (float) ($value ?: 0);

        return number_format($amount, 2, '.', '');
    }
}
