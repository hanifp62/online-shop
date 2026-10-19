<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('carts:prune', function () {
    // Keranjang akun tidak pernah kedaluwarsa; hanya keranjang tamu yang dipangkas.
    $deleted = DB::table('carts')->whereNotNull('token_hash')
        ->where(fn ($query) => $query->whereNotNull('consumed_at')->orWhere('expires_at', '<=', now()))
        ->delete();
    $this->info("Pruned {$deleted} guest carts.");
})->purpose('Hapus keranjang tamu yang kedaluwarsa atau sudah digabung');

Schedule::command('carts:prune')->daily();
