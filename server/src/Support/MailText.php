<?php

namespace Fleetbase\FleetOps\Support;

use Fleetbase\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

/**
 * Text of the order notification emails, in the recipient's language.
 *
 * The language is the recipient user's console language (setting user.<uuid>.locale; for a driver
 * or contact, its linked user). Recipients who never chose one get config('fleetops.server_text_locale').
 * Only Thai and English exist; any other language falls back to English.
 */
class MailText
{
    private const TEXTS = [
        'en' => [
            'greeting'             => 'Hello!',
            'salutation'           => 'Regards,',
            'subcopy'              => 'If you\'re having trouble clicking the ":action" button, copy and paste the URL below into your web browser:',
            'track_order'          => 'Track Order',
            'no_action'            => 'No further action is necessary.',
            'assigned.subject'     => 'New order :tracking assigned!',
            'assigned.line'        => 'You have a new order assigned.',
            'assigned.scheduled'   => 'Dispatch is scheduled for :date.',
            'dispatched.subject'   => 'Order :tracking has been dispatched!',
            'dispatched.line'      => 'An order has just been dispatched and is ready to be started.',
            'canceled.subject'     => 'Order :tracking was canceled',
            'canceled.line'        => 'Order :tracking has been canceled.',
            'completed.subject'    => 'Order :tracking has been completed',
            'completed.line'       => 'Order :tracking has been completed.',
            'failed.subject'       => 'Order :tracking delivery has failed',
            'failed.line'          => 'Delivery of order :tracking has failed.',
            'dispatch_failed.subject' => 'Order :tracking dispatch has failed!',
            'reason'               => 'Reason: :reason',
            'update.subject'       => 'Update on order :tracking',
        ],
        'th' => [
            'greeting'             => 'สวัสดีครับ/ค่ะ',
            'salutation'           => 'ขอแสดงความนับถือ',
            'subcopy'              => 'หากกดปุ่ม ":action" ไม่ได้ ให้คัดลอก URL ด้านล่างไปวางในเว็บเบราว์เซอร์:',
            'track_order'          => 'ติดตามออเดอร์',
            'no_action'            => 'ไม่ต้องดำเนินการใด ๆ เพิ่มเติม',
            'assigned.subject'     => 'มีออเดอร์ใหม่ :tracking มอบหมายให้แล้ว',
            'assigned.line'        => 'มีออเดอร์ใหม่มอบหมายให้คุณ',
            'assigned.scheduled'   => 'กำหนดจ่ายงานวันที่ :date',
            'dispatched.subject'   => 'ออเดอร์ :tracking จ่ายงานแล้ว',
            'dispatched.line'      => 'ออเดอร์นี้จ่ายงานแล้ว พร้อมเริ่มดำเนินการ',
            'canceled.subject'     => 'ออเดอร์ :tracking ถูกยกเลิก',
            'canceled.line'        => 'ออเดอร์ :tracking ถูกยกเลิกแล้ว',
            'completed.subject'    => 'ออเดอร์ :tracking เสร็จสมบูรณ์',
            'completed.line'       => 'ออเดอร์ :tracking ดำเนินการเสร็จสมบูรณ์แล้ว',
            'failed.subject'       => 'ออเดอร์ :tracking จัดส่งไม่สำเร็จ',
            'failed.line'          => 'การจัดส่งออเดอร์ :tracking ไม่สำเร็จ',
            'dispatch_failed.subject' => 'ออเดอร์ :tracking จ่ายงานไม่สำเร็จ',
            'reason'               => 'เหตุผล: :reason',
            'update.subject'       => 'อัปเดตออเดอร์ :tracking',
        ],
    ];

    /**
     * The two-letter language for a notifiable (User, Driver, Contact or anything with user_uuid).
     */
    public static function localeFor($notifiable): string
    {
        $userUuid = null;
        if ($notifiable instanceof \Fleetbase\Models\User) {
            $userUuid = $notifiable->uuid;
        } elseif (is_object($notifiable)) {
            $userUuid = data_get($notifiable, 'user_uuid');
        }

        $locale = null;
        if ($userUuid) {
            try {
                $locale = Setting::lookup('user.' . $userUuid . '.locale', null);
            } catch (\Throwable $e) {
                $locale = null;
            }
        }

        $locale = strtolower(substr((string) ($locale ?: config('fleetops.server_text_locale', 'en')), 0, 2));

        return isset(self::TEXTS[$locale]) ? $locale : 'en';
    }

    public static function get(string $locale, string $key, array $replace = []): string
    {
        $text = self::TEXTS[$locale][$key] ?? self::TEXTS['en'][$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * A date in the company's timezone, with month names in the given language (C.E. years).
     */
    public static function date($value, string $locale, ?string $timezone = null): string
    {
        if (!$value) {
            return '';
        }

        $date = Carbon::parse($value)->setTimezone($timezone ?: config('fleetops.mail_timezone', 'Asia/Bangkok'));

        return $date->locale($locale)->translatedFormat('j F Y H:i');
    }

    /**
     * Build the mail: subject, lines and a "Track Order" button, all in one language.
     *
     * @param string[] $lines lines already translated (empty ones are skipped)
     */
    public static function message(string $locale, string $subject, array $lines, ?string $actionUrl = null): MailMessage
    {
        $action  = self::get($locale, 'track_order');
        $message = (new MailMessage())
            ->subject($subject)
            ->greeting(self::get($locale, 'greeting'))
            ->salutation(self::get($locale, 'salutation'));

        foreach ($lines as $line) {
            if (is_string($line) && trim($line) !== '') {
                $message->line($line);
            }
        }

        if ($actionUrl) {
            $message->action($action, $actionUrl);
        }

        return $message->markdown('fleetops::mail.notification', [
            'subcopyText' => self::get($locale, 'subcopy', ['action' => $action]),
            'mailLocale'  => $locale,
        ]);
    }
}
