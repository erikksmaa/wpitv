<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_return_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('equipment_audit_events')->restrictOnDelete();
            $table->index(['receipt_id', 'id']);
            $table->string('storage_key', 100)->unique();
            $table->string('mime', 16);
            $table->unsignedInteger('byte_size');
            $table->char('sha256', 64);
            $table->dateTime('created_at', 6);
        });
        DB::statement("ALTER TABLE equipment_return_photos ADD CHECK (byte_size BETWEEN 1 AND 5242880 AND mime IN ('image/jpeg','image/png','image/webp'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback disabled to preserve equipment evidence.');
    }
};
