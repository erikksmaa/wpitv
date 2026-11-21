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
            throw new RuntimeException('Inventory foundation requires MySQL 8.0.16 or newer.');
        }

        Schema::create('equipment_audit_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            foreach (['category_id' => 'inventory_categories', 'unit_id' => 'inventory_units', 'loan_id' => 'equipment_loans', 'loan_item_id' => 'equipment_loan_items'] as $column => $target) {
                $table->foreignId($column)->nullable()->constrained($target)->restrictOnDelete()->restrictOnUpdate();
                $table->index([$column, 'created_at', 'id'], 'equipment_audit_'.$column.'_time_idx');
            }
            $table->string('event_type', 40);
            $table->char('operation_token', 36)->charset('ascii')->collation('ascii_bin')->nullable()->unique();
            $table->string('operation_actor_key', 64)->nullable();
            $table->char('operation_payload_hash', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->string('actor_name', 100);
            $table->string('reason', 1000);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->index(['actor_id', 'created_at']);
            $table->dateTime('created_at', 6);
        });
        DB::statement("ALTER TABLE `equipment_audit_events` ADD CONSTRAINT `equipment_audit_type_check` CHECK (event_type IN ('created','updated','archived','checkout','returned','corrected','lost','extended','report_exported'))");
        DB::statement("ALTER TABLE `equipment_audit_events` ADD CONSTRAINT `equipment_audit_target_check` CHECK (event_type = 'report_exported' OR category_id IS NOT NULL OR unit_id IS NOT NULL OR loan_id IS NOT NULL OR loan_item_id IS NOT NULL)");
        DB::statement("ALTER TABLE `equipment_audit_events` ADD CONSTRAINT `equipment_audit_operation_check` CHECK ((operation_token IS NULL AND operation_actor_key IS NULL AND operation_payload_hash IS NULL) OR (operation_token IS NOT NULL AND operation_actor_key IS NOT NULL AND operation_payload_hash IS NOT NULL))");
        DB::statement("ALTER TABLE `equipment_audit_events` ADD CONSTRAINT `equipment_audit_receipt_check` CHECK (event_type NOT IN ('checkout','returned','lost','extended','corrected') OR (loan_id IS NOT NULL AND operation_token IS NOT NULL))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve inventory history. Use an explicitly reviewed recovery plan.');
    }
};
