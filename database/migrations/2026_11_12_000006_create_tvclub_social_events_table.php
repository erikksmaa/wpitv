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

        Schema::create('tvclub_social_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('action', 30);
            $table->string('target_type', 24);
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->json('changes')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['target_type', 'target_id', 'created_at', 'id'], 'social_event_target');

        });

        DB::statement("ALTER TABLE tvclub_social_events ADD CONSTRAINT social_events_check_1 CHECK (target_type IN ('account','post','post_observation','account_observation','report'))");
        DB::statement("ALTER TABLE tvclub_social_events ADD CONSTRAINT social_events_check_2 CHECK (action IN ('created','updated','archived','observed','corrected','published','exported'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve TVClub social history.');
    }
};
