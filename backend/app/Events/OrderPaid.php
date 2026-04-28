<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADR-008 §2.3 — fired exactly once per `payment_status: * -> paid` transition,
 * via `OrderConversionObserver`. Decouples the conversion push pipeline from
 * the existing `OrderObserver` (bank-transfer celebration / blacklist) so the
 * two concerns can evolve independently and so unit tests for either side
 * don't need to know about the other.
 *
 * Listener: `App\Listeners\PushOrderPaidToConversion` (queued).
 */
class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
