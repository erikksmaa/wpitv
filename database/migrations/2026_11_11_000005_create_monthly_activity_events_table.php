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

        Schema::create('monthly_activity_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_activity_reports')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('report_version_id')->nullable();
            $table->foreign(['report_id', 'report_version_id'], 'mae_report_version_fk')->references(['report_id', 'id'])->on('monthly_activity_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->string('action', 24)->collation('utf8mb4_bin');
            $table->string('from_status', 16)->collation('utf8mb4_bin')->nullable();
            $table->string('to_status', 16)->collation('utf8mb4_bin');
            $table->unsignedInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->string('actor_name', 100);
            $table->string('reason', 1000)->nullable();
            $table->json('changes')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['report_id', 'created_at', 'id'], 'mae_report_created_index');
        });
        DB::statement("ALTER TABLE monthly_activity_events ADD CONSTRAINT mar_events_check CHECK (action IN ('created','updated','submitted','revision_requested','approved') AND (from_status IS NULL OR from_status IN ('draft','submitted','revision','approved')) AND to_status IN ('draft','submitted','revision','approved'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve monthly report history.');
    }
};
