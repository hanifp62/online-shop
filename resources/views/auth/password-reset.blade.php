<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth_ui.reset.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="{{ __('auth_ui.common.visual_label') }}">
        <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="{{ __('auth_ui.common.image_alt') }}">
        <a class="brand" href="{{ route('home') }}"><x-brand-mark /></a>
        <div class="auth-quote"><h2>{{ __('auth_ui.reset.quote_heading') }}</h2><p>{{ __('auth_ui.reset.quote_body') }}</p></div>
    </section>
    <section class="auth-content">
        <div class="auth-card">
            <h1>{{ __('auth_ui.reset.heading') }}</h1>
            <p>{{ __('auth_ui.reset.intro') }}</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field"><label for="email">{{ __('auth_ui.common.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
                <div class="field"><label for="password">{{ __('auth_ui.reset.new_password') }}</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
                <div class="field"><label for="password_confirmation">{{ __('auth_ui.reset.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
                <button class="button button-sapphire" type="submit">{{ __('auth_ui.reset.submit') }}</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
