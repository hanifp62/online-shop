<?php

namespace App\Mail;

final class RecipientLocale
{
    public static function resolve(mixed $locale): string
    {
        return is_string($locale) && in_array($locale, config('app.supported_locales', ['id']), true)
            ? $locale
            : 'id';
    }
}
