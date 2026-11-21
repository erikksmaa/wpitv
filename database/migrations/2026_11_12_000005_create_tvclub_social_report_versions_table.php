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
            throw new RuntimeException('TVClub social reports require MySQL 8.');
        }

        Schema::create('tvclub_social_report_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->date('period_start');
            $table->unsignedInteger('revision_number');
            $table->unsignedSmallInteger('schema_version');
            $table->json('snapshot');
            $table->char('snapshot_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->dateTime('published_at', 6);
            $table->string('reason', 1000)->nullable();
            $table->unique(['period_start', 'revision_number'], 'social_report_period_revision');

        });

        DB::statement('ALTER TABLE tvclub_social_report_versions ADD CONSTRAINT social_report_versions_check_1 CHECK (DAYOFMONTH(period_start) = 1)');
        DB::statement('ALTER TABLE tvclub_social_report_versions ADD CONSTRAINT social_report_versions_check_2 CHECK (schema_version > 0 AND ((revision_number = 1 AND reason IS NULL) OR (revision_number > 1 AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0)))');
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve TVClub social history.');
    }
};
