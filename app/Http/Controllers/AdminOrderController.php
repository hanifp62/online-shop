<?php

namespace App\Http\Controllers;

use App\Mail\RecipientLocale;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use App\Services\PdfDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    private const REPORT_MAX_ORDERS = 500;

    private const PER_PAGE_DEFAULT = 20;

    private const PER_PAGE_MAX = 50;

    public function __construct(private readonly CheckoutService $checkout) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(array_keys(Order::STATUS_FLOW))],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $perPage = min(max((int) ($validated['per_page'] ?? self::PER_PAGE_DEFAULT), 1), self::PER_PAGE_MAX);
        $query = Order::with('user:id,name')
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id');

        $total = $query->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($validated['page'] ?? 1)), $lastPage);
        $orders = $query->paginate($perPage, ['*'], 'page', $page, $total)
            ->through(fn (Order $order) => $this->orderJson($order));

        return response()->json($orders);
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items', 'statusHistory', 'payments', 'user:id,name'])->findOrFail($id);

        return response()->json(['order' => $this->orderJson($order, true)]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $order = Order::with('items')->findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Order::STATUS_FLOW))],
        ]);

        $order = $this->checkout->transition($order, $validated['status']);

        if (! $request->expectsJson()) {
            return back()->with('success', trans('admin_ui.flash.order_status', ['id' => $order->id, 'status' => trans('admin_ui.dashboard.'.$order->status)]));
        }

        return response()->json(['order' => $this->orderJson($order)]);
    }

    private function orderJson(Order $order, bool $detail = false): array
    {
        $data = [
            'id' => $order->id,
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'shipping_fee' => $order->shipping_fee,
            'total' => $order->total,
            'user' => $order->relationLoaded('user') && $order->user
                ? ['id' => $order->user->id, 'name' => $order->user->name]
                : null,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];

        if ($detail) {
            $data['shipping_address'] = $order->shipping_address;
            $data['items'] = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_title' => $item->product_title,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ])->values()->all();
            $data['shipment'] = $order->shipmentSummary();
            $data['status_history'] = $order->statusHistory->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'admin_id' => $entry->admin_id,
                'created_at' => $entry->created_at?->toISOString(),
            ])->values()->all();
            $data['payment'] = [
                'settled' => $order->hasSettledPayment(),
                'anomaly' => $order->hasPaymentAnomaly(),
                'ledger' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'provider' => $p->provider,
                    'provider_transaction_id' => $p->provider_transaction_id,
                    'event_status' => $p->event_status,
                    'outcome' => $p->outcome,
                    'amount' => $p->amount,
                    'payment_type' => data_get($p->payload, 'payment_type'),
                    'created_at' => $p->created_at?->toISOString(),
                ])->values()->all(),
            ];
        }

        return $data;
    }

    /** Laporan PDF berdasarkan created_at UTC; nilai pesanan bukan bukti pembayaran. */
    /** Ringkasan penjualan periode ini vs periode sebelumnya dengan panjang yang sama. */
    public function salesSummary(Request $request): JsonResponse
    {
        [$from, $to] = $this->reportRange($request);
        $length = $from->diffInSeconds($to) + 1;
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subSeconds($length - 1);

        $current = $this->salesMetrics($from, $to);
        $previous = $this->salesMetrics($previousFrom, $previousTo);
        $delta = fn (string $key) => $previous[$key] == 0 ? null : round(($current[$key] - $previous[$key]) / $previous[$key] * 100, 1);

        return response()->json([
            'period' => ['from' => $from->toISOString(), 'to' => $to->toISOString()],
            'previous_period' => ['from' => $previousFrom->toISOString(), 'to' => $previousTo->toISOString()],
            'current' => $current,
            'previous' => $previous,
            'change_percent' => [
                'orders' => $delta('orders'),
                'paid_value' => $delta('paid_value'),
                'payment_success_rate' => $current['payment_success_rate'] === null || $previous['payment_success_rate'] === null
                    ? null : round($current['payment_success_rate'] - $previous['payment_success_rate'], 1),
            ],
        ]);
    }

    /** Nilai dari ledger `paid` (sumber kebenaran, #001), bukan kolom status. */
    private function salesMetrics(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to]);
        $count = (clone $orders)->count();
        $paidOrders = (clone $orders)->whereHas('payments', fn ($q) => $q->where('outcome', 'paid'))->count();
        $paidValue = (int) Payment::where('outcome', 'paid')
            ->whereIn('order_id', (clone $orders)->select('id'))
            ->sum('amount');

        return [
            'orders' => $count,
            'paid_orders' => $paidOrders,
            'paid_value' => $paidValue,
            'payment_success_rate' => $count === 0 ? null : round($paidOrders / $count * 100, 1),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function reportRange(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['today', '7d', '30d', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $now = Carbon::now('UTC');
        [$from, $to] = match ($validated['period']) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                Carbon::createFromFormat('!Y-m-d', $validated['from'], 'UTC')->startOfDay(),
                Carbon::createFromFormat('!Y-m-d', $validated['to'], 'UTC')->endOfDay(),
            ],
        };

        if ($from->diffInDays($to) >= 366) {
            throw ValidationException::withMessages(['to' => trans('admin_ui.flash.report_range')]);
        }

        return [$from, $to];
    }

    public function report(Request $request, PdfDocumentService $pdf): Response
    {
        [$from, $to] = $this->reportRange($request);

        // Ambil satu baris lebih dari batas untuk mendeteksi kelebihan tanpa query count terpisah.
        $orders = Order::with('user:id,name')->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->orderBy('id')
            ->limit(self::REPORT_MAX_ORDERS + 1)->get();

        if ($orders->count() > self::REPORT_MAX_ORDERS) {
            return response(
                trans('admin_ui.report.exceeded_limit', ['max' => self::REPORT_MAX_ORDERS]),
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store']
            );
        }

        $active = $orders->where('status', '!=', 'cancelled');
        $lines = [
            trans('admin_ui.report.period', ['from' => $from->format('d-m-Y H:i'), 'to' => $to->format('d-m-Y H:i')]),
            trans('admin_ui.report.orders_created', ['count' => $orders->count(), 'cancelled' => $orders->count() - $active->count()]),
            trans('admin_ui.report.non_cancelled_value', ['value' => number_format((int) $active->sum('total'), 0, ',', '.')]),
            trans('admin_ui.report.note'),
            '',
        ];

        foreach (array_keys(Order::STATUS_FLOW) as $status) {
            $lines[] = trans('admin_ui.report.status_line', ['status' => trans('admin_ui.dashboard.'.$status), 'count' => $orders->where('status', $status)->count()]);
        }

        $lines[] = '';

        foreach ($orders as $order) {
            $lines[] = trans('admin_ui.report.row', [
                'id' => $order->id,
                'date' => $order->created_at->format('d-m-Y H:i'),
                'status' => trans('admin_ui.dashboard.'.$order->status),
                'customer' => trim((string) ($order->customer_name ?? $order->user?->name ?? '')) ?: '-',
                'total' => number_format($order->total, 0, ',', '.'),
            ]);
        }

        $filename = 'zafira-laporan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return response($pdf->render(trans('admin_ui.report.title'), $lines, trans('admin_ui.report.pagination')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Slip pesanan HTML ramah cetak tanpa dependensi eksternal.
     * shortcut: format HTML responsif sampai pustaka PDF khusus disetujui.
     */
    public function invoice(int $id): Response
    {
        $order = Order::with(['items', 'user'])->findOrFail($id);
        // Slip ditujukan ke klien: bahasa penerima, bukan bahasa operator; locale operator dipulihkan setelahnya.
        $recipientLocale = RecipientLocale::resolve($order->locale ?? $order->user?->locale);
        $operatorLocale = app()->getLocale();
        app()->setLocale($recipientLocale);
        try {
            return $this->renderInvoice($order);
        } finally {
            app()->setLocale($operatorLocale);
        }
    }

    private function renderInvoice(Order $order): Response
    {
        $addr = $order->shipping_address ?? [];
        $customerName = $order->customer_name ?? $order->user?->name ?? '-';
        $customerEmail = $order->customer_email ?? $order->user?->email ?? '-';

        $locale = app()->getLocale();
        $status = trans('admin_ui.dashboard.'.$order->status);
        $html = '<!DOCTYPE html><html lang="'.e($locale).'"><head><meta charset="utf-8"><title>'.e(trans('admin_ui.invoice.title', ['id' => $order->id])).'</title>'
            .'<style>body{font-family:serif;max-width:700px;margin:20px auto;color:#1c1b1a}h1{letter-spacing:2px;font-size:20px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border-bottom:1px solid #ddd;padding:8px;text-align:left}th:last-child,td:last-child{text-align:right}</style></head>'
            .'<body><h1>'.e(trans('admin_ui.invoice.heading', ['id' => $order->id])).'</h1>'
            .'<p>'.e(trans('admin_ui.invoice.date')).': '.$order->created_at->format('Y-m-d H:i').'<br>'.e(trans('admin_ui.invoice.client')).': '.e($customerName).' ('.e($customerEmail).')<br>'
            .e(trans('admin_ui.invoice.status')).': '.e($status).'<br>'.e(trans('admin_ui.invoice.destination')).': '.e($addr['line1'] ?? '-').', '.e($addr['city'] ?? '-').' '.e($addr['postal_code'] ?? '').'</p>'
            .'<table><thead><tr><th>'.e(trans('admin_ui.invoice.product')).'</th><th>'.e(trans('admin_ui.invoice.quantity')).'</th><th>'.e(trans('admin_ui.invoice.price')).'</th><th>'.e(trans('admin_ui.invoice.subtotal')).'</th></tr></thead><tbody>';

        foreach ($order->items as $item) {
            $html .= '<tr><td>'.e($item->product_title).'</td><td>'.$item->quantity.'</td><td>Rp '.number_format($item->unit_price, 0, ',', '.').'</td><td>Rp '.number_format($item->subtotal, 0, ',', '.').'</td></tr>';
        }

        $html .= '</tbody></table><p style="text-align:right;font-weight:bold;margin-top:20px">'
            .e(trans('admin_ui.invoice.subtotal')).': Rp '.number_format($order->subtotal, 0, ',', '.').'<br>'
            .e(trans('admin_ui.invoice.discount')).': Rp '.number_format($order->discount, 0, ',', '.').'<br>'
            .e(trans('admin_ui.invoice.total')).': Rp '.number_format($order->total, 0, ',', '.').'</p></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
