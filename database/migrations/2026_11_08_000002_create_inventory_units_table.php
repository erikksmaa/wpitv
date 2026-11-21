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

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete()->restrictOnUpdate();
            $table->string('inventory_code', 50)->unique();
            $table->string('name', 150)->index();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable()->index();
            $table->string('storage_location', 150);
            $table->string('condition', 20);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->index(['category_id', 'is_active', 'condition'], 'inventory_units_category_state_idx');
            $table->index(['is_active', 'condition']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `inventory_units` ADD CONSTRAINT `inventory_units_condition_check` CHECK (`condition` IN ('good', 'minor_damage', 'major_damage'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve inventory history. Use an explicitly reviewed recovery plan.');
    }
};
