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

        Schema::create('monthly_activity_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_activity_reports')->restrictOnDelete()->restrictOnUpdate();
            $table->date('activity_date');
            $table->string('title', 200);
            foreach (['description', 'outcome', 'obstacles', 'follow_up', 'contribution'] as $field) {
                $table->text($field);
            }
            $table->string('source_type', 24)->collation('utf8mb4_bin')->nullable();
            $table->unsignedInteger('source_id')->nullable();
            $table->json('source_context')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['report_id', 'id'], 'mai_report_id_unique');
            $table->unique(['report_id', 'source_type', 'source_id'], 'mai_report_source_unique');
            $table->index(['report_id', 'activity_date', 'id'], 'mai_report_date_index');
        });
        DB::statement("ALTER TABLE monthly_activity_items ADD CONSTRAINT mar_items_check CHECK ((source_type IS NULL AND source_id IS NULL) OR (source_type IS NOT NULL AND source_type IN ('agenda','coverage','news','video') AND source_id IS NOT NULL AND source_id > 0))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve monthly report history.');
    }
};
