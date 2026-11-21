<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Monthly activity reports require MySQL 8.0.16 or newer.');
        }

        Schema::create('monthly_activity_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('division', 16)->collation('utf8mb4_bin');
            $table->date('period_start');
            $table->string('status', 16)->collation('utf8mb4_bin')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->string('nil_reason', 1000)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['division', 'period_start']);
            $table->index(['status', 'period_start', 'division']);
        });
        DB::statement("ALTER TABLE monthly_activity_reports ADD CONSTRAINT mar_reports_check CHECK (division IN ('production','news') AND status IN ('draft','submitted','revision','approved') AND DAYOFMONTH(period_start) = 1 AND version >= 1)");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve monthly report history.');
    }
};
