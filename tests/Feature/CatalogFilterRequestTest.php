<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFilterRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_sort_is_accepted_and_unknown_sort_is_dropped(): void
    {
        $this->get('/koleksi?urut=tersedia')->assertOk()
            ->assertViewHas('filters', fn ($f) => ($f['urut'] ?? null) === 'tersedia');
        $this->get('/koleksi?urut=stock')->assertOk()
            ->assertViewHas('filters', fn ($f) => ! isset($f['urut']));
    }
}
