@extends('admin.layouts.app', ['pageTitle' => $profilePage['copy']['title'], 'activePage' => 'profile'])
@section('title', $profilePage['copy']['title'])
@section('content')
@php
    $copy = $profilePage['copy'];
    $identity = $profilePage['identity'];
    $security = $profilePage['security'];
    $urls = $profilePage['urls'];
@endphp
<div class="profile-page profile-admin">
    <header class="profile-identity">
        <span class="profile-initials" aria-hidden="true">{{ $identity['initials'] }}</span>
        <div><h2>{{ $identity['name'] }}</h2><p>{{ $identity['email'] }}</p><p>{{ $identity['role'] }}</p></div>
    </header>
    <nav class="profile-section-nav" aria-label="{{ $copy['navigation'] }}">
        <a href="#profile-identity">{{ $copy['identity'] }}</a>
        <a href="#profile-security">{{ $copy['security'] }}</a>
        <a href="#profile-password">{{ $copy['password_heading'] }}</a>
    </nav>
    @if ($profilePage['status'])
        <div class="profile-feedback" role="status">{{ $profilePage['status'] }}</div>
    @endif
    @if ($errors->any())
        <div class="profile-feedback" id="auth-error-summary" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="profile-admin-grid">
        <section class="profile-section" id="profile-identity" aria-labelledby="profile-identity-heading">
            <h2 id="profile-identity-heading">{{ $copy['identity'] }}</h2>
            <p class="profile-note">{{ $copy['readonly'] }}</p>
            <dl class="profile-facts">
                @foreach (['name', 'email', 'role'] as $field)
                    <div><dt>{{ $copy[$field] }}</dt><dd>{{ $identity[$field] }}</dd></div>
                @endforeach
            </dl>
            <div class="field profile-theme-field">
                <label for="profile-theme">{{ $copy['theme'] }}</label>
                <select id="profile-theme" data-theme-select>
                    @foreach (['system', 'light', 'dark'] as $mode)
                        <option value="{{ $mode }}">{{ $copy['theme_'.$mode] }}</option>
                    @endforeach
                </select>
            </div>
        </section>
        <div>
            <section class="profile-section" id="profile-security" aria-labelledby="profile-security-heading">
                <h2 id="profile-security-heading">{{ $copy['security'] }}</h2>
                <p>{{ $security['totpEnabled'] ? $copy['totp_active'] : $copy['totp_inactive'] }}</p>
                <div class="profile-links">
                    @if ($urls['totpEnrol'])<a class="text-link" href="{{ $urls['totpEnrol'] }}">{{ $copy['totp_enrol'] }}</a>@endif
                    @if ($urls['authEvents'])<a class="text-link" href="{{ $urls['authEvents'] }}">{{ $copy['auth_events'] }}</a>@endif
                </div>
            </section>
            <section class="profile-section" id="profile-password" aria-labelledby="profile-password-heading">
                <h2 id="profile-password-heading">{{ $copy['password_heading'] }}</h2>
                <p id="profile-password-hint" class="profile-note">{{ $copy['password_hint'] }}</p>
                @if ($security['totpEnabled'])<p class="profile-note">{{ $copy['password_effect'] }}</p>@endif
                <form method="POST" action="{{ $urls['password'] }}" data-auth-form data-error-summary="auth-error-summary" novalidate>
                    @csrf
                    @method('PATCH')
                    @foreach (['current_password' => 'current_password', 'password' => 'new_password', 'password_confirmation' => 'confirm_password'] as $field => $label)
                        <div class="field">
                            <label for="{{ $field }}">{{ $copy[$label] }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" @if ($field !== 'current_password') minlength="8" @endif required @error($field) aria-invalid="true" aria-describedby="{{ str_replace('_', '-', $field) }}-error" @enderror>
                            @error($field)<small id="{{ str_replace('_', '-', $field) }}-error">{{ $message }}</small>@enderror
                        </div>
                    @endforeach
                    <button class="button button-sapphire" type="submit">{{ $copy['password_submit'] }}</button>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection
