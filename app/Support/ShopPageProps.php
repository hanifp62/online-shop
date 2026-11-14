<?php

namespace App\Support;

use App\Http\Controllers\SocialAuthController;

final class ShopPageProps
{
    public static function index(): array
    {
        $intended = '/belanja#checkout';
        $googleEnabled = SocialAuthController::enabled('google');

        return [
            'loggedIn' => auth()->check(),
            'googleEnabled' => $googleEnabled,
            'requireAuthCheckout' => (bool) config('services.shop.require_auth_checkout', true),
            'authErrors' => array_values(session('errors')?->getBag('default')->all() ?? []),
            'oldEmail' => (string) old('email', ''),
            'oldName' => (string) old('name', ''),
            'copy' => __('shop_ui.shop'),
            'assistantCopy' => __('shop_ui.assistant'),
            'footerCopy' => __('shop_ui.footer'),
            'requestCopy' => __('shop_ui.request'),
            'statuses' => __('shop_ui.status'),
            'year' => date('Y'),
            'urls' => [
                'home' => route('home'), 'catalog' => route('catalog.index'),
                'account' => route('account.show'), 'login' => route('login'),
                'loginCheckout' => route('login', ['intended' => $intended]),
                'loginSubmit' => route('login.process', ['intended' => $intended]),
                'registerCheckout' => route('register', ['intended' => $intended]),
                'registerSubmit' => route('register.store', ['intended' => $intended]),
                'googleCheckout' => route('social.redirect', ['provider' => 'google', 'intended' => $intended]),
                'cart' => route('cart.show'), 'cartStore' => route('cart.items.store'),
                'cartItems' => url('/cart/items'), 'cartClear' => route('cart.clear'),
                'addresses' => route('addresses.index'), 'addressStore' => route('addresses.store'),
                'checkout' => route('checkout.store'), 'quote' => route('checkout.quote'),
                'orders' => route('orders.index'), 'track' => route('orders.track'),
                'reviews' => route('reviews.store'), 'favorites' => route('favorites.index'),
                'products' => url('/produk'),
                'concierge' => route('concierge.store'),
                'assistantProducts' => route('assistant.products'),
                'assistantFeedback' => route('assistant.feedback'),
                'assistantChat' => url('/bantuan/chat'),
            ],
        ];
    }
}
