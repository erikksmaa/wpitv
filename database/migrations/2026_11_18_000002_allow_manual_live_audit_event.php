<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE live_stream_events
            DROP CHECK live_events_type_check,
            ADD CONSTRAINT live_events_type_check CHECK (event_type IN
                ('created', 'agenda_updated', 'manual_updated', 'youtube_updated', 'status_changed', 'archive_linked', 'archive_unlinked'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback would invalidate manual Live audit history.');
    }
};
