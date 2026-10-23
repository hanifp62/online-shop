<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** FR-FE-COMP-20261023: presentation whitelist, never serialize dashboard models. */
final class AdminDashboardPageProps
{
    public static function make(array $data): array
    {
        $url = fn (string $name, mixed $id = null) => route($name, $id === null ? [] : [$id]);
        $tables = [
            'kategori' => self::table($data['categories'], fn ($row) => [
                'id' => $row->cat_id, 'title' => $row->cat_title, 'count' => $row->products_count,
                'update' => $url('admin.categories.update', $row->cat_id), 'destroy' => $url('admin.categories.destroy', $row->cat_id),
            ]),
            'produk' => self::table($data['latestProducts'], fn ($row) => [
                'id' => $row->product_id, 'title' => $row->product_title, 'slug' => $row->product_slug,
                'price' => 'Rp'.number_format($row->product_price, 0, ',', '.'), 'category' => $row->product_cat,
                'stock' => $row->stock, 'active' => (bool) $row->is_active,
                'show' => $url('products.show', $row->product_slug), 'update' => $url('admin.products.update', $row->product_id),
                'destroy' => $url('admin.products.destroy', $row->product_id),
            ]),
            'pesanan' => self::table($data['latestOrders'], fn ($row) => [
                'id' => $row->id, 'date' => $row->created_at?->format('d M Y H:i').' UTC',
                'customer' => $row->customer_name ?? $row->user?->name ?? __('admin_ui.dashboard.guest'),
                'price' => 'Rp '.number_format($row->total, 0, ',', '.'), 'status' => $row->status,
                'transitions' => array_values(array_diff(Order::STATUS_FLOW[$row->status] ?? [], Order::MANUAL_BLOCKED)),
                'update' => $url('admin.orders.update', $row->id), 'invoice' => $url('admin.orders.invoice', $row->id),
            ]),
            'user' => self::table($data['latestUsers'], fn ($row) => [
                'id' => $row->id, 'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: $row->name,
                'email' => $row->email, 'phone' => $row->mobile ?? '-',
                'address' => ($row->address1 ?? '-').($row->address2 ? ', '.$row->address2 : ''),
                'date' => $row->created_at?->format('d M Y'),
            ]),
            'audit' => self::table($data['latestBackups'], fn ($row) => [
                'id' => $row->id, 'userId' => $row->user_id,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ($row->user?->name ?? '-'),
                'email' => $row->email, 'phone' => $row->mobile ?? '-', 'date' => $row->created_at?->format('d M Y H:i'),
            ]),
            'concierge' => self::table($data['latestConcierge'], fn ($row) => [
                'id' => $row->id, 'name' => $row->name, 'userName' => $row->user ? '#'.$row->user_id.' '.$row->user->name : null,
                'phone' => $row->phone, 'notes' => $row->notes, 'email' => $row->email, 'service' => $row->service,
                'schedule' => ($row->preferred_date?->format('d M Y') ?? '-').' '.($row->preferred_time ?? ''),
                'status' => $row->status, 'transitions' => $row->availableTransitions(), 'update' => $url('admin.concierge.update', $row->id),
            ]),
            'subscriber' => self::table($data['latestSubscribers'], fn ($row) => [
                'id' => $row->id, 'email' => $row->email, 'date' => $row->created_at?->format('d M Y'),
                'destroy' => $url('admin.subscribers.destroy', $row->id),
            ]),
        ];
        $state = $data['tableState'];
        foreach (['kategori' => 'kpage', 'produk' => 'ppage', 'pesanan' => 'opage', 'user' => 'upage', 'audit' => 'bpage', 'concierge' => 'cpage', 'subscriber' => 'spage'] as $key => $page) {
            if (isset($state[$page])) {
                $state[$page] = $tables[$key]['pagination']['current'];
            }
        }
        $admin = request()->user('admin');
        $snapshot = [
            'contract' => 'admin-dashboard.v1', 'url' => route('admin.dashboard', $state),
            'tableState' => $state, 'filters' => $data['filters'], 'stats' => $data['stats'],
            'categoryOptions' => $data['categoryOptions']->map(fn ($row) => ['id' => $row->cat_id, 'title' => $row->cat_title])->values()->all(),
            'tables' => $tables,
        ];

        return [
            'locale' => app()->getLocale(),
            'copy' => ['common' => __('admin_ui.common'), 'dashboard' => __('admin_ui.dashboard'), 'table' => __('admin_ui.table_js')],
            'dashboardSnapshot' => $snapshot,
            'csrfToken' => csrf_token(),
            'flashMessage' => session('success'),
            'validationErrors' => array_values(session('errors')?->getBag('default')->all() ?? []),
            'urls' => [
                'dashboard' => $url('admin.dashboard'), 'home' => $url('home'), 'login' => $url('admin.login'),
                'categoryStore' => $url('admin.categories.store'), 'productStore' => $url('admin.products.store'),
                'report' => $url('admin.orders.report'), 'password' => $url('admin.password.update'),
                'enrol' => $admin->getRawOriginal('totp_secret') === null ? $url('admin.totp.enrol') : null,
                'operations' => $admin->isAdmin() && app(FeatureFlags::class)->enabled('admin_ops_summary', (string) $admin->id)
                    ? $url('admin.reports.operations-summary') : null,
            ],
            'recovery' => ['message' => 'Halaman belum dapat ditampilkan.', 'action' => 'Buka tampilan standar'],
        ];
    }

    private static function table(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'rows' => array_map($map, $paginator->items()),
            'pagination' => [
                'total' => $paginator->total(), 'current' => $paginator->currentPage(), 'last' => $paginator->lastPage(),
                'previous' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl(),
            ],
        ];
    }
}
