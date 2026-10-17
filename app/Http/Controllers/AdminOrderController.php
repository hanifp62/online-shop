<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

    public function __construct(private readonly CheckoutService $checkout) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items', 'user:id,name,email'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['order' => Order::with(['items', 'voucher', 'user'])->findOrFail($id)]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $order = Order::with('items')->findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['paid', 'processing', 'shipped', 'completed', 'cancelled'])],
        ]);

        $order = $this->checkout->transition($order, $validated['status']);

        if (! $request->expectsJson()) {
            return back()->with('success', "Status pesanan #{$order->id} menjadi {$order->status}.");
        }

        return response()->json(['order' => $order]);
    }

    /** Laporan PDF berdasarkan created_at UTC; nilai pesanan bukan bukti pembayaran. */
    public function report(Request $request, PdfDocumentService $pdf): Response
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
            throw ValidationException::withMessages(['to' => 'Rentang laporan maksimal 366 hari.']);
        }

        // Ambil satu baris lebih dari batas untuk mendeteksi kelebihan tanpa query count terpisah.
        $orders = Order::with('user:id,name')->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->orderBy('id')
            ->limit(self::REPORT_MAX_ORDERS + 1)->get();

        if ($orders->count() > self::REPORT_MAX_ORDERS) {
            return response(
                'Laporan melebihi '.self::REPORT_MAX_ORDERS.' pesanan. Persempit rentang tanggal lalu unduh ulang.',
                422,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store']
            );
        }

        $active = $orders->where('status', '!=', 'cancelled');
        $lines = [
            'Periode (UTC): '.$from->format('d-m-Y H:i').' hingga '.$to->format('d-m-Y H:i').' UTC',
            'Pesanan dibuat: '.$orders->count().' (dibatalkan: '.($orders->count() - $active->count()).')',
            'Nilai pesanan non-batal: Rp '.number_format((int) $active->sum('total'), 0, ',', '.'),
            'Catatan: nilai pesanan tercatat, bukan pendapatan terbayar/terverifikasi.',
            '',
        ];

        foreach (array_keys(Order::STATUS_FLOW) as $status) {
            $lines[] = 'Status '.strtoupper($status).': '.$orders->where('status', $status)->count();
        }

        $lines[] = '';

        foreach ($orders as $order) {
            $customer = trim((string) ($order->user?->name ?? '')) ?: '-';
            $lines[] = sprintf('#%d | %s | %s | Pelanggan: %s | Rp %s', $order->id, $order->created_at->format('d-m-Y H:i'), strtoupper($order->status), $customer, number_format($order->total, 0, ',', '.'));
        }

        $filename = 'zafira-laporan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return response($pdf->render('ZAFIRA - LAPORAN PESANAN', $lines), 200, [
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
        $addr = $order->shipping_address ?? [];

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Slip Pesanan #'.$order->id.'</title>'
            .'<style>body{font-family:serif;max-width:700px;margin:20px auto;color:#1c1b1a}h1{letter-spacing:2px;font-size:20px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border-bottom:1px solid #ddd;padding:8px;text-align:left}th:last-child,td:last-child{text-align:right}</style></head>'
            .'<body><h1>ZAFIRA &mdash; SLIP PESANAN #'.$order->id.'</h1>'
            .'<p>Tanggal: '.$order->created_at->format('d M Y H:i').'<br>Klien: '.e($order->user->name).' ('.e($order->user->email).')<br>'
            .'Status: '.strtoupper($order->status).'<br>Tujuan: '.e($addr['line1'] ?? '-').', '.e($addr['city'] ?? '-').' '.e($addr['postal_code'] ?? '').'</p>'
            .'<table><thead><tr><th>Produk</th><th>Jml</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';

        foreach ($order->items as $item) {
            $html .= '<tr><td>'.e($item->product_title).'</td><td>'.$item->quantity.'</td><td>Rp '.number_format($item->unit_price, 0, ',', '.').'</td><td>Rp '.number_format($item->subtotal, 0, ',', '.').'</td></tr>';
        }

        $html .= '</tbody></table><p style="text-align:right;font-weight:bold;margin-top:20px">'
            .'Subtotal: Rp '.number_format($order->subtotal, 0, ',', '.').'<br>'
            .'Diskon: Rp '.number_format($order->discount, 0, ',', '.').'<br>'
            .'Total: Rp '.number_format($order->total, 0, ',', '.').'</p></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
