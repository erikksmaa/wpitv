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
            throw new RuntimeException('TVClub social reports require MySQL 8.');
        }

        Schema::create('tvclub_social_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('platform', 12);
            $table->string('account_key', 100);
            $table->string('handle', 100);
            $table->string('display_name', 160);
            $table->string('profile_url', 500)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->dateTime('archived_at', 6)->nullable();
            $table->unique(['platform', 'account_key']);
            $table->unique(['platform', 'handle']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        DB::statement("ALTER TABLE tvclub_social_accounts ADD CONSTRAINT social_accounts_check_1 CHECK (platform IN ('tiktok','instagram','facebook'))");
        DB::statement('ALTER TABLE tvclub_social_accounts ADD CONSTRAINT social_accounts_check_2 CHECK (lock_version > 0)');
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve TVClub social history.');
    }
};
