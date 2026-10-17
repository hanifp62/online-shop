<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Samakan email ke huruf kecil sebelum validasi.
     * Input non-string dibiarkan utuh supaya aturan validasi yang menolaknya,
     * bukan cast string yang melempar error 500.
     */
    protected function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower($email)]);
        }
    }
}
