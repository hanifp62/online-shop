<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialAuthController extends Controller
{
    public const PROVIDERS = ['google', 'apple'];

    public static function enabled(string $provider): bool
    {
        return filled(config("services.$provider.client_id")) && filled(config("services.$provider.redirect"));
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        if (! self::enabled($provider)) {
            return $this->fail(__('customer_messages.social.unavailable', ['provider' => ucfirst($provider)]));
        }

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! self::enabled($provider)) {
            return $this->fail(__('customer_messages.social.unavailable', ['provider' => ucfirst($provider)]));
        }

        $linking = $request->session()->pull('social_link') === $provider && Auth::check();
        if (Auth::check() && ! $linking) {
            // Callback dikeluarkan dari grup guest demi penautan; jangan biarkan akun login berpindah diam-diam.
            return redirect()->route('account.show');
        }

        try {
            $identity = $this->driver($provider)->user();
        } catch (\Throwable $e) {
            // State/nonce tidak cocok, pengguna membatalkan, atau penyedia menolak kode.
            Log::info('Social login rejected', ['provider' => $provider, 'exception' => $e::class]);

            return $this->fail(__('customer_messages.social.failed', ['provider' => ucfirst($provider)]));
        }

        if ($linking) {
            return $this->link($request->user(), $provider, $identity);
        }

        $user = $this->resolveUser($provider, $identity);
        if (is_string($user)) {
            return $this->fail($user);
        }

        return CustomerTotpController::completeLogin($request, $user, true);
    }

    /** Mulai penautan dari halaman akun (pengguna sudah login). */
    public function linkRedirect(Request $request, string $provider): SymfonyRedirect
    {
        if (! self::enabled($provider)) {
            return redirect()->route('account.show')->withErrors(['social' => __('customer_messages.social.provider_unavailable')]);
        }
        $request->session()->put('social_link', $provider);

        return $this->driver($provider)->redirect();
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();
        $account = $user->socialAccounts()->where('provider', $provider)->first();
        if (! $account) {
            return redirect()->route('account.show');
        }
        if (! $user->has_password && $user->socialAccounts()->count() <= 1) {
            return redirect()->route('account.show')
                ->withErrors(['social' => __('customer_messages.social.last_link')]);
        }
        $account->delete();

        return redirect()->route('account.show')->with('success', __('customer_messages.social.unlinked', ['provider' => ucfirst($provider)]));
    }

    private function link(User $user, string $provider, ProviderUser $identity): RedirectResponse
    {
        $providerId = (string) $identity->getId();
        $existing = SocialAccount::where('provider', $provider)->where('provider_id', $providerId)->first();
        if ($providerId === '' || ($existing && $existing->user_id !== $user->id)) {
            return redirect()->route('account.show')
                ->withErrors(['social' => __('customer_messages.social.taken', ['provider' => ucfirst($provider)])]);
        }
        if (! $existing) {
            if ($user->socialAccounts()->where('provider', $provider)->exists()) {
                return redirect()->route('account.show')
                    ->withErrors(['social' => __('customer_messages.social.already_linked', ['provider' => ucfirst($provider)])]);
            }
            SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => $providerId]);
        }

        return redirect()->route('account.show')->with('success', __('customer_messages.social.linked', ['provider' => ucfirst($provider)]));
    }

    /** @return User|string User yang masuk, atau pesan galat untuk pengguna. */
    private function resolveUser(string $provider, ProviderUser $identity): User|string
    {
        $providerId = (string) $identity->getId();
        if ($providerId === '') {
            return __('customer_messages.social.invalid');
        }

        $linked = SocialAccount::where('provider', $provider)->where('provider_id', $providerId)->first();
        if ($linked) {
            return $linked->user;
        }

        $email = is_string($identity->getEmail()) ? mb_strtolower(trim($identity->getEmail())) : '';
        if ($email === '' || ! $this->emailVerified($identity)) {
            return __('customer_messages.social.unverified', ['provider' => ucfirst($provider)]);
        }

        // Jangan menautkan otomatis ke akun berkata sandi: pemilik email itu belum tentu
        // pendaftar akun lokal (pre-account hijacking).
        if (User::where('email', $email)->exists()) {
            return __('customer_messages.social.email_taken');
        }

        return DB::transaction(function () use ($provider, $providerId, $email, $identity) {
            $user = User::create([
                'name' => $this->uniqueName($identity->getName() ?: Str::before($email, '@')),
                'email' => $email,
                // Kata sandi acak yang tidak diketahui siapa pun; akun hanya masuk via penyedia.
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['email_verified_at' => now(), 'has_password' => false])->save();
            SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => $providerId]);

            return $user;
        });
    }

    private function emailVerified(ProviderUser $identity): bool
    {
        $raw = method_exists($identity, 'getRaw') ? $identity->getRaw() : [];
        $flag = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return $flag === true || $flag === 'true';
    }

    private function uniqueName(string $base): string
    {
        $base = Str::limit(trim($base) !== '' ? trim($base) : 'Pelanggan', 200, '');
        $name = $base;
        while (User::where('name', $name)->exists()) {
            $name = $base.' '.Str::lower(Str::random(4));
        }

        return $name;
    }

    private function driver(string $provider)
    {
        $driver = Socialite::driver($provider);

        // Apple mengirim callback via POST lintas situs; cookie sesi SameSite=lax tidak ikut,
        // jadi nonce dibawa cookie terenkripsi khusus (lihat README provider).
        return $provider === 'apple' ? $driver->stateless()->cookieNonce() : $driver;
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
