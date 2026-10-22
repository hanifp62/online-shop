@extends('admin.layouts.app', ['pageTitle' => __('admin_ui.dashboard.heading'), 'activePage' => 'dashboard'])
@section('title', __('admin_ui.dashboard.title'))
@section('content')
    <p style="margin-bottom: 16px; color: var(--zafira-muted);">{{ __('admin_ui.dashboard.intro') }}</p>
    {{-- Persistent outside swapped regions so screen readers hear loading and the new page summary. --}}
    <p class="sr-only" data-table-announcement role="status" aria-live="polite"></p>
    <nav class="admin-nav" aria-label="{{ __('admin_ui.dashboard.navigation') }}">
        <a href="#panel-kategori">{{ __('admin_ui.common.category') }}</a>
        <a href="#panel-produk">{{ __('admin_ui.common.product') }}</a>
        <a href="#panel-pesanan">{{ __('admin_ui.dashboard.orders_reports') }}</a>
        @if (auth('admin')->user()->isAdmin() && app(\App\Support\FeatureFlags::class)->enabled('admin_ops_summary', (string) auth('admin')->id()))
            <a href="{{ route('admin.reports.operations-summary') }}">Ringkasan operasional</a>
        @endif
        <a href="#panel-user">{{ __('admin_ui.common.user') }}</a>
        <a href="#panel-audit">{{ __('admin_ui.common.audit') }}</a>
        <a href="#panel-concierge">{{ __('admin_ui.common.concierge') }}</a>
        <a href="#panel-subscriber">{{ __('admin_ui.common.subscriber') }}</a>
        <a href="#panel-keamanan">{{ __('admin_ui.common.security') }}</a>
    </nav>

    @if (auth('admin')->user()->getRawOriginal('totp_secret') === null)
        <div class="auth-errors" role="status">
            <a class="button button-sapphire" href="{{ route('admin.totp.enrol') }}">{{ __('admin_ui.dashboard.enable_totp') }}</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="auth-errors" role="alert" style="margin-bottom:16px"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stat-grid">
        <div class="stat-card"><span>{{ __('admin_ui.common.user') }}</span><strong>{{ $stats['users'] }}</strong></div>
        <div class="stat-card"><span>{{ __('admin_ui.common.audit') }}</span><strong>{{ $stats['backups'] }}</strong></div>
        <div class="stat-card"><span>{{ __('admin_ui.common.concierge') }}</span><strong>{{ $stats['concierge'] }}</strong></div>
        <div class="stat-card"><span>{{ __('admin_ui.common.subscriber') }}</span><strong>{{ $stats['subscribers'] }}</strong></div>
        <div class="stat-card"><span>{{ __('admin_ui.common.product') }}</span><strong>{{ $stats['products'] }}</strong></div>
        <div class="stat-card"><span>{{ __('admin_ui.common.category') }}</span><strong>{{ $stats['categories'] }}</strong></div>
    </div>

    @php
        $filterLabels = ['oq' => __('admin_ui.dashboard.filter_order'), 'ostatus' => __('admin_ui.dashboard.filter_order_status'), 'status' => __('admin_ui.dashboard.filter_request_status'), 'q' => __('admin_ui.dashboard.filter_concierge')];
        $conciergeStatusLabels = ['new' => __('admin_ui.dashboard.new'), 'contacted' => __('admin_ui.dashboard.contacted'), 'confirmed' => __('admin_ui.dashboard.confirmed'), 'completed' => __('admin_ui.dashboard.completed'), 'cancelled' => __('admin_ui.dashboard.cancelled')];
        $kategoriAria = $filters['kdir'] === 'asc' ? 'ascending' : 'descending';
        $kategoriOther = \Illuminate\Support\Arr::except($tableState, ['kq', 'ksort', 'kdir', 'kpage']);
    @endphp
    <section class="admin-panel" id="panel-kategori">
        {{-- Hanya region ini yang diganti oleh admin-table.js; form tambah/laporan berada di luar agar draf tidak hilang. --}}
        <div data-admin-table="kategori" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.category_heading') }}</h2>
        <p class="panel-hint">{{ __('admin_ui.dashboard.category_hint') }}</p>
        <form class="inline-form" data-admin-table-form="kategori" method="GET" action="{{ route('admin.dashboard') }}#panel-kategori" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['kq', 'ksort', 'kdir', 'kpage']])
            <div class="field"><label for="filter-kq">{{ __('admin_ui.dashboard.search_category') }}</label><input id="filter-kq" name="kq" type="search" value="{{ $filters['kq'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.category_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.search') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'kategori', 'sortName' => 'ksort', 'dirName' => 'kdir', 'sortOptions' => ['cat_id' => 'ID', 'cat_title' => __('admin_ui.dashboard.title_col'), 'products_count' => __('admin_ui.dashboard.product_count')], 'currentSort' => $filters['ksort'], 'currentDir' => $filters['kdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="kategori" aria-live="polite">
            @if (($filters['kq'] ?? '') !== '')
                {{ __('admin_ui.common.active_filters') }} <span class="badge" data-active-filter="kq: {{ $filters['kq'] }}">{{ __('admin_ui.dashboard.category_filter', ['value' => $filters['kq']]) }}</span>
                <a data-table-link href="{{ route('admin.dashboard', $kategoriOther) }}#panel-kategori">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $categories->count(), 'total' => $categories->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'kategori'])
        @if ($categories->isEmpty())
            <p class="admin-empty">{{ ($filters['kq'] ?? '') !== '' ? __('admin_ui.dashboard.category_empty_filtered') : __('admin_ui.dashboard.category_empty') }}</p>
        @else
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.category')]) }}">
        <table class="admin-table" data-density="compact" data-table-body="kategori">
            <caption class="sr-only">{{ __('admin_ui.common.category') }}</caption>
            <thead><tr><th scope="col" class="num" @if ($filters['ksort'] === 'cat_id') aria-sort="{{ $kategoriAria }}" @endif>ID</th><th scope="col" @if ($filters['ksort'] === 'cat_title') aria-sort="{{ $kategoriAria }}" @endif>{{ __('admin_ui.dashboard.title_col') }}</th><th scope="col" class="num" @if ($filters['ksort'] === 'products_count') aria-sort="{{ $kategoriAria }}" @endif>{{ __('admin_ui.common.product') }}</th><th scope="col">{{ __('admin_ui.common.actions') }}</th></tr></thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="num">#{{ $category->cat_id }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.categories.update', $category->cat_id) }}">
                            @csrf
                            @method('PATCH')
                            <input name="cat_title" type="text" value="{{ $category->cat_title }}" required maxlength="255" aria-label="{{ __('admin_ui.dashboard.category_title_aria', ['id' => $category->cat_id]) }}">
                            <button type="submit" aria-label="{{ __('admin_ui.dashboard.category_save_aria', ['id' => $category->cat_id]) }}">{{ __('admin_ui.common.save') }}</button>
                        </form>
                    </td>
                    <td class="num">{{ $category->products_count }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category->cat_id) }}" onsubmit="return confirm(@js(__('admin_ui.dashboard.category_confirm', ['title' => $category->cat_title])))">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit" aria-label="{{ __('admin_ui.dashboard.category_delete_aria', ['title' => $category->cat_title]) }}">{{ __('admin_ui.common.delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'kategori', 'label' => 'kategori', 'paginator' => $categories])
        @endif
        </div>
        <form class="inline-form" method="POST" action="{{ route('admin.categories.store') }}" style="grid-template-columns: 1fr auto">
            @csrf
            <div class="field"><label for="cat_title">{{ __('admin_ui.dashboard.add_category') }}</label><input id="cat_title" name="cat_title" type="text" required maxlength="255" placeholder="{{ __('admin_ui.dashboard.add_category_hint') }}" autocomplete="off"></div>
            <button class="button button-sapphire" type="submit">{{ __('admin_ui.dashboard.add') }}</button>
        </form>
    </section>

    @php
        $produkAria = $filters['pdir'] === 'asc' ? 'ascending' : 'descending';
    @endphp
    <section class="admin-panel" id="panel-produk">
        <div data-admin-table="produk" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.product_heading') }}</h2>
        <p class="panel-hint">{{ __('admin_ui.dashboard.product_hint') }}</p>
        <form class="inline-form" data-admin-table-form="produk" method="GET" action="{{ route('admin.dashboard') }}#panel-produk" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['pq', 'psort', 'pdir', 'ppage']])
            <div class="field"><label for="filter-pq">{{ __('admin_ui.dashboard.search_product') }}</label><input id="filter-pq" name="pq" type="search" value="{{ $filters['pq'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.product_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.search') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'produk', 'sortName' => 'psort', 'dirName' => 'pdir', 'sortOptions' => ['product_id' => __('admin_ui.dashboard.catalog_number'), 'product_title' => __('admin_ui.dashboard.title_col'), 'product_price' => __('admin_ui.common.price'), 'stock' => __('admin_ui.common.stock')], 'currentSort' => $filters['psort'], 'currentDir' => $filters['pdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="produk" aria-live="polite">
            @if (($filters['pq'] ?? '') !== '')
                {{ __('admin_ui.common.active_filters') }} <span class="badge" data-active-filter="pq: {{ $filters['pq'] }}">{{ __('admin_ui.dashboard.product_filter', ['value' => $filters['pq']]) }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['pq', 'psort', 'pdir', 'ppage'])) }}#panel-produk">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestProducts->count(), 'total' => $latestProducts->total()]) }}
        </div>

        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'produk'])
        @if ($latestProducts->isEmpty())
            <p class="admin-empty">{{ ($filters['pq'] ?? '') !== '' ? __('admin_ui.dashboard.product_empty_filtered') : __('admin_ui.dashboard.product_empty') }}</p>
            <div class="admin-empty-action" role="status">
                @if (($filters['pq'] ?? '') !== '')
                    <a href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['pq', 'ppage'])) }}#panel-produk">{{ __('admin_ui.dashboard.clear_search') }}</a>
                @else
                    <span>{{ __('admin_ui.dashboard.empty_store') }}</span>
                    <a href="#form-produk">{{ __('admin_ui.dashboard.add_new_product') }}</a>
                @endif
            </div>
        @else
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.product')]) }}">
        <table class="admin-table" data-density="compact" data-table-body="produk">
            <caption class="sr-only">{{ __('admin_ui.common.product') }}</caption>
            <thead><tr><th scope="col" @if ($filters['psort'] === 'product_title') aria-sort="{{ $produkAria }}" @endif>{{ __('admin_ui.common.product') }}</th><th scope="col" class="num" @if ($filters['psort'] === 'product_id') aria-sort="{{ $produkAria }}" @endif>ID</th><th scope="col" class="num" @if ($filters['psort'] === 'product_price') aria-sort="{{ $produkAria }}" @endif>{{ __('admin_ui.common.price') }}</th><th scope="col" @if ($filters['psort'] === 'stock') aria-sort="{{ $produkAria }}" @endif>{{ __('admin_ui.dashboard.user_category_stock') }}</th><th scope="col">{{ __('admin_ui.common.status') }}</th><th scope="col">{{ __('admin_ui.common.actions') }}</th></tr></thead>
            <tbody>
            @foreach ($latestProducts as $product)
                <tr>
                    <td><a href="{{ route('products.show', $product->product_slug) }}">{{ $product->product_title }}</a><br><small style="color:var(--muted)">{{ $product->product_slug }}</small></td>
                    <td class="num">#{{ $product->product_id }}</td>
                    <td class="num">Rp{{ number_format($product->product_price, 0, ',', '.') }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.products.update', $product->product_id) }}">
                            @csrf
                            @method('PATCH')
                            <select name="product_cat" aria-label="{{ __('admin_ui.dashboard.category_aria', ['title' => $product->product_title]) }}">
                                @foreach ($categoryOptions as $category)
                                    <option value="{{ $category->cat_id }}" @selected($product->product_cat == $category->cat_id)>{{ $category->cat_title }}</option>
                                @endforeach
                            </select>
                            <input name="stock" type="number" min="0" max="100000" step="1" value="{{ $product->stock }}" required aria-label="{{ __('admin_ui.dashboard.stock_aria', ['title' => $product->product_title]) }}" style="width:90px">
                            <select name="is_active" aria-label="{{ __('admin_ui.dashboard.visibility_aria', ['title' => $product->product_title]) }}"><option value="1" @selected($product->is_active)>{{ __('admin_ui.dashboard.active') }}</option><option value="0" @selected(! $product->is_active)>{{ __('admin_ui.dashboard.inactive') }}</option></select>
                            <button type="submit" aria-label="{{ __('admin_ui.dashboard.save_product_aria', ['title' => $product->product_title]) }}">{{ __('admin_ui.common.save') }}</button>
                        </form>
                    </td>
                    <td><span class="badge-status {{ $product->is_active ? 'badge-confirmed' : 'badge-cancelled' }}">{{ $product->is_active ? __('admin_ui.dashboard.active') : __('admin_ui.dashboard.inactive') }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.products.destroy', $product->product_id) }}" onsubmit="return confirm(@js(__('admin_ui.dashboard.delete_product_confirm', ['title' => $product->product_title])))">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;" aria-label="{{ __('admin_ui.dashboard.delete_product_aria', ['title' => $product->product_title]) }}">{{ __('admin_ui.common.delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'produk', 'label' => 'produk', 'paginator' => $latestProducts])
        @endif
        </div>

        <details id="form-produk" @if ($latestProducts->isEmpty() && ($filters['pq'] ?? '') === '') open @endif style="margin: 12px 18px 16px; border: 1px solid var(--line); padding: 14px 18px; background: #fafafa;">
            <summary style="font-weight: 600; cursor: pointer; font-size: 13px; color: var(--sapphire);">{{ __('admin_ui.dashboard.add_new_product') }}</summary>
            <form class="inline-form" method="POST" action="{{ route('admin.products.store') }}" style="grid-template-columns: 1fr 1fr 1fr; padding: 14px 0 0; gap: 12px;">
                @csrf
                <div class="field"><label for="product_title">{{ __('admin_ui.dashboard.product_name') }}</label><input id="product_title" name="product_title" type="text" required placeholder="{{ __('admin_ui.dashboard.product_name_hint') }}"></div>
                <div class="field"><label for="product_cat">{{ __('admin_ui.common.category') }}</label>
                    <select id="product_cat" name="product_cat" required>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category->cat_id }}">{{ $category->cat_title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="product_price">{{ __('admin_ui.dashboard.price_rp') }}</label><input id="product_price" name="product_price" type="number" min="0" required placeholder="{{ __('admin_ui.dashboard.price_hint') }}"></div>
                <div class="field"><label for="product_stock">{{ __('admin_ui.common.stock') }}</label><input id="product_stock" name="stock" type="number" min="0" max="100000" step="1" value="{{ old('stock', 0) }}" required></div>
                <div class="field"><label for="product_active">{{ __('admin_ui.dashboard.show_catalog') }}</label><select id="product_active" name="is_active"><option value="1" @selected(old('is_active', '1') == '1')>{{ __('admin_ui.dashboard.active') }}</option><option value="0" @selected(old('is_active', '1') == '0')>{{ __('admin_ui.dashboard.inactive') }}</option></select></div>
                <div class="field"><label for="product_image">{{ __('admin_ui.dashboard.image_url') }}</label><input id="product_image" name="product_image" type="url" required placeholder="https://images.unsplash.com/..."></div>
                <div class="field"><label for="product_color">{{ __('admin_ui.dashboard.color') }}</label><input id="product_color" name="product_color" type="text" placeholder="{{ __('admin_ui.dashboard.color_hint') }}"></div>
                <div class="field"><label for="product_badge">{{ __('admin_ui.dashboard.badge') }}</label><input id="product_badge" name="product_badge" type="text" placeholder="{{ __('admin_ui.dashboard.badge_hint') }}"></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_description">{{ __('admin_ui.dashboard.description') }}</label><textarea id="product_description" name="product_description" rows="2" placeholder="{{ __('admin_ui.dashboard.description_hint') }}"></textarea></div>
                <div class="field"><label for="product_material">{{ __('admin_ui.dashboard.material') }}</label><input id="product_material" name="product_material" type="text" placeholder="{{ __('admin_ui.dashboard.material_hint') }}"></div>
                <div class="field"><label for="product_composition">{{ __('admin_ui.dashboard.composition') }}</label><input id="product_composition" name="product_composition" type="text" placeholder="{{ __('admin_ui.dashboard.composition_hint') }}"></div>
                <div class="field"><label for="product_origin">{{ __('admin_ui.dashboard.origin') }}</label><input id="product_origin" name="product_origin" type="text" placeholder="{{ __('admin_ui.dashboard.origin_hint') }}"></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_craft">{{ __('admin_ui.dashboard.craft') }}</label><textarea id="product_craft" name="product_craft" rows="2" placeholder="{{ __('admin_ui.dashboard.craft_hint') }}"></textarea></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_care">{{ __('admin_ui.dashboard.care') }}</label><textarea id="product_care" name="product_care" rows="2" placeholder="{{ __('admin_ui.dashboard.care_hint') }}"></textarea></div>
                <div style="grid-column: 1 / -1;"><button class="button button-sapphire" type="submit">{{ __('admin_ui.dashboard.add_product') }}</button></div>
            </form>
        </details>
    </section>

    @php
        $pesananAria = $filters['odir'] === 'asc' ? 'ascending' : 'descending';
        $pesananFilters = array_filter(['oq' => $filters['oq'] ?? '', 'ostatus' => $filters['ostatus'] ?? ''], fn ($v) => $v !== '');
        $orderStatusLabels = ['pending' => __('admin_ui.dashboard.pending'), 'paid' => __('admin_ui.dashboard.paid'), 'processing' => __('admin_ui.dashboard.processing'), 'shipped' => __('admin_ui.dashboard.shipped'), 'completed' => __('admin_ui.dashboard.completed'), 'cancelled' => __('admin_ui.dashboard.cancelled')];
    @endphp
    <section class="admin-panel" id="panel-pesanan">
        <div data-admin-table="pesanan" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.orders_reports') }}</h2>
        <p class="panel-hint">{{ __('admin_ui.dashboard.order_count_hint', ['count' => $stats['orders']]) }}</p>
        <form class="inline-form inline-form--mobile-stack" data-admin-table-form="pesanan" method="GET" action="{{ route('admin.dashboard') }}#panel-pesanan" style="grid-template-columns: 1fr 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['oq', 'osort', 'odir', 'opage', 'ostatus']])
            <div class="field"><label for="filter-ostatus">{{ __('admin_ui.common.filter_status') }}</label><select id="filter-ostatus" name="ostatus"><option value="">{{ __('admin_ui.common.all_statuses') }}</option>@foreach (array_keys(\App\Models\Order::STATUS_FLOW) as $orderStatus)<option value="{{ $orderStatus }}" @selected(($filters['ostatus'] ?? '') === $orderStatus)>{{ $orderStatusLabels[$orderStatus] }}</option>@endforeach</select></div>
            <div class="field"><label for="filter-oq">{{ __('admin_ui.dashboard.search_order') }}</label><input id="filter-oq" name="oq" type="search" value="{{ $filters['oq'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.order_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.filter') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'pesanan', 'sortName' => 'osort', 'dirName' => 'odir', 'sortOptions' => ['id' => __('admin_ui.dashboard.order_number'), 'created_at' => __('admin_ui.common.date'), 'total' => __('admin_ui.dashboard.value')], 'currentSort' => $filters['osort'], 'currentDir' => $filters['odir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="pesanan" aria-live="polite">
            @if (! empty($pesananFilters))
                {{ __('admin_ui.common.active_filters') }}
                @foreach ($pesananFilters as $key => $val)
                    <span class="badge" data-active-filter="{{ $key }}: {{ $val }}">{{ $filterLabels[$key] ?? $key }}: {{ $key === 'ostatus' ? ($orderStatusLabels[$val] ?? $val) : $val }}</span>
                @endforeach
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['oq', 'osort', 'odir', 'opage', 'ostatus'])) }}#panel-pesanan">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestOrders->count(), 'total' => $latestOrders->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'pesanan'])
        @if ($latestOrders->isNotEmpty())
            <div class="table-selection-toolbar" hidden data-table-enhancement>
                <label><input type="checkbox" data-table-select-all="pesanan"> {{ __('admin_ui.dashboard.select_all') }}</label>
                <span class="table-selection-count" data-table-selection-count="pesanan" aria-live="polite">{{ __('admin_ui.common.selected', ['count' => 0]) }}</span>
                <button class="button button-dark" type="button" data-table-copy-selection="pesanan" disabled>{{ __('admin_ui.dashboard.copy_orders') }}</button>
            </div>
        @endif
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.order')]) }}"><table class="admin-table" data-density="compact" data-table-body="pesanan">
            <caption class="sr-only">{{ __('admin_ui.common.order') }}</caption>
            <thead><tr><th scope="col" class="table-select-cell" hidden data-table-enhancement><span class="sr-only">{{ __('admin_ui.dashboard.select') }}</span></th><th scope="col" @if ($filters['osort'] === 'id') aria-sort="{{ $pesananAria }}" @endif>{{ __('admin_ui.dashboard.order_number') }}</th><th scope="col" @if ($filters['osort'] === 'created_at') aria-sort="{{ $pesananAria }}" @endif>{{ __('admin_ui.common.date') }}</th><th scope="col">{{ __('admin_ui.dashboard.client') }}</th><th scope="col" @if ($filters['osort'] === 'total') aria-sort="{{ $pesananAria }}" @endif>{{ __('admin_ui.dashboard.recorded_value') }}</th><th scope="col">{{ __('admin_ui.common.status') }}</th><th scope="col">{{ __('admin_ui.dashboard.documents') }}</th></tr></thead>
            <tbody>
            @forelse ($latestOrders as $order)
                <tr>
                    <td class="table-select-cell" hidden data-table-enhancement><label class="table-select-label"><input type="checkbox" data-table-select="pesanan" value="{{ $order->id }}" aria-label="{{ __('admin_ui.dashboard.select_order', ['id' => $order->id]) }}"></label></td>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->created_at->format('d M Y H:i') }} UTC</td>
                    <td>{{ $order->customer_name ?? $order->user?->name ?? __('admin_ui.dashboard.guest') }}</td>
                    <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                    <td><span class="badge-status badge-{{ $order->status }}">{{ $orderStatusLabels[$order->status] ?? $order->status }}</span>
                        @if (!empty(\App\Models\Order::STATUS_FLOW[$order->status]))
                            <form class="status-form" data-order-status-form method="POST" action="{{ route('admin.orders.update', $order->id) }}">
                                @csrf @method('PATCH')
                                <select name="status" required aria-label="{{ __('admin_ui.dashboard.order_status_aria', ['id' => $order->id]) }}">
                                    <option value="" disabled selected>{{ __('admin_ui.dashboard.choose_status') }}</option>
                                    @foreach (array_diff(\App\Models\Order::STATUS_FLOW[$order->status], \App\Models\Order::MANUAL_BLOCKED) as $nextStatus)
                                        <option value="{{ $nextStatus }}">{{ $orderStatusLabels[$nextStatus] ?? $nextStatus }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" aria-label="{{ __('admin_ui.dashboard.save_order_status', ['id' => $order->id]) }}">{{ __('admin_ui.common.save') }}</button>
                            </form>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.orders.invoice', $order->id) }}">{{ __('admin_ui.dashboard.order_slip') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7">{{ $pesananFilters ? __('admin_ui.dashboard.orders_empty_filtered') : __('admin_ui.dashboard.orders_empty') }}@if ($pesananFilters) <a href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['oq', 'osort', 'odir', 'opage', 'ostatus'])) }}#panel-pesanan">{{ __('admin_ui.common.reset') }}</a>@endif</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'pesanan', 'label' => 'pesanan', 'paginator' => $latestOrders])
        </div>
        <form class="inline-form" method="GET" action="{{ route('admin.orders.report') }}">
            <div class="field"><label for="report-period">{{ __('admin_ui.dashboard.report_period') }}</label><select id="report-period" name="period"><option value="today">{{ __('admin_ui.dashboard.today') }}</option><option value="7d">{{ __('admin_ui.dashboard.last_7') }}</option><option value="30d">{{ __('admin_ui.dashboard.last_30') }}</option><option value="custom">{{ __('admin_ui.dashboard.date_range') }}</option></select></div>
            <div class="field"><label for="report-from">{{ __('admin_ui.dashboard.from_utc') }}</label><input id="report-from" type="date" name="from"></div>
            <div class="field"><label for="report-to">{{ __('admin_ui.dashboard.to_utc') }}</label><input id="report-to" type="date" name="to"></div>
            <button class="button button-sapphire" type="submit">{{ __('admin_ui.dashboard.download_pdf') }}</button>
        </form>
    </section>

    @php
        $userSort = $filters['usort'];
        $userDir = $filters['udir'] === 'asc' ? 'asc' : 'desc';
        $userAriaValue = $userDir === 'asc' ? 'ascending' : 'descending';
    @endphp
    <section class="admin-panel" id="panel-user" data-admin-table="user" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.users_heading') }}</h2>
        <form class="inline-form" data-admin-table-form="user" method="GET" action="{{ route('admin.dashboard') }}#panel-user" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['uq', 'usort', 'udir', 'upage']])
            <div class="field"><label for="filter-uq">{{ __('admin_ui.dashboard.search_user') }}</label><input id="filter-uq" name="uq" type="search" value="{{ $filters['uq'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.user_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.search') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'user', 'sortName' => 'usort', 'dirName' => 'udir', 'sortOptions' => ['created_at' => __('admin_ui.dashboard.created_at'), 'name' => __('admin_ui.common.name'), 'email' => __('admin_ui.common.email_short')], 'currentSort' => $userSort, 'currentDir' => $userDir])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="user" aria-live="polite">
            @if (($filters['uq'] ?? '') !== '')
                {{ __('admin_ui.common.active_filters') }} <span class="badge" data-active-filter="uq: {{ $filters['uq'] }}">{{ __('admin_ui.dashboard.user_filter', ['value' => $filters['uq']]) }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['uq', 'usort', 'udir', 'upage'])) }}#panel-user">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestUsers->count(), 'total' => $latestUsers->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'user'])
        @if ($latestUsers->isEmpty())
            <p class="admin-empty">{{ __('admin_ui.dashboard.users_empty') }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.user')]) }}">
            <table class="admin-table" data-density="compact" data-table-body="user">
                <caption class="sr-only">{{ __('admin_ui.common.user') }}</caption>
            <thead><tr><th scope="col" @if ($userSort === 'name') aria-sort="{{ $userAriaValue }}" @endif>{{ __('admin_ui.common.name') }}</th><th scope="col" @if ($userSort === 'email') aria-sort="{{ $userAriaValue }}" @endif>{{ __('admin_ui.common.email_short') }}</th><th scope="col">{{ __('admin_ui.dashboard.phone') }}</th><th scope="col">{{ __('admin_ui.dashboard.address') }}</th><th scope="col" @if ($userSort === 'created_at') aria-sort="{{ $userAriaValue }}" @endif>{{ __('admin_ui.dashboard.created') }}</th></tr></thead>
                <tbody>
                @foreach ($latestUsers as $user)
                    <tr>
                        <td>{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->mobile ?? '—' }}</td>
                        <td style="overflow-wrap:anywhere">{{ $user->address1 ?? '—' }}{{ $user->address2 ? ', '.$user->address2 : '' }}</td>
                        <td class="num">{{ $user->created_at?->format('d M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'user', 'label' => 'user', 'paginator' => $latestUsers])
        @endif
    </section>

    @php($auditAria = $filters['bdir'] === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-audit" data-admin-table="audit" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.audit_heading') }}</h2>
        <form class="inline-form" data-admin-table-form="audit" method="GET" action="{{ route('admin.dashboard') }}#panel-audit" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['bq', 'bsort', 'bdir', 'bpage']])
            <div class="field"><label for="filter-bq">{{ __('admin_ui.dashboard.search_name_email') }}</label><input id="filter-bq" name="bq" type="search" value="{{ $filters['bq'] ?? '' }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.search') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'audit', 'sortName' => 'bsort', 'dirName' => 'bdir', 'sortOptions' => ['created_at' => __('admin_ui.dashboard.archive_date'), 'email' => __('admin_ui.common.email_short'), 'first_name' => __('admin_ui.common.name'), 'user_id' => __('admin_ui.dashboard.client_number')], 'currentSort' => $filters['bsort'], 'currentDir' => $filters['bdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="audit" aria-live="polite">
            @if (($filters['bq'] ?? '') !== '')
                {{ __('admin_ui.common.active_filters') }} <span class="badge" data-active-filter="bq: {{ $filters['bq'] }}">{{ __('admin_ui.dashboard.audit_filter', ['value' => $filters['bq']]) }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['bq', 'bsort', 'bdir', 'bpage'])) }}#panel-audit">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestBackups->count(), 'total' => $latestBackups->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'audit'])
        @if ($latestBackups->isEmpty())
            <p class="admin-empty">{{ ($filters['bq'] ?? '') !== '' ? __('admin_ui.dashboard.audit_empty_filtered') : __('admin_ui.dashboard.audit_empty') }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.audit')]) }}">
            <table class="admin-table" data-density="compact" data-table-body="audit">
                <caption class="sr-only">{{ __('admin_ui.common.audit') }}</caption>
            <thead><tr><th scope="col" class="num" @if ($filters['bsort'] === 'user_id') aria-sort="{{ $auditAria }}" @endif>{{ __('admin_ui.dashboard.user_id') }}</th><th scope="col" @if ($filters['bsort'] === 'first_name') aria-sort="{{ $auditAria }}" @endif>{{ __('admin_ui.common.name') }}</th><th scope="col" @if ($filters['bsort'] === 'email') aria-sort="{{ $auditAria }}" @endif>{{ __('admin_ui.common.email_short') }}</th><th scope="col">{{ __('admin_ui.dashboard.phone') }}</th><th scope="col" class="num" @if ($filters['bsort'] === 'created_at') aria-sort="{{ $auditAria }}" @endif>{{ __('admin_ui.dashboard.archived') }}</th></tr></thead>
                <tbody>
                @foreach ($latestBackups as $backup)
                    <tr>
                        <td class="num">#{{ $backup->user_id }}</td>
                        <td>{{ trim(($backup->first_name ?? '').' '.($backup->last_name ?? '')) ?: ($backup->user?->name ?? '—') }}</td>
                        <td>{{ $backup->email }}</td>
                        <td>{{ $backup->mobile ?? '—' }}</td>
                        <td class="num">{{ $backup->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'audit', 'label' => 'audit', 'paginator' => $latestBackups])
        @endif
    </section>

    @php($conciergeSort = $filters['csort'])
    @php($conciergeDir = $filters['cdir'] === 'asc' ? 'asc' : 'desc')
    @php($conciergeAriaValue = $conciergeDir === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-concierge" data-admin-table="concierge" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.concierge_heading') }}</h2>
        <form class="inline-form inline-form--mobile-stack" data-admin-table-form="concierge" method="GET" action="{{ route('admin.dashboard') }}#panel-concierge" style="grid-template-columns: 1fr 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['status', 'q', 'csort', 'cdir', 'cpage']])
            <div class="field"><label for="filter-status">{{ __('admin_ui.common.filter_status') }}</label><select id="filter-status" name="status"><option value="">{{ __('admin_ui.common.all_statuses') }}</option>@foreach (['new', 'contacted', 'confirmed', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $conciergeStatusLabels[$status] ?? $status }}</option>@endforeach</select></div>
            <div class="field"><label for="filter-q">{{ __('admin_ui.dashboard.search_contact') }}</label><input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.contact_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.filter') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'concierge', 'sortName' => 'csort', 'dirName' => 'cdir', 'sortOptions' => ['created_at' => __('admin_ui.dashboard.created_at'), 'name' => __('admin_ui.common.name'), 'email' => __('admin_ui.common.email_short'), 'status' => __('admin_ui.common.status'), 'preferred_date' => __('admin_ui.dashboard.schedule')], 'currentSort' => $conciergeSort, 'currentDir' => $conciergeDir])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        @php($conciergeFilters = array_filter(['status' => $filters['status'] ?? '', 'q' => $filters['q'] ?? ''], fn ($v) => $v !== ''))
        <div class="panel-context" data-panel-count="concierge" aria-live="polite">
            @if (! empty($conciergeFilters))
                {{ __('admin_ui.common.active_filters') }}
                @foreach ($conciergeFilters as $key => $val)
                    <span class="badge" data-active-filter="{{ $key }}: {{ $val }}">{{ $filterLabels[$key] ?? $key }}: {{ $key === 'status' ? ($conciergeStatusLabels[$val] ?? $val) : $val }}</span>
                @endforeach
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['status', 'q', 'csort', 'cdir', 'cpage'])) }}#panel-concierge">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestConcierge->count(), 'total' => $latestConcierge->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'concierge'])
        @if ($latestConcierge->isEmpty())
            <p class="admin-empty">{{ __('admin_ui.dashboard.requests_empty') }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.concierge')]) }}">
            <table class="admin-table" data-density="compact" data-table-body="concierge">
                <caption class="sr-only">{{ __('admin_ui.common.concierge') }}</caption>
            <thead><tr><th scope="col" @if ($conciergeSort === 'name') aria-sort="{{ $conciergeAriaValue }}" @endif>{{ __('admin_ui.dashboard.name_notes') }}</th><th scope="col" @if ($conciergeSort === 'email') aria-sort="{{ $conciergeAriaValue }}" @endif>{{ __('admin_ui.common.email_short') }}</th><th scope="col">{{ __('admin_ui.dashboard.service') }}</th><th scope="col" @if ($conciergeSort === 'preferred_date') aria-sort="{{ $conciergeAriaValue }}" @endif>{{ __('admin_ui.dashboard.schedule') }}</th><th scope="col" @if ($conciergeSort === 'status') aria-sort="{{ $conciergeAriaValue }}" @endif>{{ __('admin_ui.common.status') }}</th></tr></thead>
                <tbody>
                @foreach ($latestConcierge as $item)
                    <tr>
                        <td style="overflow-wrap:anywhere">
                            <strong>{{ $item->name }}</strong>{{ $item->user ? ' · #'.$item->user_id.' '.$item->user->name : '' }}
                            @if ($item->phone)<br><small style="color:var(--muted)">WA: {{ $item->phone }}</small>@endif
                            @if ($item->notes)
                                <div class="concierge-notes-box"><strong>{{ __('admin_ui.dashboard.notes') }}</strong><br>{{ $item->notes }}</div>
                            @endif
                        </td>
                        <td style="overflow-wrap:anywhere">{{ $item->email }}</td>
                        <td>{{ $item->service }}</td>
                        <td class="num">{{ $item->preferred_date?->format('d M Y') ?? '—' }} {{ $item->preferred_time ?? '' }}</td>
                        <td>
                            <span class="badge-status badge-{{ $item->status }}">{{ $conciergeStatusLabels[$item->status] ?? $item->status }}</span>
                            @php($available = $item->availableTransitions())
                            @if (!empty($available))
                                <form class="status-form" method="POST" action="{{ route('admin.concierge.update', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" aria-label="{{ __('admin_ui.dashboard.request_status_aria', ['id' => $item->id]) }}">
                                        @foreach ($available as $status)
                                            <option value="{{ $status }}" @selected($item->status === $status)>{{ $conciergeStatusLabels[$status] ?? $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" aria-label="{{ __('admin_ui.dashboard.save_request_status', ['id' => $item->id]) }}">{{ __('admin_ui.common.save') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'concierge', 'label' => 'concierge', 'paginator' => $latestConcierge])
        @endif
    </section>

    @php($subscriberAria = $filters['sdir'] === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-subscriber" data-admin-table="subscriber" aria-busy="false">
        <h2 tabindex="-1">{{ __('admin_ui.dashboard.subscriber_heading') }}</h2>
        <form class="inline-form" data-admin-table-form="subscriber" method="GET" action="{{ route('admin.dashboard') }}#panel-subscriber" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['sq', 'ssort', 'sdir', 'spage']])
            <div class="field"><label for="filter-sq">{{ __('admin_ui.dashboard.search_email') }}</label><input id="filter-sq" name="sq" type="search" value="{{ $filters['sq'] ?? '' }}" placeholder="{{ __('admin_ui.dashboard.email_hint_input') }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">{{ __('admin_ui.common.search') }}</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'subscriber', 'sortName' => 'ssort', 'dirName' => 'sdir', 'sortOptions' => ['created_at' => __('admin_ui.dashboard.signup_date'), 'email' => __('admin_ui.common.email_short')], 'currentSort' => $filters['ssort'], 'currentDir' => $filters['sdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="subscriber" aria-live="polite">
            @if (($filters['sq'] ?? '') !== '')
                {{ __('admin_ui.common.active_filters') }} <span class="badge" data-active-filter="sq: {{ $filters['sq'] }}">Email: {{ $filters['sq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['sq', 'ssort', 'sdir', 'spage'])) }}#panel-subscriber">{{ __('admin_ui.common.reset') }}</a> ·
            @endif
            {{ __('admin_ui.common.showing', ['count' => $latestSubscribers->count(), 'total' => $latestSubscribers->total()]) }}
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'subscriber'])
        @if ($latestSubscribers->isEmpty())
            <p class="admin-empty">{{ ($filters['sq'] ?? '') !== '' ? __('admin_ui.dashboard.subscriber_empty_filtered') : __('admin_ui.dashboard.subscriber_empty') }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.common.table_region', ['label' => __('admin_ui.common.subscriber')]) }}">
            <table class="admin-table" data-density="compact" data-table-body="subscriber">
                <caption class="sr-only">{{ __('admin_ui.common.subscriber') }}</caption>
            <thead><tr><th scope="col" @if ($filters['ssort'] === 'email') aria-sort="{{ $subscriberAria }}" @endif>{{ __('admin_ui.common.email_short') }}</th><th scope="col" class="num" @if ($filters['ssort'] === 'created_at') aria-sort="{{ $subscriberAria }}" @endif>{{ __('admin_ui.dashboard.since') }}</th><th scope="col">{{ __('admin_ui.common.actions') }}</th></tr></thead>
                <tbody>
                @foreach ($latestSubscribers as $subscriber)
                    <tr>
                        <td style="overflow-wrap:anywhere">{{ $subscriber->email }}</td>
                        <td class="num">{{ $subscriber->created_at?->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber->id) }}" onsubmit="return confirm(@js(__('admin_ui.dashboard.subscriber_confirm', ['email' => $subscriber->email])))">
                                @csrf
                                @method('DELETE')
                                <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;" aria-label="{{ __('admin_ui.dashboard.subscriber_delete_aria', ['email' => $subscriber->email]) }}">{{ __('admin_ui.common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'subscriber', 'label' => 'subscriber', 'paginator' => $latestSubscribers])
        @endif
    </section>

    <section class="admin-panel" id="panel-keamanan">
        <h2>{{ __('admin_ui.dashboard.change_password') }}</h2>
        @if ($errors->any())
            <div class="auth-errors" role="alert" style="margin:16px 18px 0"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="inline-form" method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            @method('PATCH')
            <div class="field"><label for="current_password">{{ __('admin_ui.dashboard.current_password') }}</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
            <div class="field"><label for="password">{{ __('admin_ui.dashboard.new_password') }}</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="field"><label for="password_confirmation">{{ __('admin_ui.dashboard.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
            <button class="button button-sapphire" type="submit">{{ __('admin_ui.dashboard.change') }}</button>
        </form>
    </section>

    <p style="margin-top:20px">
        <a class="text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>{{ __('admin_ui.common.back_maison') }}</span>
        </a>
    </p>
@endsection
@section('after-content')
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
@endsection
