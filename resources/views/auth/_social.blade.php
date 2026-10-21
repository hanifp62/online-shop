@php($socialProviders = collect(['google' => 'Google', 'apple' => 'Apple'])->filter(fn ($label, $provider) => \App\Http\Controllers\SocialAuthController::enabled($provider)))
@if ($socialProviders->isNotEmpty())
    <div class="social-auth" role="group" aria-label="{{ __('auth_ui.social.group') }}">
        @foreach ($socialProviders as $provider => $label)
            <a class="button button-dark" href="{{ route('social.redirect', $provider) }}" data-social-provider="{{ $provider }}">
                @if ($provider === 'google')
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M21.35 11.1H12v2.98h5.35c-.23 1.45-1.67 4.25-5.35 4.25a5.9 5.9 0 0 1 0-11.8c1.87 0 3.12.8 3.83 1.48l2.6-2.5A9.4 9.4 0 0 0 12 2.6a9.4 9.4 0 1 0 0 18.8c5.43 0 9.03-3.82 9.03-9.2 0-.62-.07-1.09-.15-1.58z"/></svg>
                @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M16.37 1.43c0 1.14-.46 2.22-1.2 3-.8.84-2.1 1.49-3.15 1.4-.13-1.1.42-2.25 1.15-3 .82-.85 2.2-1.46 3.2-1.4zM20.5 17.3c-.55 1.27-.82 1.84-1.52 2.96-.99 1.57-2.38 3.53-4.1 3.54-1.53.02-1.93-1-4.01-.99-2.08.01-2.52 1.01-4.05.99-1.72-.02-3.04-1.78-4.03-3.35C-.12 16.1-.4 11.1 1.3 8.7c1.2-1.7 3.1-2.7 4.88-2.7 1.82 0 2.96 1 4.46 1 1.46 0 2.35-1 4.45-1 1.6 0 3.3.87 4.5 2.38-3.95 2.17-3.31 7.8.9 8.92z"/></svg>
                @endif
                <span>{{ __('auth_ui.social.continue', ['provider' => $label]) }}</span>
            </a>
        @endforeach
    </div>
@endif
