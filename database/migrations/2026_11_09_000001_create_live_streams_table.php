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

        Schema::create('live_streams', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedInteger('agenda_id')->unique();
            $table->foreign('agenda_id')->references('id')->on('wpitv_agendas')->restrictOnDelete()->restrictOnUpdate();
            $table->char('youtube_id', 11)->charset('ascii')->collation('ascii_bin')->nullable()->unique();
            $table->string('status', 16)->collation('utf8mb4_bin')->default('scheduled');
            $table->unsignedInteger('archive_video_id')->nullable()->unique();
            $table->foreign('archive_video_id')->references('id')->on('ms_video')->nullOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('live_marked_at', 6)->nullable();
            $table->dateTime('ended_marked_at', 6)->nullable();
            $table->dateTime('cancelled_marked_at', 6)->nullable();
            $table->dateTime('status_changed_at', 6);
            foreach (['status_changed_by', 'created_by', 'updated_by'] as $column) {
                $table->unsignedInteger($column)->nullable()->index();
                $table->foreign($column)->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
            }
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['status', 'agenda_id', 'id']);
        });

        // MySQL CHECK syntax is not exposed by this version of Schema Builder.
        // Never reference archive_video_id here: its FK uses ON DELETE SET NULL.
        DB::statement('ALTER TABLE `live_streams` ADD CONSTRAINT `live_streams_version_check` CHECK (version >= 1)');
        DB::statement('ALTER TABLE `live_streams` ADD CONSTRAINT `live_streams_youtube_check` CHECK (youtube_id IS NULL OR CHAR_LENGTH(youtube_id) = 11)');
        DB::statement("ALTER TABLE `live_streams` ADD CONSTRAINT `live_streams_lifecycle_check` CHECK (
            (status = 'scheduled' AND live_marked_at IS NULL AND ended_marked_at IS NULL AND cancelled_marked_at IS NULL)
            OR (status = 'live' AND youtube_id IS NOT NULL AND live_marked_at IS NOT NULL AND ended_marked_at IS NULL AND cancelled_marked_at IS NULL)
            OR (status = 'ended' AND youtube_id IS NOT NULL AND ended_marked_at IS NOT NULL AND cancelled_marked_at IS NULL AND (live_marked_at IS NULL OR ended_marked_at >= live_marked_at))
            OR (status = 'cancelled' AND cancelled_marked_at IS NOT NULL AND live_marked_at IS NULL AND ended_marked_at IS NULL)
        )");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled to preserve live streaming history. Use a reviewed recovery plan.');
    }
};
