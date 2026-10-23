<?php

namespace App\Support;

final class ShopPageProps
{
    public static function index(): array
    {
        return [
            'loggedIn' => auth()->check(),
            'copy' => __('shop_ui.shop'),
            'requestCopy' => __('shop_ui.request'),
            'statuses' => __('shop_ui.status'),
            'year' => date('Y'),
            'urls' => [
                'home' => route('home'), 'catalog' => route('catalog.index'),
                'account' => route('account.show'), 'login' => route('login'),
                'cart' => route('cart.show'), 'cartStore' => route('cart.items.store'),
                'cartItems' => url('/cart/items'), 'cartClear' => route('cart.clear'),
                'addresses' => route('addresses.index'), 'addressStore' => route('addresses.store'),
                'checkout' => route('checkout.store'), 'quote' => route('checkout.quote'),
                'orders' => route('orders.index'), 'track' => route('orders.track'),
                'reviews' => route('reviews.store'), 'favorites' => route('favorites.index'),
                'products' => url('/produk'),
            ],
        ];
    }
}
