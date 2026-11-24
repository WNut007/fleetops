<?php

namespace Fleetbase\FleetOps\Support;

/**
 * Text for records the server writes itself (stored, so written once in one language).
 * The language is config('fleetops.server_text_locale'); unknown keys fall back to English.
 */
class ServerText
{
    private const TEXTS = [
        'en' => [
            'inspection.failed_title'  => 'Failed inspection: :subject',
            'inspection.failed_report' => 'Failed items: :items',
            'inspection.failed_empty'  => 'Inspection failed.',
        ],
        'th' => [
            'inspection.failed_title'  => 'ตรวจสภาพไม่ผ่าน: :subject',
            'inspection.failed_report' => 'รายการที่ไม่ผ่าน: :items',
            'inspection.failed_empty'  => 'ตรวจสภาพไม่ผ่าน',
        ],
    ];

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = strtolower(substr($locale ?? (string) config('fleetops.server_text_locale', 'en'), 0, 2));
        $text   = self::TEXTS[$locale][$key] ?? self::TEXTS['en'][$key] ?? $key;

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }
}
