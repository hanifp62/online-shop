@props(['page' => null])
@if ($page !== null)
    <script type="application/json" data-page="app">{!! json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <div id="app" data-react-fallback>{{ $slot }}</div>
    <script data-frontend-handoff>
        (() => {
            const root = document.getElementById('app');
            const lifetime = new AbortController();
            const options = { signal: lifetime.signal };
            root.dataset.reactBoot = 'pending';
            // Interaction hands ownership to Blade for this document, including in-flight writes.
            for (const event of ['pointerdown', 'keydown', 'click', 'input', 'change', 'submit']) {
                root.addEventListener(event, () => { root.dataset.reactBoot = 'fallback'; }, { ...options, capture: true });
            }
            root.addEventListener('zafira:react-claim', () => lifetime.abort(), { once: true });
            root.querySelectorAll('[data-password-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = button.closest('.password-wrap')?.querySelector('input');
                    if (!input) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.textContent = show ? button.dataset.i18nHide : button.dataset.i18nShow;
                    button.setAttribute('aria-pressed', String(show));
                    button.setAttribute('aria-label', show ? button.dataset.i18nAriaHide : button.dataset.i18nAriaShow);
                }, options);
            });
            root.querySelectorAll('[data-auth-form]').forEach((form) => {
                let submitting = false;
                const button = form.querySelector('[type="submit"]');
                form.addEventListener('submit', (event) => {
                    if (submitting) { event.preventDefault(); return; }
                    submitting = true;
                    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
                }, options);
                window.addEventListener('pageshow', () => {
                    submitting = false;
                    if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
                }, options);
            });
        })();
    </script>
@else
    {{ $slot }}
@endif
