<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <x-theme-preference />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin_ui.recovery.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @isset($page)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    @else
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endisset
</head>
<body>
<x-react-root :page="$page ?? null">
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">{{ __('admin_ui.common.admin_access') }}</span>
            <h1>{{ __('admin_ui.recovery.heading') }}</h1>
            <p>{{ __('admin_ui.recovery.intro') }}</p>
            <ul aria-label="{{ __('admin_ui.recovery.codes') }}">
                @foreach ($codes as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
            <a class="button button-sapphire" href="{{ route('admin.dashboard') }}">{{ __('admin_ui.recovery.saved') }}</a>
        </div>
    </section>
</main>
</x-react-root>
<x-private-page-lifecycle />
</body>
</html>
