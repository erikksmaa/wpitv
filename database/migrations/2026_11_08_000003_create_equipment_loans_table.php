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

        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('loan_code', 40)->unique();
            $table->char('submission_token', 36)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('checkout_actor_key', 64);
            $table->char('checkout_payload_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('borrower_name', 150);
            $table->string('student_number', 30)->index();
            $table->string('study_program', 150);
            $table->string('whatsapp_number', 16);
            $table->text('purpose');
            $table->dateTime('borrowed_at', 6)->index();
            $table->dateTime('due_at', 6)->index();
            $table->dateTime('initial_due_at', 6);
            $table->unsignedInteger('recorded_by')->nullable()->index();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->string('recorded_by_name', 100);
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `equipment_loans` ADD CONSTRAINT `equipment_loans_dates_check` CHECK (due_at >= initial_due_at AND initial_due_at >= borrowed_at)");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve inventory history. Use an explicitly reviewed recovery plan.');
    }
};
