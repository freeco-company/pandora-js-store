<?php

namespace App\Observers;

use App\Events\OrderPaid;
use App\Models\Order;

/**
 * ADR-008 §2.3 — fires `OrderPaid` event when payment_status transitions to
 * `paid` for the first time.
 *
 * This is intentionally **separate** from `OrderObserver` (which handles
 * blacklist + bank-transfer celebration / payment-confirmed mail). Reasons:
 *
 *   1. Single-responsibility: conversion-funnel concerns are orthogonal to
 *      mothership order lifecycle. Mixing them makes the observer hard to
 *      reason about and tests start needing to know about both worlds.
 *   2. Per the implementation brief for ADR-008 PR (do NOT modify the existing
 *      OrderObserver state machine, only attach a listener).
 *   3. Independent removal: if py-service push gets pulled later, we just
 *      unregister this observer + delete the listener; nothing else moves.
 */
class OrderConversionObserver
{
    public function updated(Order $order): void
    {
        // Same idempotency guards as OrderObserver::updated to mirror the
        // exact "moment of first paid" semantics — but we DO accept all
        // payment_methods (ecpay_credit / cod / bank_transfer), since for
        // conversion we care about every paid order, not just bank transfer.
        if (! $order->wasChanged('payment_status')) {
            return;
        }
        if ($order->payment_status !== 'paid') {
            return;
        }
        if ($order->getOriginal('payment_status') === 'paid') {
            return;
        }

        OrderPaid::dispatch($order);
    }
}
