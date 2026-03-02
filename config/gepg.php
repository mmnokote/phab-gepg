<?php

return [
    'default_app_code' => env('GEPG_DEFAULT_APP_CODE', 'DEFAULT'),
    'default_profile_code' => env('GEPG_DEFAULT_PROFILE_CODE', 'DEFAULT'),
    'sp_code' => env('GEPG_SP_CODE', 'SP99400'),
    'company_code' => env('GEPG_COMPANY_CODE', 'default.sp.in'),
    'algorithm' => env('GEPG_ALGORITHM', '00S2'),
    'system_code' => env('GEPG_SYSTEM_CODE', 'TNEMC001'),
    'endpoint_url' => env('GEPG_ENDPOINT_URL', 'https://uat1.gepg.go.tz'),
    'bill_submission_path' => env('GEPG_BILL_SUBMISSION_PATH', '/api/bill/sigqrequest'),
    'reconciliation_endpoint_url' => env('GEPG_RECONCILIATION_ENDPOINT_URL', 'https://uat1.gepg.go.tz/api/reconciliation/20/request'),
    'bill_cancellation_endpoint_url' => env('GEPG_BILL_CANCELLATION_ENDPOINT_URL', 'https://uat1.gepg.go.tz/api/bill/20/cancellation'),
    'private_key_path' => env('GEPG_PRIVATE_KEY_PATH'),
    'private_key_passphrase' => env('GEPG_PRIVATE_KEY_PASSPHRASE', ''),
    'public_key_passphrase' => env('GEPG_PUBLIC_KEY_PASSPHRASE', ''),
    'public_key_path' => env('GEPG_PUBLIC_KEY_PATH'),
    'auth_private_key_path' => env('GEPG_AUTH_PRIVATE_KEY_PATH'),
    'auth_public_key_path' => env('GEPG_AUTH_PUBLIC_KEY_PATH'),
    'auth_key_store_passphrase' => env('GEPG_AUTH_KEY_STORE_PASSPHRASE', ''),
    'auth_public_key_store_passphrase' => env('GEPG_AUTH_PUBLIC_KEY_STORE_PASSPHRASE', ''),
    'verify_signature' => env('GEPG_VERIFY_SIGNATURE', false),
    'queues' => [
        'outbound' => env('GEPG_OUTBOUND_QUEUE', 'gepg-outbound'),
        'control_inbox' => env('GEPG_CONTROL_INBOX_QUEUE', 'gepg-inbox-control'),
        'payment_inbox' => env('GEPG_PAYMENT_INBOX_QUEUE', 'gepg-inbox-payment'),
    ],
];
