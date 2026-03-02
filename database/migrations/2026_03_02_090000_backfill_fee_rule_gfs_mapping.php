<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('gfs') || ! DB::getSchemaBuilder()->hasTable('fee_rules')) {
            return;
        }

        $gfsRows = [
            'GFS_REG_APP' => [
                'name' => 'Registration Application Fees',
                'description' => 'GFS account for registration application charges',
            ],
            'GFS_REG_ANNUAL' => [
                'name' => 'Registration Annual Fees',
                'description' => 'GFS account for annual registration and membership charges',
            ],
            'GFS_LICENSE' => [
                'name' => 'Practicing License Fees',
                'description' => 'GFS account for practicing license charges',
            ],
            'GFS_CPD' => [
                'name' => 'CPD Accreditation Fees',
                'description' => 'GFS account for CPD provider accreditation charges',
            ],
            'GFS_CERT' => [
                'name' => 'Certificate Service Fees',
                'description' => 'GFS account for certificate replacement/duplication charges',
            ],
            'GFS_VERIFY' => [
                'name' => 'Verification Service Fees',
                'description' => 'GFS account for verification-related charges',
            ],
        ];

        $now = now();
        $gfsIds = [];

        foreach ($gfsRows as $code => $gfs) {
            $payload = [
                'name' => $gfs['name'],
                'description' => $gfs['description'],
                'active' => true,
                'updated_by' => 'system',
                'updated_at' => $now,
                'created_by' => 'system',
                'created_at' => $now,
            ];

            if (DB::getSchemaBuilder()->hasColumn('gfs', 'gepg_gfs_code')) {
                $payload['gepg_gfs_code'] = $code;
            }

            DB::table('gfs')->updateOrInsert(
                ['code' => $code],
                $payload
            );

            $gfsIds[$code] = DB::table('gfs')->where('code', $code)->value('id');
        }

        $ruleToGfsMap = [
            'FULL_REG_APP' => 'GFS_REG_APP',
            'FULL_REG_ANNUAL' => 'GFS_REG_ANNUAL',
            'PROV_REG_APP' => 'GFS_REG_APP',
            'PROV_REG_ANNUAL' => 'GFS_REG_ANNUAL',
            'TEMP_REG_APP' => 'GFS_REG_APP',
            'TEMP_REG_ANNUAL' => 'GFS_REG_ANNUAL',
            'LICENSE_APP' => 'GFS_LICENSE',
            'LICENSE_RENEWAL' => 'GFS_LICENSE',
            'CPD_PROVIDER_ACCREDITATION' => 'GFS_CPD',
            'CERT_DUPLICATION' => 'GFS_CERT',
            'COURSE_ADVERTISEMENT' => 'GFS_VERIFY',
            'SYSTEM_VERIFICATION' => 'GFS_VERIFY',
            'VERIFICATION_PRINTING' => 'GFS_VERIFY',
        ];

        foreach ($ruleToGfsMap as $ruleCode => $gfsCode) {
            $gfsId = $gfsIds[$gfsCode] ?? null;
            if (! $gfsId) {
                continue;
            }

            DB::table('fee_rules')
                ->where('code', $ruleCode)
                ->whereNull('gfs_id')
                ->update([
                    'gfs_id' => $gfsId,
                    'updated_by' => 'system',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty: avoid destructive rollback of production mappings.
    }
};
