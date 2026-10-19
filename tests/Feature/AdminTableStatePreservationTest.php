<?php

namespace Tests\Feature;

use App\Models\Admin;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Setiap form GET tabel admin wajib membawa state 25–26 kunci tabel lain sebagai hidden input,
 * tanpa menduplikasi kunci miliknya sendiri yang sudah punya kontrol terlihat.
 */
class AdminTableStatePreservationTest extends TestCase
{
    use RefreshDatabase;

    /** Seluruh 30 kunci allow-list $tableState dengan nilai valid menurut validasi controller. */
    private const STATE = [
        'q' => 'tamu', 'status' => 'contacted', 'csort' => 'email', 'cdir' => 'asc', 'cpage' => '2',
        'uq' => 'anggota', 'usort' => 'email', 'udir' => 'asc', 'upage' => '3',
        'kq' => 'koleksi', 'ksort' => 'cat_title', 'kdir' => 'desc', 'kpage' => '4',
        'pq' => 'sac', 'psort' => 'stock', 'pdir' => 'asc', 'ppage' => '5',
        'oq' => '1024', 'osort' => 'total', 'odir' => 'asc', 'opage' => '6', 'ostatus' => 'shipped',
        'bq' => 'arsip', 'bsort' => 'user_id', 'bdir' => 'asc', 'bpage' => '7',
        'sq' => 'lettre', 'ssort' => 'email', 'sdir' => 'asc', 'spage' => '1000',
    ];

    /** table => [kunci milik tabel yang punya kontrol terlihat, kunci halaman milik tabel]. */
    public static function tables(): array
    {
        return [
            'kategori' => ['kategori', ['kq', 'ksort', 'kdir'], 'kpage'],
            'produk' => ['produk', ['pq', 'psort', 'pdir'], 'ppage'],
            'pesanan' => ['pesanan', ['oq', 'osort', 'odir', 'ostatus'], 'opage'],
            'user' => ['user', ['uq', 'usort', 'udir'], 'upage'],
            'audit' => ['audit', ['bq', 'bsort', 'bdir'], 'bpage'],
            'concierge' => ['concierge', ['status', 'q', 'csort', 'cdir'], 'cpage'],
            'subscriber' => ['subscriber', ['sq', 'ssort', 'sdir'], 'spage'],
        ];
    }

    #[DataProvider('tables')]
    public function test_form_preserves_exactly_other_tables_state(string $table, array $visibleOwn, string $pageKey): void
    {
        $this->assertCount(30, self::STATE, 'fixture harus memuat 30 kunci allow-list');
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');

        $html = $this->get(route('admin.dashboard', self::STATE))->assertOk()->getContent();

        $xpath = $this->xpath($html);
        $this->assertSame(7, $xpath->query('//form[@data-admin-table-form]')->length, 'tepat tujuh form tabel');
        $forms = $xpath->query('//form[@data-admin-table-form="'.$table.'"]');
        $this->assertSame(1, $forms->length, $table.' tepat satu form');
        $form = $forms->item(0);
        $this->assertSame('get', strtolower($form->getAttribute('method')), $table.' form GET');
        $this->assertSame(route('admin.dashboard').'#panel-'.$table, $form->getAttribute('action'), $table.' form action');
        foreach ($visibleOwn as $name) {
            $controls = $xpath->query('.//*[@name="'.$name.'"]', $form);
            $this->assertSame(1, $controls->length, $table.' '.$name.' control');
            $control = $controls->item(0);
            if ($control->nodeName === 'select') {
                $selected = $xpath->query('.//option[@selected]', $control);
                $this->assertSame(1, $selected->length, $table.' '.$name.' selected option');
                $this->assertSame(self::STATE[$name], $selected->item(0)->getAttribute('value'));
            } else {
                $this->assertSame(self::STATE[$name], $control->getAttribute('value'));
            }
        }

        $hidden = [];
        foreach ($xpath->query('.//input[@type="hidden"][@name]', $form) as $input) {
            /** @var DOMElement $input */
            $name = $input->getAttribute('name');
            $this->assertArrayNotHasKey($name, $hidden, $table.' hidden ganda: '.$name);
            $hidden[$name] = $input->getAttribute('value');
        }
        $visible = [];
        foreach ($xpath->query('.//*[self::input[not(@type="hidden")] or self::select or self::textarea][@name]', $form) as $control) {
            /** @var DOMElement $control */
            $visible[] = $control->getAttribute('name');
        }

        $own = [...$visibleOwn, $pageKey];
        $expected = array_diff_key(self::STATE, array_flip($own));
        ksort($expected);
        ksort($hidden);

        // (a) hidden = 30 kunci dikurangi kunci milik tabel, dengan nilai permintaan.
        $this->assertSame($expected, $hidden, $table.' hidden state');
        // (b) tidak ada nama yang muncul sebagai hidden sekaligus kontrol terlihat.
        $this->assertSame([], array_values(array_intersect(array_keys($hidden), $visible)), $table.' hidden vs terlihat');
        // (c) kunci milik tabel punya tepat satu kontrol terlihat dan tidak dirender hidden.
        $this->assertEqualsCanonicalizing($visibleOwn, $visible, $table.' kontrol terlihat');
        // Kunci halaman milik tabel tidak boleh ada di form sama sekali (submit = kembali ke halaman 1).
        $this->assertSame(0, $xpath->query('.//*[@name="'.$pageKey.'"]', $form)->length, $table.' '.$pageKey.' absen');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }
}
