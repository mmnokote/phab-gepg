<?php

namespace Database\Seeders;

use App\Models\Payment\FeeRule;
use App\Models\Payment\Gfs;
use Illuminate\Database\Seeder;

class FeeRulesSeeder extends Seeder
{
    public function run(): void
    {
        $appCode = (string) config('gepg.default_app_code', 'DEFAULT');

        $gfsRows = [
            'GFS_REG_APP' => ['gepg_gfs_code' => 'GFS_REG_APP', 'name' => 'Registration Application Fees', 'description' => 'GFS account for registration application charges'],
            'GFS_REG_ANNUAL' => ['gepg_gfs_code' => 'GFS_REG_ANNUAL', 'name' => 'Registration Annual Fees', 'description' => 'GFS account for annual registration and membership charges'],
            'GFS_LICENSE' => ['gepg_gfs_code' => 'GFS_LICENSE', 'name' => 'Practicing License Fees', 'description' => 'GFS account for practicing license charges'],
            'GFS_CPD' => ['gepg_gfs_code' => 'GFS_CPD', 'name' => 'CPD Accreditation Fees', 'description' => 'GFS account for CPD provider accreditation charges'],
            'GFS_CERT' => ['gepg_gfs_code' => 'GFS_CERT', 'name' => 'Certificate Service Fees', 'description' => 'GFS account for certificate replacement/duplication charges'],
            'GFS_VERIFY' => ['gepg_gfs_code' => 'GFS_VERIFY', 'name' => 'Verification Service Fees', 'description' => 'GFS account for verification-related charges'],
        ];

        $gfsIds = [];

        foreach ($gfsRows as $code => $gfs) {
            $gfsIds[$code] = Gfs::query()->updateOrCreate(
                ['code' => $code],
                array_merge($gfs, [
                    'active' => true,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ])
            )->id;
        }

        $rules = [
            ['code' => 'FULL_REG_APP', 'module' => 'REGISTRATION', 'sub_module' => 'Full Registration', 'payment_type' => 'Full Registration Application Fee', 'gfs_code' => 'GFS_REG_APP', 'amount' => 15000, 'frequency' => 'ONE_OFF'],
            ['code' => 'FULL_REG_ANNUAL', 'module' => 'REGISTRATION', 'sub_module' => 'Full Registration', 'payment_type' => 'Full Registration Annual Membership Fee', 'gfs_code' => 'GFS_REG_ANNUAL', 'amount' => 20000, 'frequency' => 'ANNUAL'],
            ['code' => 'PROV_REG_APP', 'module' => 'REGISTRATION', 'sub_module' => 'Provisional Registration', 'payment_type' => 'Provisional Registration Application Fee', 'gfs_code' => 'GFS_REG_APP', 'amount' => 15000, 'frequency' => 'ONE_OFF'],
            ['code' => 'PROV_REG_ANNUAL', 'module' => 'REGISTRATION', 'sub_module' => 'Provisional Registration', 'payment_type' => 'Provisional Registration Annual Membership Fee', 'gfs_code' => 'GFS_REG_ANNUAL', 'amount' => 20000, 'frequency' => 'ANNUAL'],
            ['code' => 'TEMP_REG_APP', 'module' => 'REGISTRATION', 'sub_module' => 'Temporary Registration', 'payment_type' => 'Temporary Registration Fee', 'gfs_code' => 'GFS_REG_APP', 'amount' => 50000, 'frequency' => 'ONE_OFF'],
            ['code' => 'TEMP_REG_ANNUAL', 'module' => 'REGISTRATION', 'sub_module' => 'Temporary Registration', 'payment_type' => 'Temporary Registration Membership Fee', 'gfs_code' => 'GFS_REG_ANNUAL', 'amount' => 50000, 'frequency' => 'ANNUAL'],
            ['code' => 'LICENSE_APP', 'module' => 'PRACTICING_LICENSE', 'sub_module' => 'License Application', 'payment_type' => 'License Application Fee', 'gfs_code' => 'GFS_LICENSE', 'amount' => 0, 'frequency' => 'ONE_OFF'],
            ['code' => 'LICENSE_RENEWAL', 'module' => 'PRACTICING_LICENSE', 'sub_module' => 'License Renewal', 'payment_type' => 'License Renewal Fee', 'gfs_code' => 'GFS_LICENSE', 'amount' => 15000, 'frequency' => 'RENEWAL'],
            ['code' => 'CPD_PROVIDER_ACCREDITATION', 'module' => 'CPD_MANAGEMENT', 'sub_module' => 'CPD Provider Registration/Accreditation', 'payment_type' => 'CPD Provider Accreditation Fee', 'gfs_code' => 'GFS_CPD', 'amount' => 1000000, 'frequency' => 'ONE_OFF'],
            ['code' => 'CERT_DUPLICATION', 'module' => 'CERTIFICATE', 'sub_module' => 'Replacement or Duplication', 'payment_type' => 'Certificate Duplication Fee', 'gfs_code' => 'GFS_CERT', 'amount' => 100000, 'frequency' => 'ONE_OFF'],
            ['code' => 'COURSE_ADVERTISEMENT', 'module' => 'VERIFICATION_SERVICES', 'sub_module' => 'Course Advertisement', 'payment_type' => 'Course Advertisement Fee', 'gfs_code' => 'GFS_VERIFY', 'amount' => null, 'frequency' => 'ONE_OFF'],
            ['code' => 'SYSTEM_VERIFICATION', 'module' => 'VERIFICATION_SERVICES', 'sub_module' => 'System Verification Request', 'payment_type' => 'System Verification Fee', 'gfs_code' => 'GFS_VERIFY', 'amount' => null, 'frequency' => 'ONE_OFF'],
            ['code' => 'VERIFICATION_PRINTING', 'module' => 'VERIFICATION_SERVICES', 'sub_module' => 'Print/Export verified details', 'payment_type' => 'Verification Printing Fee', 'gfs_code' => 'GFS_VERIFY', 'amount' => null, 'frequency' => 'ONE_OFF'],
        ];

        foreach ($rules as $rule) {
            $gfsCode = $rule['gfs_code'];
            unset($rule['gfs_code']);

            FeeRule::query()->updateOrCreate(
                ['app_code' => $appCode, 'code' => $rule['code']],
                array_merge($rule, [
                    'app_code' => $appCode,
                    'gfs_id' => $gfsIds[$gfsCode] ?? null,
                    'currency' => 'TZS',
                    'active' => true,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                ])
            );
        }
    }
}
