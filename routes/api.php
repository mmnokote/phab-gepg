<?php

use App\Http\Controllers\API\Payment\GepgCallbackAPIController;
use App\Http\Controllers\API\Payment\FeeRuleAPIController;
use App\Http\Controllers\API\Payment\InvoiceAPIController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/gepg/callback')->group(function () {
    Route::post('bill-response', [GepgCallbackAPIController::class, 'billResponse']);
    Route::post('payment-response', [GepgCallbackAPIController::class, 'paymentResponse']);
});

Route::middleware('audit.columns')->group(function () {
    Route::get('payments/fee-rules', [FeeRuleAPIController::class, 'index']);
    Route::post('payments/bills', [InvoiceAPIController::class, 'createBill']);
    Route::get('payments/invoices/{invoice}', [InvoiceAPIController::class, 'show']);
});
