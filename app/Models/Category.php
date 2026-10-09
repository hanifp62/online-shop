<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $primaryKey = 'cat_id';

    protected $fillable = [
        'cat_title',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_cat', 'cat_id');
    }

    /**
     * Setara portable procedure dump `getcat(cid)`:
     *   SELECT * FROM categories WHERE cat_id = cid
     */
    public static function getById(int $cid): ?self
    {
        return self::where('cat_id', $cid)->first();
    }
}
