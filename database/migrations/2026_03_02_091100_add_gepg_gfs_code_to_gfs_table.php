<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gfs', function (Blueprint $table) {
            $table->string('gepg_gfs_code', 30)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('gfs', function (Blueprint $table) {
            $table->dropColumn('gepg_gfs_code');
        });
    }
};
