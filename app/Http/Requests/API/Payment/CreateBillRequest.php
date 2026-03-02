<?php

namespace App\Http\Requests\API\Payment;

use App\Common\Requests\APIRequest;
use Illuminate\Validation\Rule;

class CreateBillRequest extends APIRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $appCode = (string) $this->input('app_code', (string) config('gepg.default_app_code', 'DEFAULT'));

        return [
            'app_code' => ['nullable', 'string', 'max:50'],
            'profile_code' => ['nullable', 'string', 'max:50'],
            'fee_rule_code' => [
                'required',
                'string',
                Rule::exists('fee_rules', 'code')
                    ->whereNull('deleted_at')
                    ->where('active', true)
                    ->whereNotNull('gfs_id')
                    ->where('app_code', $appCode),
            ],
            'trigger_reference' => ['prohibited'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'payer_email' => ['nullable', 'email', 'max:150'],
            'expires_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
