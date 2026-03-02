<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('gfs') || ! DB::getSchemaBuilder()->hasColumn('gfs', 'gepg_gfs_code')) {
            return;
        }

        DB::table('gfs')
            ->whereNull('gepg_gfs_code')
            ->update([
                'gepg_gfs_code' => DB::raw('code'),
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Non-destructive rollback by design.
    }
};
