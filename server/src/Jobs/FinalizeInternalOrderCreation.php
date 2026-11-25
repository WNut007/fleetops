<?php

namespace Fleetbase\FleetOps\Jobs;

use Fleetbase\FleetOps\Events\OrderReady;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeInternalOrderCreation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string|null $assignedDriverUuid the driver set when the order was created. The driver is told here only
     *                                        if still assigned; a driver set or changed after creation was already
     *                                        told by OrderObserver, so telling them again sent duplicate notifications
     */
    public function __construct(public string $orderUuid, public ?string $assignedDriverUuid = null)
    {
    }

    public function handle(): void
    {
        $order = $this->findOrder();
        if (!$order) {
            return;
        }

        // Only the driver set at creation, and only if still assigned: a driver set or changed while this job
        // waited was already told by OrderObserver.
        $creationDriver = isset($this->assignedDriverUuid) ? $this->assignedDriverUuid : null;
        if ($creationDriver && $order->driver_assigned_uuid === $creationDriver) {
            $order->notifyDriverAssigned();
        }

        $this->fireOrderReady($order);
    }

    protected function findOrder(): ?Order
    {
        return Order::where('uuid', $this->orderUuid)->first();
    }

    protected function fireOrderReady(Order $order): void
    {
        event(new OrderReady($order));
    }
}
