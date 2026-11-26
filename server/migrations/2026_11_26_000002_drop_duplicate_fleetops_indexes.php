<?php

use Fleetbase\FleetOps\Support\Database\TableIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two upstream performance-index migrations (2025_11_01, 2025_12_16) added indexes that
 * repeat existing ones column for column (payload pickup/dropoff/return three times each).
 * Every copy is written on each insert and update and competes for the buffer pool, but none is
 * ever chosen over its twin. Each name below is dropped only while another index with exactly
 * the same columns remains; down() recreates whatever this migration dropped.
 *
 * Note: those upstream migrations drop their indexes by name in down() without checking, so
 * rolling them back after this one needs this one rolled back first.
 */
return new class extends Migration {
    private const DUPLICATES = [
        'orders'            => ['orders_company_created_idx', 'orders_company_scheduled_idx', 'orders_payload_uuid_idx', 'orders_tracking_number_uuid_idx'],
        'payloads'          => ['idx_payloads_pickup', 'idx_payloads_dropoff', 'idx_payloads_return', 'payloads_pickup_uuid_idx', 'payloads_dropoff_uuid_idx', 'payloads_return_uuid_idx'],
        'waypoints'         => ['idx_waypoints_payload', 'waypoints_place_uuid_idx'],
        'tracking_statuses' => ['idx_tracking_statuses_tracking_number'],
        'tracking_numbers'  => ['tracking_numbers_tracking_number_idx'],
        'drivers'           => ['drivers_user_uuid_idx', 'drivers_vendor_uuid_idx'],
        'vehicles'          => ['vehicles_vendor_uuid_idx'],
    ];

    public function up(): void
    {
        foreach (self::DUPLICATES as $table => $names) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($names as $name) {
                $indexes = TableIndexes::for($table);
                $columns = $indexes[$name] ?? null;
                if ($columns === null) {
                    continue;
                }

                // Indexes are re-read after every drop, so of two listed copies the second stays
                $twin = collect($indexes)->except($name)->contains(fn ($indexed) => $indexed === $columns);

                if ($twin) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'orders_company_created_idx'           => ['orders', ['company_uuid', 'created_at']],
            'orders_company_scheduled_idx'         => ['orders', ['company_uuid', 'scheduled_at']],
            'orders_payload_uuid_idx'              => ['orders', ['payload_uuid']],
            'orders_tracking_number_uuid_idx'      => ['orders', ['tracking_number_uuid']],
            'idx_payloads_pickup'                  => ['payloads', ['pickup_uuid']],
            'idx_payloads_dropoff'                 => ['payloads', ['dropoff_uuid']],
            'idx_payloads_return'                  => ['payloads', ['return_uuid']],
            'payloads_pickup_uuid_idx'             => ['payloads', ['pickup_uuid']],
            'payloads_dropoff_uuid_idx'            => ['payloads', ['dropoff_uuid']],
            'payloads_return_uuid_idx'             => ['payloads', ['return_uuid']],
            'idx_waypoints_payload'                => ['waypoints', ['payload_uuid']],
            'waypoints_place_uuid_idx'             => ['waypoints', ['place_uuid']],
            'idx_tracking_statuses_tracking_number' => ['tracking_statuses', ['tracking_number_uuid']],
            'tracking_numbers_tracking_number_idx' => ['tracking_numbers', ['tracking_number']],
            'drivers_user_uuid_idx'                => ['drivers', ['user_uuid']],
            'drivers_vendor_uuid_idx'              => ['drivers', ['vendor_uuid']],
            'vehicles_vendor_uuid_idx'             => ['vehicles', ['vendor_uuid']],
        ];

        foreach ($columns as $name => [$table, $cols]) {
            if (!Schema::hasTable($table) || !Schema::hasColumns($table, $cols) || isset(TableIndexes::for($table)[$name])) {
                continue;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($cols, $name));
        }
    }
};
