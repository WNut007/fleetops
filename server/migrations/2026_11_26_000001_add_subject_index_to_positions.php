<?php

use Fleetbase\FleetOps\Support\Database\TableIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver's or vehicle's position history (Positions tab, playback, last known position)
 * filters company, subject and trashed rows and sorts by time. The (subject_uuid, created_at)
 * index left company and deleted_at to row lookups, so counting one driver's ~12k positions
 * read every row (3.5 s at 2.5M positions). This index answers those queries from the index.
 */
return new class extends Migration {
    private const TABLE   = 'positions';
    private const INDEX   = 'positions_company_subject_deleted_created_idx';
    private const COLUMNS = ['company_uuid', 'subject_uuid', 'deleted_at', 'created_at'];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumns(self::TABLE, self::COLUMNS)) {
            return;
        }

        if (TableIndexes::covers(self::TABLE, self::COLUMNS)) {
            return;
        }

        Schema::table(self::TABLE, fn (Blueprint $table) => $table->index(self::COLUMNS, self::INDEX));
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if ((TableIndexes::for(self::TABLE)[self::INDEX] ?? null) === self::COLUMNS) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }
    }
};
