<?php

namespace Database\Seeders;

use App\Models\Payment\GepgBillConfig;
use Illuminate\Database\Seeder;

class GepgBillConfigSeeder extends Seeder
{
    public function run(): void
    {
        $appCode = (string) config('gepg.default_app_code', 'DEFAULT');
        $profileCode = (string) config('gepg.default_profile_code', 'DEFAULT');

        GepgBillConfig::query()->updateOrCreate(
            ['app_code' => $appCode, 'profile_code' => $profileCode],
            [
                'code' => $appCode . '-' . $profileCode,
                'app_code' => $appCode,
                'profile_code' => $profileCode,
                'active' => true,
                'sp_grp_code' => (string) config('gepg.sp_code', 'SP99400'),
                'sys_code' => (string) config('gepg.system_code', 'TNEMC001'),
                'sp_code' => (string) config('gepg.sp_code', 'SP99400'),
                'bill_typ' => 1,
                'pay_typ' => 2,
                'coll_cent_code' => 'CC1014000119400',
                'cust_id_typ' => 1,
                'bill_gen_by' => 'System',
                'bill_appr_by' => 'System',
                'bill_pay_opt' => 3,
                'pay_plan' => 1,
                'pay_lim_typ' => 1,
                'pay_lim_amt' => 0.00,
                'min_pay_amt' => 100.00,
                'sub_sp_code' => '1001',
                'use_item_ref_on_pay' => 'N',
                'coll_sp' => (string) config('gepg.sp_code', 'SP99400'),
                'coll_psp' => null,
                'bill_ttl_days' => 30,
                'created_by' => 'system',
                'updated_by' => 'system',
            ]
        );
    }
}
