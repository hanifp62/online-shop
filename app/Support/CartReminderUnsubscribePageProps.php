<?php

namespace App\Support;

final class CartReminderUnsubscribePageProps
{
    public static function from(?string $action, bool $done = false): array
    {
        $copy = [];
        foreach (['title', 'heading', 'intro', 'submit', 'shop'] as $key) {
            $copy[$key] = __("account_ui.unsubscribe.$key");
        }

        if ($done) {
            $copy['heading'] = __('account_ui.unsubscribe.done_heading');
            $copy['intro'] = __('account_ui.unsubscribe.done_intro');
        }

        return ['done' => $done, 'copy' => ['page' => $copy, 'common' => ['home' => __('account_ui.common.home')]],
            'urls' => ['home' => route('home'), 'shop' => route('shop.index'), 'submit' => $done ? null : $action]];
    }
}
