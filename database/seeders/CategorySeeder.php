<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['cat_title' => 'Busana Wanita'],
            ['cat_title' => 'Busana Pria'],
            ['cat_title' => 'Tas Tangan'],
            ['cat_title' => 'Sepatu'],
            ['cat_title' => 'Aksesori'],
            ['cat_title' => 'Perhiasan'],
            ['cat_title' => 'Warisan Nusantara'],
            ['cat_title' => 'Koleksi Kapsul'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate($category);
        }
    }
}
