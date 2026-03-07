<?php

namespace App\Http\Controllers\API\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment\FeeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeRuleAPIController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appCode = (string) $request->query('app_code', (string) config('gepg.default_app_code', 'DEFAULT'));

        $rows = FeeRule::query()
            ->where('app_code', $appCode)
            ->where('active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('gfs_id')
            ->orderBy('code')
            ->get([
                'id',
                'app_code',
                'code',
                'module',
                'sub_module',
                'payment_type',
                'amount',
                'currency',
                'frequency',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Fee rules retrieved successfully.',
            'data' => $rows,
        ]);
    }
}
