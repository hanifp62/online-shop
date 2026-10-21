<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_FLOW = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Transisi yang boleh dipilih operator. Status `paid` tidak termasuk:
     * belum ada kontrak bukti pembayaran tepercaya yang dapat diverifikasi.
     */
    public const MANUAL_BLOCKED = ['paid'];

    protected $fillable = [
        'user_id', 'customer_name', 'customer_email', 'customer_phone', 'locale',
        'status', 'subtotal', 'discount', 'tax', 'shipping_fee', 'total',
        'idempotency_key', 'guest_scope_hash', 'payload_hash',
        'tracking_token_hash', 'tracking_expires_at', 'shipping_address', 'voucher_id',
    ];

    protected $hidden = [
        'guest_scope_hash', 'tracking_token_hash', 'tracking_expires_at',
        'customer_name', 'customer_email', 'customer_phone',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'tracking_expires_at' => 'datetime',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'shipping_fee' => 'integer',
            'total' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function shipmentSummary(): array
    {
        $shipment = $this->shipment()->with('trackingEvents')->first();

        return $shipment ? $shipment->trackingSummary() : Shipment::notYetShipped();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function hasSettledPayment(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains(fn (Payment $payment) => $payment->outcome === 'paid' && $payment->amount === (int) $this->total);
        }

        return $this->payments()->where('outcome', 'paid')->where('amount', $this->total)->exists();
    }

    public function hasPaymentAnomaly(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'completed'], true)
            && ! $this->hasSettledPayment();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_FLOW[$this->status] ?? [], true);
    }

    public function isCancellable(): bool
    {
        return $this->canTransitionTo('cancelled') || $this->status === 'cancelled';
    }
}
