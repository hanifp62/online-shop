<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin status pengembalian dana. Uang hanya dianggap bergerak bila ada bukti ledger:
 * `dana_dikirim` dan `diterima` wajib membawa ID refund penyedia dan menulis baris `payments`.
 */
class RefundService
{
    /** Ledger outcome per status yang membutuhkan bukti uang bergerak. */
    private const LEDGER_EVENTS = [
        Refund::STATUS_DANA_DIKIRIM => 'refund',
        Refund::STATUS_DITERIMA => 'refund_settlement',
    ];

    public function request(OrderReturn $return, string $reason): Refund
    {
        return DB::transaction(function () use ($return, $reason) {
            $order = Order::whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $return = OrderReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->status === OrderReturn::STATUS_REJECTED) {
                throw ValidationException::withMessages(['return' => __('customer_messages.refund.rejected_return')]);
            }
            if (Refund::where('return_id', $return->id)->where('status', '!=', Refund::STATUS_DITOLAK)->exists()) {
                throw ValidationException::withMessages(['return' => __('customer_messages.refund.duplicate')]);
            }
            $this->assertWithinPaidAmount($order, $return->requested_refund_amount);

            return Refund::create([
                'order_id' => $order->id,
                'return_id' => $return->id,
                'user_id' => $return->user_id,
                'refund_number' => Refund::generateRefundNumber($order->id),
                'amount' => $return->requested_refund_amount,
                'status' => Refund::STATUS_DIAJUKAN,
                'reason' => $reason,
                'submitted_at' => now(),
            ]);
        });
    }

    public function transition(Refund $refund, string $target, array $evidence = []): Refund
    {
        return DB::transaction(function () use ($refund, $target, $evidence) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (! $refund->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => __('customer_messages.refund.transition', ['from' => $refund->status, 'to' => $target])]);
            }

            $changes = ['status' => $target];
            if ($target === Refund::STATUS_DITOLAK) {
                $reason = trim((string) ($evidence['rejection_reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['rejection_reason' => __('customer_messages.refund.rejection_required')]);
                }
                $changes += ['rejection_reason' => $reason, 'reviewed_at' => now()];
            }
            if ($target === Refund::STATUS_DISETUJUI) {
                $changes['reviewed_at'] = now();
            }

            if (isset(self::LEDGER_EVENTS[$target])) {
                // shortcut: fail-closed until a Midtrans refund API/webhook verifies the ID; remove this throw once that integration lands.
                throw ValidationException::withMessages(['status' => __('customer_messages.refund.unverified')]);
                $providerId = trim((string) ($evidence['provider_refund_id'] ?? ''));
                if ($providerId === '' || strlen($providerId) > 64) {
                    throw ValidationException::withMessages(['provider_refund_id' => __('customer_messages.refund.missing_provider_id')]);
                }
                if ($target === Refund::STATUS_DITERIMA && $providerId !== $refund->provider_refund_id) {
                    throw ValidationException::withMessages(['provider_refund_id' => __('customer_messages.refund.provider_id_mismatch')]);
                }
                Payment::create([
                    'order_id' => $refund->order_id,
                    'provider' => $refund->provider,
                    'provider_transaction_id' => $providerId,
                    'provider_order_id' => (string) $refund->order_id,
                    'event_status' => self::LEDGER_EVENTS[$target],
                    'amount' => $refund->amount,
                    'outcome' => self::LEDGER_EVENTS[$target],
                    'payload' => ['refund_number' => $refund->refund_number],
                ]);
                $changes += $target === Refund::STATUS_DANA_DIKIRIM
                    ? ['provider_refund_id' => $providerId, 'dispatched_at' => now()]
                    : ['settled_at' => now()];
            }

            $refund->update($changes);

            return $refund->fresh();
        });
    }

    private function assertWithinPaidAmount(Order $order, int $amount): void
    {
        $paid = (int) $order->payments()->where('outcome', 'paid')->sum('amount');
        if ($paid <= 0) {
            throw ValidationException::withMessages(['order' => __('customer_messages.refund.unpaid')]);
        }
        $committed = (int) Refund::where('order_id', $order->id)->where('status', '!=', Refund::STATUS_DITOLAK)->sum('amount');
        if ($amount <= 0 || $committed + $amount > $paid) {
            throw ValidationException::withMessages(['amount' => __('customer_messages.refund.excess')]);
        }
    }

    public static function summary(Refund $refund): array
    {
        return [
            'id' => $refund->id,
            'refund_number' => $refund->refund_number,
            'order_id' => $refund->order_id,
            'return_id' => $refund->return_id,
            'amount' => $refund->amount,
            'status' => $refund->status,
            'status_label' => Refund::statusLabel($refund->status),
            'rejection_reason' => $refund->rejection_reason,
            'submitted_at' => $refund->submitted_at?->toISOString(),
            'reviewed_at' => $refund->reviewed_at?->toISOString(),
            'dispatched_at' => $refund->dispatched_at?->toISOString(),
            'settled_at' => $refund->settled_at?->toISOString(),
        ];
    }
}
