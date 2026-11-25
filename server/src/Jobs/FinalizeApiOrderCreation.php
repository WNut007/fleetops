<?php

namespace Fleetbase\FleetOps\Jobs;

use Fleetbase\FleetOps\Events\OrderReady;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\ServiceQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeApiOrderCreation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $orderUuid,
        public ?string $serviceQuoteUuid = null,
        public bool $shouldDispatch = false,
        public ?string $assignedDriverUuid = null,
    ) {
    }

    public function handle(): void
    {
        $order = $this->findOrder();
        if (!$order) {
            return;
        }

        $serviceQuote = $this->findServiceQuote();

        // Only the driver set at creation, and only if still assigned: a driver set or changed while this job
        // waited was already told by OrderObserver.
        $creationDriver = isset($this->assignedDriverUuid) ? $this->assignedDriverUuid : null;
        if ($creationDriver && $order->driver_assigned_uuid === $creationDriver) {
            $order->notifyDriverAssigned();
        }
        $order->setPreliminaryDistanceAndTime();
        $order->purchaseServiceQuote($serviceQuote);

        // The order may have been dispatched or started while this job waited in the
        // queue; dispatching it again would move its status backwards.
        if ($this->shouldDispatch && $order->isAwaitingDispatch()) {
            $order->dispatchWithActivity();
        }

        $this->fireOrderReady($order);
    }

    protected function findOrder(): ?Order
    {
        return Order::where('uuid', $this->orderUuid)->first();
    }

    protected function findServiceQuote(): ?ServiceQuote
    {
        return $this->serviceQuoteUuid ? ServiceQuote::where('uuid', $this->serviceQuoteUuid)->first() : null;
    }

    protected function fireOrderReady(Order $order): void
    {
        event(new OrderReady($order));
    }
}
