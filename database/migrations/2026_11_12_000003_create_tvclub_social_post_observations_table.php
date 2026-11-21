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

        Schema::create('tvclub_social_post_observations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('post_id')->constrained('tvclub_social_posts')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('observed_at', 6);
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->string('source_note', 500)->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('corrects_id')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->unique(['post_id', 'id'], 'social_post_observation_owner');
            $table->unique('corrects_id', 'social_post_correction_unique');
            $table->foreign(['post_id', 'corrects_id'], 'social_post_correction_fk')->references(['post_id', 'id'])->on('tvclub_social_post_observations')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['post_id', 'observed_at', 'id'], 'social_post_observed');
            $table->dateTime('created_at', 6);
        });

        DB::statement('ALTER TABLE tvclub_social_post_observations ADD CONSTRAINT social_post_observations_check_1 CHECK ((corrects_id IS NULL AND reason IS NULL) OR (corrects_id IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0))');
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve TVClub social history.');
    }
};
