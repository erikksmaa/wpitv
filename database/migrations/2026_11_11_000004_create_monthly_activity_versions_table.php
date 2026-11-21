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

        Schema::create('monthly_activity_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_activity_reports')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('revision_number');
            $table->unsignedSmallInteger('schema_version');
            $table->json('snapshot');
            $table->char('snapshot_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('submitted_by')->nullable();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->dateTime('submitted_at', 6);
            $table->unique(['report_id', 'revision_number'], 'mav_report_revision_unique');
            $table->unique(['report_id', 'id'], 'mav_report_id_unique');
        });
        DB::statement('ALTER TABLE monthly_activity_versions ADD CONSTRAINT mar_versions_check CHECK (revision_number >= 1 AND schema_version >= 1)');
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve monthly report history.');
    }
};
