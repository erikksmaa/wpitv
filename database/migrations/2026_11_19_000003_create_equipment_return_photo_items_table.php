<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_return_photo_items', function (Blueprint $table) {

            $table->foreignId('photo_id')->constrained('equipment_return_photos')->restrictOnDelete();
            $table->foreignId('loan_item_id')->constrained('equipment_loan_items')->restrictOnDelete();
            $table->primary(['photo_id', 'loan_item_id']);
            $table->index(['loan_item_id', 'photo_id']);

        });

    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback disabled to preserve equipment evidence.');
    }
};
