<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->string('item_code', 50)->unique();
            $table->string('name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active');
            $table->unsignedInteger('next_unit_number')->default(2);
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('inventory_item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unique(['item_id', 'revision']);
            $table->string('storage_key', 100)->unique();
            $table->string('mime', 16);
            $table->unsignedInteger('byte_size');
            $table->char('sha256', 64);
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('created_at', 6);
        });
        Schema::create('equipment_return_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('equipment_loans')->restrictOnDelete();
            $table->foreignId('audit_event_id')->nullable()->unique()->constrained('equipment_audit_events')->restrictOnDelete();
            $table->string('receipt_code', 50)->unique();
            $table->dateTime('returned_at', 6);
            $table->unsignedInteger('received_by')->nullable();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->string('received_by_name', 100);
            $table->text('notes')->nullable();
            $table->dateTime('created_at', 6);
        });
        Schema::create('equipment_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('equipment_return_receipts')->restrictOnDelete();
            $table->foreignId('loan_item_id')->unique()->constrained('equipment_loan_items')->restrictOnDelete();
            $table->string('condition_in', 20);
            $table->text('return_notes')->nullable();
            $table->dateTime('returned_at', 6);
        });
        DB::statement("ALTER TABLE equipment_return_items ADD CONSTRAINT equipment_return_condition_check CHECK (condition_in IN ('good','minor_damage','major_damage'))");
        DB::statement("ALTER TABLE inventory_item_photos ADD CONSTRAINT inventory_item_photo_check CHECK (byte_size BETWEEN 1 AND 5242880 AND mime IN ('image/jpeg','image/png','image/webp'))");
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->dateTime('retired_at', 6)->nullable();
            $table->string('retired_reason', 1000)->nullable();
        });
        Schema::table('equipment_return_photos', function (Blueprint $table) {
            $table->foreignId('return_receipt_id')->nullable()->constrained('equipment_return_receipts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Equipment history requires a reviewed recovery procedure.');
    }
};
