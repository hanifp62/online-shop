<aside class="drawer cart-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="cart-title" tabindex="-1">
    <div class="drawer-header"><div><span class="eyebrow">{{ __('shop_ui.cart.catalog') }}</span><h2 id="cart-title">{{ __('shop_ui.cart.title') }} <small class="cart-count-text">(0)</small></h2></div><button class="icon-button" type="button" data-close-cart aria-label="{{ __('shop_ui.cart.close') }}"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
    <p>{{ __('shop_ui.cart.notice') }}</p>
    <p data-cart-status role="status" aria-live="polite">{{ __('shop_ui.cart.loading') }}</p>
    <details data-legacy-cart hidden><summary>{{ __('shop_ui.cart.legacy_title') }}</summary><p>{{ __('shop_ui.cart.legacy_notice') }}</p><ul data-legacy-lines></ul><button class="text-link" type="button" data-open-concierge>{{ __('shop_ui.cart.legacy_consult') }}</button></details>
    <div class="cart-body" data-cart-items></div>
    <div class="cart-empty" data-cart-empty hidden><span class="empty-gem"></span><h3>{{ __('shop_ui.cart.empty') }}</h3><p>{{ __('shop_ui.cart.empty_hint') }}</p><button class="button button-dark" type="button" data-close-cart>{{ __('shop_ui.cart.explore') }}</button></div>
    <div class="cart-footer" data-cart-footer hidden><div class="cart-total"><span>{{ __('shop_ui.cart.subtotal') }}</span><strong data-cart-total translate="no">Rp0</strong></div><a class="button button-sapphire" href="{{ route('shop.index') }}">{{ __('shop_ui.cart.continue') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a></div>
</aside>
