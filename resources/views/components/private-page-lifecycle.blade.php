<script>
    (() => {
        // BFCache can retain no-store documents. Conceal the entire shell, not only React's root.
        window.addEventListener('pagehide', () => {
            document.documentElement.style.visibility = 'hidden';
            window.dispatchEvent(new Event('zafira:private-leave'));
        });
        window.addEventListener('pageshow', (event) => {
            // A fresh request rechecks authentication/permissions before showing privileged content.
            if (event.persisted) window.location.reload();
        });
    })();
</script>
