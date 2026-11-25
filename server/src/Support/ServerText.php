<?php

namespace Fleetbase\FleetOps\Support;

/**
 * Text the server writes itself: stored records (written once, in one language) and console error
 * messages.
 * The language is config('fleetops.server_text_locale'); unknown keys fall back to English.
 */
class ServerText
{
    private const TEXTS = [
        'en' => [
            'inspection.failed_title'  => 'Failed inspection: :subject',
            'inspection.failed_report' => 'Failed items: :items',
            'inspection.failed_empty'  => 'Inspection failed.',
            'device.already_attached'      => 'Device is already attached to :target. Detach it before attaching it elsewhere.',
            'device.another_asset'         => 'another asset',
            'trailer.already_attached'     => 'Trailer is already attached to another vehicle. Detach it before moving.',
        ],
        'th' => [
            'inspection.failed_title'  => 'ตรวจสภาพไม่ผ่าน: :subject',
            'inspection.failed_report' => 'รายการที่ไม่ผ่าน: :items',
            'inspection.failed_empty'  => 'ตรวจสภาพไม่ผ่าน',
            'device.already_attached'      => 'อุปกรณ์นี้ติดตั้งอยู่กับ :target แล้ว ถอดออกก่อนจึงจะติดตั้งกับทรัพย์สินอื่นได้',
            'device.another_asset'         => 'ทรัพย์สินอื่น',
            'trailer.already_attached'     => 'หางพ่วงนี้ต่ออยู่กับรถคันอื่นแล้ว ถอดออกก่อนจึงจะย้ายได้',
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
