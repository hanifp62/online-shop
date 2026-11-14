<?php

namespace App\Services;

use App\Models\Product;
use App\Support\StorefrontPageProps;
use Illuminate\Support\Collection;

/**
 * FR-402.3: product search for the guided assistant.
 *
 * The catalogue page matches one whole phrase; shoppers type "tas sutra biru".
 * Here every meaningful word must match (accent-insensitive, one typo allowed),
 * Indonesian/English words map to the French/Italian titles, and stock is read live.
 */
class AssistantProductSearch
{
    public const LIMIT = 4;

    // Safety cap, as in CatalogService: matching runs in PHP over active products.
    public const CANDIDATE_CAP = 500;

    private const STOPWORDS = [
        'ada', 'adakah', 'apa', 'apakah', 'yang', 'yg', 'untuk', 'buat', 'saya', 'aku', 'mau', 'ingin', 'pengen', 'cari', 'carikan', 'mencari',
        'tolong', 'dong', 'ya', 'ini', 'itu', 'dengan', 'dan', 'atau', 'di', 'ke', 'dari', 'punya', 'rekomendasi', 'rekomendasikan',
        'tampilkan', 'lihat', 'minta', 'warna', 'bahan', 'model', 'koleksi', 'produk', 'barang', 'jual', 'kah', 'nya', 'sih', 'tangan',
        'a', 'an', 'the', 'for', 'me', 'show', 'find', 'looking', 'some', 'any', 'do', 'you', 'have', 'i', 'want', 'recommend', 'with',
        'in', 'of', 'color', 'colour', 'material', 'product', 'products', 'please', 'is', 'there',
    ];

    private const SYNONYMS = [
        'tas' => ['tas', 'sac', 'minaudiere', 'bag'], 'bag' => ['tas', 'sac', 'minaudiere'], 'handbag' => ['tas', 'sac'], 'clutch' => ['minaudiere', 'clutch'],
        'gaun' => ['robe', 'dress', 'gaun'], 'dress' => ['robe', 'dress'], 'rok' => ['robe'],
        'baju' => ['busana'], 'pakaian' => ['busana'], 'clothing' => ['busana'], 'wanita' => ['wanita'], 'women' => ['wanita'], 'pria' => ['pria'], 'men' => ['pria'],
        'sepatu' => ['sepatu', 'mocassin'], 'shoes' => ['sepatu', 'mocassin'], 'shoe' => ['sepatu', 'mocassin'], 'loafer' => ['mocassin'],
        'jam' => ['montre'], 'watch' => ['montre'], 'perhiasan' => ['perhiasan', 'montre'], 'jewelry' => ['perhiasan'],
        'syal' => ['carre', 'echarpe'], 'scarf' => ['carre', 'echarpe'], 'selendang' => ['echarpe', 'carre'], 'selempang' => ['echarpe'],
        'kemeja' => ['chemise'], 'shirt' => ['chemise'], 'mantel' => ['manteau', 'trench'], 'coat' => ['manteau', 'trench'], 'jaket' => ['manteau', 'trench'],
        'sutra' => ['sutra', 'silk'], 'silk' => ['sutra'], 'kulit' => ['kulit', 'leather'], 'leather' => ['kulit'], 'wool' => ['wol'], 'katun' => ['katun'], 'cotton' => ['katun'],
        'biru' => ['blue', 'bleu', 'sapphire', 'saphir', 'indigo'], 'blue' => ['blue', 'bleu', 'sapphire', 'indigo'], 'emas' => ['or', 'emas', 'gold'], 'gold' => ['or', 'emas'],
        'coklat' => ['cognac', 'espresso', 'tobacco'], 'cokelat' => ['cognac', 'espresso', 'tobacco'], 'brown' => ['cognac', 'espresso', 'tobacco'],
        'putih' => ['ivoire'], 'white' => ['ivoire'],
    ];

    public function __construct(private readonly CatalogSearchMatcher $matcher) {}

