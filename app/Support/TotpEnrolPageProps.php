<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** FE-COMP-20261023 SECURITY; FR-FE-001/002, NFR-FE-001/002. */
final class TotpEnrolPageProps
{
    public static function props(bool $admin, bool $recovery = false): array
    {
        $catalog = $admin ? 'admin_ui' : 'account_ui';
        $section = $recovery ? 'recovery' : 'enrol';
        $keys = $recovery
            ? ($admin ? ['title', 'heading', 'intro', 'codes', 'saved'] : ['title', 'heading', 'intro', 'submit'])
            : ($admin ? ['title', 'heading', 'intro', 'qr_alt', 'setup_key', 'uri', 'submit'] : ['title', 'heading', 'intro', 'qr_alt', 'manual', 'submit', 'cancel']);
        $copy = [];
        foreach ($keys as $key) {
            $copy[$key] = __("$catalog.$section.$key");
        }
        $copy['code'] = __($admin ? 'admin_ui.totp.code' : 'account_ui.enrol.code');

        return [
            'copy' => ['page' => $copy, 'common' => $admin ? ['admin_access' => __('admin_ui.common.admin_access'), 'logout' => __('admin_ui.common.logout')] : ['admin_access' => 'Mon Compte']],
            'urls' => [
                'submit' => route($admin ? 'admin.totp.enrol.store' : 'account.totp.store'),
                'exit' => route($admin ? 'admin.dashboard' : 'account.show'),
                'logout' => $admin ? route('admin.logout') : null,
            ],
        ];
    }

    public static function prefetch(Request $request): bool
    {
        return str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '')), 'prefetch')
            || $request->headers->has('X-Inertia-Prefetch');
    }

    public static function recoveryDocumentVisit(Request $request, bool $admin): ?Response
    {
        if (! $request->isMethod('GET') || ! $request->header('X-Inertia')) {
            return null;
        }

        $component = $admin ? 'Admin/TotpRecovery' : 'Account/TotpRecovery';
        $fallback = ! config('frontend.enabled') || $request->query('frontend') === 'blade'
            || ! in_array($component, config('frontend.pages', []), true);
        if (! $fallback && $request->header('X-Inertia-Version', '') === Inertia::getVersion()) {
            return null;
        }

        // The middleware can discard this response after the controller runs; preserve one-time state until the document GET.
        $response = Inertia::location($request->getSchemeAndHttpHost().$request->getRequestUri());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Vary', 'X-Inertia', false);

        return $response;
    }

    public static function render(Request $request, bool $admin, bool $recovery, array $material): Response
    {
        $component = ($admin ? 'Admin/' : 'Account/').($recovery ? 'TotpRecovery' : 'TotpEnrol');
        $view = $admin ? ($recovery ? 'admin.totp-enrol-recovery' : 'admin.totp-enrol') : ($recovery ? 'account.totp-recovery' : 'account.totp-enrol');
        Inertia::encryptHistory();
        Inertia::clearHistory();
        $page = FrontendPage::render($component, $view, $material, self::props($admin, $recovery));
        if ($page instanceof \Inertia\Response) {

            $page->flash('totpMaterial', $material);
            $response = $page->toResponse($request);
        } else {
            $response = response($page);
        }
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Vary', 'X-Inertia', false);

        return $response;
    }
}
