<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('account_ui.unsubscribe.title') }}</title>
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
<main class="auth-page" style="display:block;padding:48px 24px;max-width:560px;margin:0 auto">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ __('account_ui.common.home') }}"><x-brand-mark /></a>
    @if ($done)
    <h1>{{ __('account_ui.unsubscribe.done_heading') }}</h1>
    <p role="status">{{ __('account_ui.unsubscribe.done_intro') }}</p>
    @else
    <h1>{{ __('account_ui.unsubscribe.heading') }}</h1>
    <p>{{ __('account_ui.unsubscribe.intro') }}</p>
    <form method="post" action="{{ $action }}">@csrf<button class="button button-sapphire" type="submit">{{ __('account_ui.unsubscribe.submit') }}</button></form>
    @endif
    <p><a class="button button-sapphire" href="{{ route('shop.index') }}">{{ __('account_ui.unsubscribe.shop') }}</a></p>
</main>
</x-react-root>
<x-private-page-lifecycle />
</body>
</html>
