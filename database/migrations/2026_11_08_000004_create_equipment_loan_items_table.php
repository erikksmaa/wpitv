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

        Schema::create('equipment_loan_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('loan_id')->constrained('equipment_loans')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('inventory_unit_id')->constrained('inventory_units')->restrictOnDelete()->restrictOnUpdate();
            $table->json('unit_snapshot');
            $table->string('condition_out', 20);
            $table->text('checkout_notes')->nullable();
            $table->dateTime('returned_at', 6)->nullable();
            $table->string('condition_in', 20)->nullable();
            $table->text('return_notes')->nullable();
            $table->dateTime('due_at_on_return', 6)->nullable();
            $table->dateTime('lost_at', 6)->nullable();
            $table->text('loss_notes')->nullable();
            $table->unsignedInteger('received_by')->nullable()->index();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->string('received_by_name', 100)->nullable();
            $table->unsignedBigInteger('active_unit_id')->nullable()->storedAs('CASE WHEN returned_at IS NULL THEN inventory_unit_id ELSE NULL END');
            $table->unique('active_unit_id');
            $table->unique(['loan_id', 'inventory_unit_id'], 'equipment_items_loan_unit_unique');
            $table->index(['loan_id', 'returned_at']);
            $table->index(['inventory_unit_id', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `equipment_loan_items` ADD CONSTRAINT `equipment_items_out_check` CHECK (condition_out IN ('good', 'minor_damage', 'major_damage'))");
        DB::statement("ALTER TABLE `equipment_loan_items` ADD CONSTRAINT `equipment_items_in_check` CHECK (condition_in IS NULL OR condition_in IN ('good', 'minor_damage', 'major_damage'))");
        DB::statement("ALTER TABLE `equipment_loan_items` ADD CONSTRAINT `equipment_items_return_check` CHECK ((returned_at IS NULL AND condition_in IS NULL AND received_by_name IS NULL AND due_at_on_return IS NULL) OR (returned_at IS NOT NULL AND condition_in IS NOT NULL AND received_by_name IS NOT NULL AND due_at_on_return IS NOT NULL))");
        DB::statement("ALTER TABLE `equipment_loan_items` ADD CONSTRAINT `equipment_items_loss_check` CHECK ((lost_at IS NULL AND loss_notes IS NULL) OR (lost_at IS NOT NULL AND loss_notes IS NOT NULL))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve inventory history. Use an explicitly reviewed recovery plan.');
    }
};