    /** @return array{terms: list<string>, items: list<array>, total: int, partial: bool} */
    public function search(string $query): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return ['terms' => [], 'items' => [], 'total' => 0, 'partial' => false];
        }

        $products = Product::query()->with('category:cat_id,cat_title')->where('is_active', true)
            ->orderBy('product_id')->limit(self::CANDIDATE_CAP)->get();

        $scored = $products->map(fn (Product $product) => [$product, $this->score($product, $terms)]);
        $all = $scored->filter(fn ($row) => $row[1]['matched'] === count($terms));
        $partial = false;
        if ($all->isEmpty() && count($terms) > 1) {
            // Nothing matches every word: show the closest items and say so.
            $all = $scored->filter(fn ($row) => $row[1]['matched'] > 0);
            $partial = $all->isNotEmpty();
        }

        $ranked = $all->sortBy([
            fn ($a, $b) => $b[1]['matched'] <=> $a[1]['matched'],
            fn ($a, $b) => $b[1]['weight'] <=> $a[1]['weight'],
            fn ($a, $b) => ((int) ($b[0]->stock > 0)) <=> ((int) ($a[0]->stock > 0)),
            fn ($a, $b) => $a[0]->product_id <=> $b[0]->product_id,
        ])->values();

        return [
            'terms' => $terms,
            'items' => $ranked->take(self::LIMIT)->map(fn ($row) => $this->item($row[0]))->all(),
            'total' => $ranked->count(),
            'partial' => $partial,
        ];
    }

    /** @return list<string> */
    public function terms(string $query): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $this->fold($query), $words);

        return Collection::make($words[0])
            ->map(fn ($word) => mb_strlen($word) > 5 && str_ends_with($word, 'nya') ? mb_substr($word, 0, -3) : $word)
            ->reject(fn ($word) => mb_strlen($word) < 2 || in_array($word, self::STOPWORDS, true))
            ->unique()->take(5)->values()->all();
    }

    private function score(Product $product, array $terms): array
    {
        $fields = [
            3 => $this->fold($product->product_title),
            2 => $this->fold($product->category?->cat_title ?? ''),
            1 => $this->fold(implode(' ', [$product->product_material, $product->product_color, $product->product_composition, $product->product_origin])),
        ];
        $matched = 0;
        $weight = 0;
        foreach ($terms as $term) {
            $best = 0;
            foreach (self::SYNONYMS[$term] ?? [$term] as $variant) {
                foreach ($fields as $fieldWeight => $text) {
                    if ($fieldWeight > $best && $this->contains($text, $variant)) {
                        $best = $fieldWeight;
                    }
                }
            }
            if ($best > 0) {
                $matched++;
                $weight += $best;
            }
        }

        return ['matched' => $matched, 'weight' => $weight];
    }

    private function contains(string $text, string $term): bool
    {
        // Short words must match whole ("or" is gold, not "organza"); longer ones may be a prefix.
        $end = mb_strlen($term) < 4 ? '(?![\p{L}\p{N}])' : '';
        if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').$end.'/u', $text) === 1) {
            return true;
        }

        return $this->matcher->eligible($term) && $this->matcher->matches($term, $text);
    }

    private function fold(?string $value): string
    {
        $decomposed = normalizer_normalize((string) $value, \Normalizer::FORM_D);

        return mb_strtolower(preg_replace('/\p{Mn}+/u', '', $decomposed !== false ? $decomposed : (string) $value));
    }

    public function card(Product $product): array
    {
        $props = StorefrontPageProps::product($product);

        return [
            'id' => $props['id'], 'productId' => $props['productId'], 'name' => $props['name'],
            'category' => $props['category'], 'priceLabel' => $props['priceLabel'],
            'available' => $props['stock'] > 0, 'image' => $props['image'], 'alt' => $props['alt'],
            'href' => $props['href'], 'hasSizes' => $props['hasSizes'],
        ];
    }

    private function item(Product $product): array
    {
        return $this->card($product);
    }
}
