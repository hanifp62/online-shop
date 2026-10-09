<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $primaryKey = 'product_id';

    protected $fillable = [
        'product_cat',
        'product_brand',
        'product_title',
        'product_slug',
        'product_price',
        'product_image',
        'product_badge',
        'product_color',
        'product_alt',
        'product_description',
        'product_material',
        'product_composition',
        'product_origin',
        'product_care',
        'product_craft',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'product_cat',
            'cat_id'
        );
    }

    public function imageWidth(int $width): string
    {
        if (! $this->product_image) {
            return '';
        }

        return preg_replace('/w=\d+/', 'w='.$width, $this->product_image);
    }

    public function swatchHex(): string
    {
        $color = mb_strtolower((string) $this->product_color);

        return match (true) {
            str_contains($color, 'noir') || str_contains($color, 'black') || str_contains($color, 'ebene') || str_contains($color, 'ébène') => '#1c1b1a',
            str_contains($color, 'crème') || str_contains($color, 'creme') || str_contains($color, 'sand') || str_contains($color, 'gold') || str_contains($color, 'or') => '#c9bba8',
            str_contains($color, 'sapphire') || str_contains($color, 'saphir') || str_contains($color, 'bleu') || str_contains($color, 'blue') => '#263a5d',
            str_contains($color, 'espresso') || str_contains($color, 'tobacco') || str_contains($color, 'brown') || str_contains($color, 'brun') => '#72513c',
            default => '#4a5568',
        };
    }
}
