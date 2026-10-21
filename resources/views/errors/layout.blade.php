<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — ZAFIRA</title>
    <style>body{margin:0;background:#f7f3ea;color:#10244a;font:15px/1.65 Manrope,system-ui,sans-serif}main{max-width:560px;margin:0 auto;padding:64px 24px}h1{font-family:'Bodoni Moda',Georgia,serif;font-weight:400;font-size:2.2rem;margin:.3em 0}a.button{display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;background:#10244a;color:#fff;text-decoration:none;margin:6px 8px 6px 0}a.button:focus-visible{outline:3px solid #b8924a;outline-offset:2px}.code{color:#b8924a;letter-spacing:.2em;font-size:.75rem}.error-id{margin-top:28px;font-size:.8rem;color:#4a5875}.error-id code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#efe8d8;padding:2px 6px;word-break:break-all}</style>
</head>
<body>
<main>
    <p class="code">@yield('code')</p>
    <h1>@yield('title')</h1>
    <p role="status">@yield('message')</p>
    <p><a class="button" href="{{ url('/') }}">{{ __('errors_ui.common.home') }}</a><a class="button" href="{{ url('/belanja') }}">{{ __('errors_ui.common.shop') }}</a></p>
    {{-- ID ini sama dengan request_id pada log server, sehingga keluhan pelanggan dapat ditelusuri tanpa membocorkan detail internal. --}}
    @if ($errorId = request()->attributes->get('request_id'))
        <p class="error-id">{{ __('errors_ui.common.incident') }}: <code>{{ $errorId }}</code><br>{{ __('errors_ui.common.incident_help') }}</p>
    @endif
</main>
</body>
</html>
