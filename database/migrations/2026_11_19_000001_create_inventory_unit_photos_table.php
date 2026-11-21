<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_unit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->foreignId('audit_event_id')->unique()->constrained('equipment_audit_events')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unique(['unit_id', 'revision']);
            $table->string('storage_key', 100)->unique();
            $table->string('mime', 16);
            $table->unsignedInteger('byte_size');
            $table->char('sha256', 64);
            $table->dateTime('created_at', 6);
        });
        DB::statement("ALTER TABLE inventory_unit_photos ADD CHECK (byte_size BETWEEN 1 AND 5242880 AND mime IN ('image/jpeg','image/png','image/webp') AND revision > 0)");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback disabled to preserve equipment evidence.');
    }
};
