# CSP inventory - 2026-10-11 final-regression-2

## Response headers (isolated 8016, file session + file cache)
--- /
HTTP/1.1 200 OK
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
--- /admin/login
HTTP/1.1 200 OK
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
--- /produk
HTTP/1.1 404 Not Found

## Content-Security-Policy present?
NO - header absent on all probed routes

## Inline <script> blocks in Blade views
resources/views/welcome.blade.php: <script type="application/ld+json">
resources/views/welcome.blade.php: <script>
resources/views/welcome.blade.php: <script>
resources/views/products/show.blade.php: <script type="application/ld+json">

## Inline <style> blocks in Blade views
resources/views/welcome.blade.php:49:    <style>
resources/views/admin/dashboard.blade.php:13:    <style>

## Inline style= attributes (count per file)
resources/views/welcome.blade.php:71
resources/views/products/show.blade.php:16
resources/views/auth/register.blade.php:2
resources/views/auth/login.blade.php:2
resources/views/admin/dashboard.blade.php:23
resources/views/admin/login.blade.php:1
resources/views/account/show.blade.php:14

## Inline event handlers (onclick/onchange/etc) in Blade views
resources/views/welcome.blade.php:289:                        <form style="position:relative; min-width:260px; flex:1; max-width:340px;" role="search" aria-label="Cari kreasi dalam katalog" onsubmit="event.preventDefault()">
resources/views/welcome.blade.php:434:                        <button type="button" class="button" id="btn-ribbon-gold" style="min-height:36px; padding:0 18px; background:#b89858; color:#0f1c33; font-weight:700; border-color:#b89858; font-size:10px;" onclick="setWelcomeRibbon('gold')" data-i18n="packaging_ribbon_gold">Pita Emas (Or)</button>
resources/views/welcome.blade.php:435:                        <button type="button" class="button" id="btn-ribbon-sapphire" style="min-height:36px; padding:0 18px; background:#0f1c33; color:#fff; border-color:#0f1c33; font-size:10px;" onclick="setWelcomeRibbon('sapphire')" data-i18n="packaging_ribbon_sapphire">Pita Safir (Saphir)</button>
resources/views/welcome.blade.php:450:                        <button class="wax-seal-badge" id="welcome-wax-seal" type="button" onclick="stampWelcomeWaxSeal()" title="Pratinjau segel lilin" aria-label="Segel lilin ZAFIRA">
resources/views/welcome.blade.php:644:                    <button type="button" class="sound-toggle-pill" id="profile-sound-btn" onclick="if(window.zafiraCore) window.zafiraCore.toggleSound()">
resources/views/welcome.blade.php:662:        <div class="search-inner"><span class="eyebrow">Recherche</span><h2 id="search-title">Apa yang Anda cari?</h2><form class="search-field" role="search" aria-label="Cari produk" onsubmit="event.preventDefault()"><svg aria-hidden="true"><use href="#icon-search"/></svg><label class="sr-only" for="search-input">Cari produk</label><input id="search-input" type="search" data-search-input placeholder="Cari tas, busana, aksesori…" data-i18n-placeholder="search_placeholder" autocomplete="off" spellcheck="false" name="q"><button type="button" data-clear-search>Hapus</button></form><div class="search-suggestions"><span>Pencarian populer</span><button type="button" data-search-term="Tas Tangan">Tas ikonis</button><button type="button" data-search-term="Saphir">Saphir</button><button type="button" data-search-term="Baru">Koleksi terbaru</button></div><div class="search-results" data-search-results></div></div>
resources/views/welcome.blade.php:690:        <div class="modal-window" style="background:var(--surface, #fff); max-width:440px; width:90%; padding:28px; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.3); border:1px solid var(--line);" onclick="event.stopPropagation()">
resources/views/admin/dashboard.blade.php:122:                        <form method="POST" action="{{ route('admin.categories.destroy', $category->cat_id) }}" onsubmit="return confirm(@js('Hapus kategori '.$category->cat_title.'?'))">
resources/views/admin/dashboard.blade.php:186:                        <form method="POST" action="{{ route('admin.products.destroy', $product->product_id) }}" onsubmit="return confirm(@js('Hapus produk '.$product->product_title.'?'))">
resources/views/admin/dashboard.blade.php:315:                            <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber->id) }}" onsubmit="return confirm(@js('Hapus subscriber '.$subscriber->email.'?'))">
resources/views/partials/product-footer.blade.php:20:    <div class="search-inner"><span class="eyebrow">Recherche</span><h2 id="search-title-pdp">Apa yang Anda cari?</h2><form class="search-field" role="search" aria-label="Cari produk" onsubmit="event.preventDefault()"><svg aria-hidden="true"><use href="#icon-search"/></svg><label class="sr-only" for="search-input-pdp">Cari produk</label><input id="search-input-pdp" type="search" data-search-input placeholder="Cari tas, busana, aksesori…" autocomplete="off" spellcheck="false" name="q"><button type="button" data-clear-search>Hapus</button></form><div class="search-suggestions"><span>Pencarian populer</span><button type="button" data-search-term="Tas Tangan">Tas ikonis</button><button type="button" data-search-term="Saphir">Saphir</button><button type="button" data-search-term="Baru">Koleksi terbaru</button></div><div class="search-results" data-search-results></div></div>

## Verification status
NOT RUN - no Content-Security-Policy-Report-Only header is deployed on the isolated environment; enforcement/report verification cannot be claimed.

## 8015 probe (original array-cache router)
--- /
CSP absent
--- /admin/login
CSP absent
