<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gfs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('fee_rules', function (Blueprint $table) {
            $table->foreignId('gfs_id')
                ->nullable()
                ->after('payment_type')
                ->constrained('gfs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fee_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gfs_id');
        });

        Schema::dropIfExists('gfs');
    }
};
