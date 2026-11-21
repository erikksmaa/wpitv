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

        Schema::create('tvclub_social_posts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('account_id')->constrained('tvclub_social_accounts')->restrictOnDelete()->restrictOnUpdate();
            $table->string('platform_content_key', 100)->collation('utf8mb4_bin')->nullable();
            $table->string('url', 500);
            $table->char('url_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->dateTime('published_at', 6);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('content_type', 24);
            $table->string('responsible_label', 160);
            $table->unsignedInteger('member_id')->nullable();
            $table->unsignedInteger('program_id')->nullable();
            $table->foreign('member_id')->references('id')->on('tvclub_members')->nullOnDelete()->restrictOnUpdate();
            $table->foreign('program_id')->references('id')->on('ms_program')->nullOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('lock_version')->default(1);
            $table->dateTime('archived_at', 6)->nullable();
            $table->unique(['account_id', 'url_hash'], 'social_post_url_unique');
            $table->unique(['account_id', 'platform_content_key'], 'social_post_key_unique');
            $table->index(['account_id', 'published_at', 'id'], 'social_post_account_date');
            $table->index(['published_at', 'id'], 'social_post_period');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        DB::statement("ALTER TABLE tvclub_social_posts ADD CONSTRAINT social_posts_check_1 CHECK (content_type IN ('short_video','video','photo','carousel','story','reel','link','text','other'))");
        DB::statement('ALTER TABLE tvclub_social_posts ADD CONSTRAINT social_posts_check_2 CHECK (lock_version > 0)');
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve TVClub social history.');
    }
};
