<?php

namespace App\Services\Payment;

use App\Jobs\Payment\ProcessControlNumberInboxJob;
use App\Jobs\Payment\ProcessPaymentInboxJob;
use App\Models\Payment\GepgCallback;
use App\Models\Payment\GepgControlNumberInbox;
use App\Models\Payment\GepgPaymentInbox;
use Illuminate\Support\Str;

class GepgCallbackService
{
    public function __construct(private readonly GepgCryptoService $crypto)
    {
    }

    public function handleBillResponse(string $xml): string
    {
        $reqId = $this->tag($xml, 'ReqId') ?? '';
        $billId = $this->tag($xml, 'BillId') ?? '';
        $grpBillId = $this->tag($xml, 'GrpBillId') ?? '';
        $signature = $this->tag($xml, 'signature');
        $resCode = $this->tag($xml, 'ResStsCode') ?? '7201';
        $controlNumber = $this->tag($xml, 'BillCntrNum');

        $inner = $this->extractTag($xml, 'billSubRes') ?? '';
        $verified = $this->crypto->verify($inner, $signature);

        $callback = GepgCallback::firstOrNew([
            'callback_type' => 'BILL_RESPONSE',
            'external_request_id' => $reqId ?: null,
            'transaction_id' => $billId ?: null,
        ]);
        $callback->fill([
            'bill_reference' => $grpBillId ?: $billId,
            'control_number' => $controlNumber,
            'signature' => $signature,
            'payload' => $xml,
            'verified' => $verified,
            'status_code' => $resCode,
            'status_message' => 'Accepted to inbox queue',
        ]);
        $callback->save();

        $inbox = GepgControlNumberInbox::firstOrNew([
            'external_request_id' => $reqId ?: null,
            'bill_id' => $billId ?: null,
            'grp_bill_id' => $grpBillId ?: null,
        ]);
        $inbox->fill([
            'control_number' => $controlNumber,
            'status_code' => $resCode,
            'signature' => $signature,
            'payload' => $xml,
            'verified' => $verified,
            'received_at' => now(),
        ]);
        $inbox->save();

        ProcessControlNumberInboxJob::dispatch($inbox->id)
            ->onQueue((string) config('gepg.queues.control_inbox', 'gepg-inbox-control'));

        return $this->ack('billSubResAck', $reqId, '7101');
    }

    public function handlePaymentResponse(string $xml): string
    {
        $reqId = $this->tag($xml, 'ReqId') ?? '';
        $trxId = $this->tag($xml, 'TrxId') ?? '';
        $grpBillId = $this->tag($xml, 'GrpBillId') ?? '';
        $billId = $this->tag($xml, 'BillId') ?? '';
        $controlNumber = $this->tag($xml, 'BillCtrNum') ?? $this->tag($xml, 'CustCntrNum');
        $signature = $this->tag($xml, 'signature');

        $inner = $this->extractTag($xml, 'pmtSpNtfReq') ?? '';
        $verified = $this->crypto->verify($inner, $signature);

        $callback = GepgCallback::firstOrNew([
            'callback_type' => 'PAYMENT_RESPONSE',
            'external_request_id' => $reqId ?: null,
            'transaction_id' => $trxId ?: null,
        ]);
        $callback->fill([
            'bill_reference' => $grpBillId ?: $billId,
            'control_number' => $controlNumber,
            'signature' => $signature,
            'payload' => $xml,
            'verified' => $verified,
            'status_code' => '7101',
            'status_message' => 'Accepted to inbox queue',
        ]);
        $callback->save();

        $inbox = GepgPaymentInbox::firstOrNew([
            'transaction_id' => $trxId ?: null,
        ]);
        $inbox->fill([
            'external_request_id' => $reqId ?: null,
            'bill_id' => $billId ?: null,
            'grp_bill_id' => $grpBillId ?: null,
            'control_number' => $controlNumber,
            'signature' => $signature,
            'payload' => $xml,
            'verified' => $verified,
            'received_at' => now(),
        ]);
        $inbox->save();

        ProcessPaymentInboxJob::dispatch($inbox->id)
            ->onQueue((string) config('gepg.queues.payment_inbox', 'gepg-inbox-payment'));

        return $this->ack('pmtSpNtfReqAck', $reqId, '7101');
    }

    private function ack(string $root, string $reqId, string $statusCode): string
    {
        $ackId = 'SP' . Str::uuid();
        $xml = '<' . $root . '><AckId>' . $ackId . '</AckId><ReqId>' . e($reqId) . '</ReqId><AckStsCode>' . $statusCode . '</AckStsCode></' . $root . '>';
        $signature = $this->crypto->sign($xml);

        return '<Gepg>' . $xml . '<signature>' . $signature . '</signature></Gepg>';
    }

    private function tag(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $m)) {
            return trim(html_entity_decode($m[1]));
        }

        return null;
    }

    private function extractTag(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $m)) {
            return '<' . $tag . '>' . trim($m[1]) . '</' . $tag . '>';
        }

        return null;
    }
}
