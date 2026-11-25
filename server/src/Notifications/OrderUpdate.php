<?php

namespace Fleetbase\FleetOps\Notifications;

use Fleetbase\FleetOps\Support\MailText;
use Fleetbase\FleetOps\Support\PushChannels;
use Fleetbase\FleetOps\Support\TrackingCode;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\Support\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;

/**
 * A message about an order, with the title and message its order config's trigger gave
 * (notify_customer / notify_driver actions). Pushed to the notifiable's own devices (a customer's
 * from `customers/register-device` or a driver's) and emailed when the notifiable has an email
 * address, so customers without the app still hear about it.
 */
class OrderUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public array $data;

    public function __construct(public Order $order, public string $title, public string $message, public string $event = 'order_update')
    {
        $this->data = ['id' => $order->public_id, 'type' => $event];
    }

    public function via($notifiable)
    {
        $channels = [FcmChannel::class, ApnChannel::class];
        if (!empty(data_get($notifiable, 'email'))) {
            $channels[] = 'mail';
        }

        return PushChannels::filter($channels);
    }

    public function toMail($notifiable)
    {
        $locale   = MailText::localeFor($notifiable);
        $tracking = data_get($this->order, 'trackingNumber.tracking_number');
        $subject  = $this->title !== '' ? $this->title : MailText::get($locale, 'update.subject', ['tracking' => $tracking]);

        return MailText::message($locale, $subject, [$this->message], $tracking ? TrackingCode::pageUrl($tracking, $this->order->company_uuid) : null);
    }

    public function toFcm($notifiable)
    {
        return PushNotification::createFcmMessage($this->title, $this->message, $this->data);
    }

    public function toApn($notifiable)
    {
        return PushNotification::createApnMessage($this->title, $this->message, $this->data, 'view_order');
    }
}
