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
            throw new RuntimeException('Live streaming requires MySQL 8.0.16 or newer.');
        }

        Schema::create('live_stream_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('live_stream_id')->constrained('live_streams')->restrictOnDelete()->restrictOnUpdate();
            $table->string('event_type', 32)->collation('utf8mb4_bin');
            $table->unsignedInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            $table->string('actor_name', 100);
            $table->string('reason', 1000)->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['live_stream_id', 'created_at', 'id'], 'live_events_stream_created_index');
            $table->index(['actor_id', 'created_at']);
        });
        DB::statement("ALTER TABLE `live_stream_events` ADD CONSTRAINT `live_events_type_check` CHECK (event_type IN ('created', 'agenda_updated', 'youtube_updated', 'status_changed', 'archive_linked', 'archive_unlinked'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve live streaming audit history. Use a reviewed recovery plan.');
    }
};
