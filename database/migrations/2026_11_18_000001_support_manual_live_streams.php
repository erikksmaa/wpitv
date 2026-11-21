<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One atomic MySQL DDL statement. Keep the existing unique key and Agenda FK.
        DB::statement("ALTER TABLE live_streams
            MODIFY agenda_id INT UNSIGNED NULL,
            ADD manual_title VARCHAR(200) NULL,
            ADD manual_program_id INT UNSIGNED NULL,
            ADD manual_starts_at DATETIME(6) NULL,
            ADD manual_ends_at DATETIME(6) NULL,
            ADD manual_visibility VARCHAR(8) COLLATE utf8mb4_bin NULL,
            ADD CONSTRAINT live_streams_manual_program_fk FOREIGN KEY (manual_program_id)
                REFERENCES ms_program (id) ON DELETE SET NULL ON UPDATE RESTRICT,
            ADD CONSTRAINT live_streams_manual_details_check CHECK (
                (manual_title IS NULL AND manual_starts_at IS NULL AND manual_ends_at IS NULL AND manual_visibility IS NULL)
                OR (manual_title IS NOT NULL AND CHAR_LENGTH(TRIM(manual_title)) > 0
                    AND manual_starts_at IS NOT NULL AND manual_visibility IS NOT NULL
                    AND manual_visibility IN ('public', 'internal')
                    AND (manual_ends_at IS NULL OR manual_ends_at > manual_starts_at))
            )");
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback would discard manual Live streaming history.');
    }
};
