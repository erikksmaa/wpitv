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

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve inventory history. Use an explicitly reviewed recovery plan.');
    }
};
