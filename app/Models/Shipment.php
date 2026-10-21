<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const STATUS_PENDING_MANIFEST = 'pending_manifest';

    public const STATUS_MANIFESTED = 'manifested';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed_attempt';

    protected $fillable = [
        'order_id',
        'courier',
        'tracking_number',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('recorded_at')->orderBy('id');
    }

    public function hasAwb(): bool
    {
        return ! empty($this->tracking_number);
    }

    /** Ringkasan untuk pesanan yang belum punya paket sama sekali. */
    public static function notYetShipped(): array
    {
        return [
            'courier' => null,
            'tracking_number' => null,
            'awb_assigned' => false,
            'status' => self::STATUS_PENDING_MANIFEST,
            'status_label' => __('customer_messages.shipment.awb_unavailable'),
            'explanation' => __('customer_messages.shipment.awb_pending'),
            'shipped_at' => null,
            'delivered_at' => null,
            'events' => [],
        ];
    }

    public function trackingSummary(): array
    {
        $hasAwb = $this->hasAwb();

        return [
            'courier' => $this->courier,
            'tracking_number' => $this->tracking_number,
            'awb_assigned' => $hasAwb,
            'status' => $this->status,
            'status_label' => $hasAwb
                ? __('customer_messages.shipment.in_transit', ['tracking_number' => $this->tracking_number])
                : __('customer_messages.shipment.awb_unavailable'),
            'explanation' => $hasAwb ? null : __('customer_messages.shipment.awb_pending'),
            'shipped_at' => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'events' => $this->trackingEvents->map(fn ($event) => [
                'status' => $event->status,
                'location' => $event->location,
                'description' => $event->description,
                'recorded_at' => $event->recorded_at?->toISOString(),
            ])->values()->all(),
        ];
    }
}
