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

        Schema::create('monthly_activity_attachments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('report_id')->constrained('monthly_activity_reports')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreign(['report_id', 'item_id'], 'maa_report_item_fk')->references(['report_id', 'id'])->on('monthly_activity_items')->restrictOnDelete()->restrictOnUpdate();
            $table->string('display_name', 255);
            $table->string('disk', 32);
            $table->string('storage_key', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('status', 16)->collation('utf8mb4_bin');
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['disk', 'storage_key'], 'maa_storage_unique');
        });
        DB::statement("ALTER TABLE monthly_activity_attachments ADD CONSTRAINT mar_attachments_check CHECK (size_bytes > 0 AND status IN ('pending','ready','failed'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve monthly report history.');
    }
};
