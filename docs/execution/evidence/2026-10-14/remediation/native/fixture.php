<?php

// Run inside isolated checkout using: php artisan tinker --execute='require base_path("scripts/manual/fixture.php");'
$root = realpath(base_path());
$identity = json_decode(file_get_contents($root.'/identity.json'), true, flags: JSON_THROW_ON_ERROR);
$db = DB::connection();
if (! str_starts_with($root, '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-')
    || $identity !== ['root' => $root, 'database' => $root.'/database.sqlite']
    || $db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== $root.'/database.sqlite') {
    throw new RuntimeException('Manual fixture refused: isolated identity mismatch.');
}
$db->transaction(function () use ($db, $root) {
    $category = $db->table('categories')->where('cat_title', 'MANUAL — Synthetic')->first();
    if (! $category) {
        $categoryId = $db->table('categories')->insertGetId([
            'cat_title' => 'MANUAL — Synthetic', 'created_at' => now(), 'updated_at' => now(),
        ], 'cat_id');
    } else {
        $categoryId = $category->cat_id;
    }
    $product = $db->table('products')->where('product_slug', 'manual-synthetic-sapphire')->first();
    if (! $product) {
        $db->table('products')->insert([
            'product_cat' => $categoryId, 'product_brand' => 'ZAFIRA MANUAL',
            'product_title' => 'MANUAL — Synthetic Sapphire', 'product_slug' => 'manual-synthetic-sapphire',
            'product_price' => 125000, 'stock' => 20, 'is_active' => true,
            'product_image' => null, 'product_alt' => 'Produk sintetis tanpa foto nyata.',
            'product_description' => 'Produk fiktif untuk uji manual; bukan inventori nyata.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    } elseif ($product->product_cat != $categoryId || $product->product_brand !== 'ZAFIRA MANUAL') {
        throw new RuntimeException('Manual fixture collision: product ownership mismatch.');
    }
    $credentials = $root.'/private-runtime/credentials.json';
    if (! is_file($credentials)) {
        $customerPassword = bin2hex(random_bytes(16));
        $adminPassword = bin2hex(random_bytes(16));
        $db->table('users')->insert([
            'name' => 'Manual Synthetic Customer', 'email' => 'manual-customer@example.test',
            'password' => Hash::make($customerPassword), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('admins')->insert([
            'name' => 'Manual Synthetic Admin', 'email' => 'manual-admin@example.test',
            'password' => Hash::make($adminPassword), 'created_at' => now(), 'updated_at' => now(),
        ]);
        file_put_contents($credentials, json_encode([
            'customer' => ['email' => 'manual-customer@example.test', 'password' => $customerPassword],
            'admin' => ['email' => 'manual-admin@example.test', 'password' => $adminPassword],
        ], JSON_THROW_ON_ERROR));
        chmod($credentials, 0600);
    }
});
echo "Synthetic fixture ready; existing orders and inventory untouched.\n";
