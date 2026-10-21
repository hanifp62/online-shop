<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin_ui.totp.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">{{ __('admin_ui.common.admin_access') }}</span>
            <h1>{{ __('admin_ui.totp.heading') }}</h1>
            <p>{{ __('admin_ui.totp.intro') }}</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.totp.verify') }}" novalidate>
                @csrf
                <div class="field"><label for="code">{{ __('admin_ui.totp.code') }}</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>@error('code')<p id="code-error" class="form-error">{{ $message }}</p>@enderror</div>
                <button class="button button-sapphire" type="submit">{{ __('admin_ui.totp.submit') }}</button>
            </form>
            <details>
                <summary>{{ __('admin_ui.totp.help') }}</summary>
                <form method="POST" action="{{ route('admin.totp.recovery') }}" novalidate>
                    @csrf
                    <div class="field"><label for="recovery_code">{{ __('admin_ui.totp.recovery_code') }}</label><input id="recovery_code" name="recovery_code" type="text" autocomplete="off" spellcheck="false" required @error('recovery_code') aria-invalid="true" aria-describedby="recovery_code-error" @enderror>@error('recovery_code')<p id="recovery_code-error" class="form-error">{{ $message }}</p>@enderror</div>
                    <button class="button" type="submit">{{ __('admin_ui.totp.recovery_submit') }}</button>
                </form>
            </details>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="text-link" type="submit">{{ __('admin_ui.common.logout') }}</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
