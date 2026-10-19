<?php

// Run only through scripts/manual/fixture.sh so preflight precedes this Artisan process.
$root = realpath(base_path());
$identity = json_decode(file_get_contents($root.'/identity.json'), true, flags: JSON_THROW_ON_ERROR);
$db = DB::connection();
if (! str_starts_with($root, '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-')
    || $identity !== ['root' => $root, 'database' => $root.'/database.sqlite']
    || $db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== $root.'/database.sqlite') {
    throw new RuntimeException('Manual fixture refused: isolated identity mismatch.');
}
$fail = fn (string $message) => throw new RuntimeException('Manual fixture refused: '.$message);

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="750"><rect width="100%" height="100%" fill="#0f2a5f"/><text x="50%" y="50%" fill="#d4af37" text-anchor="middle" font-size="28">MANUAL FIXTURE</text></svg>';
$image = $root.'/public/manual/sapphire.svg';
if ((! is_dir(dirname($image)) && ! mkdir(dirname($image), 0700, true)) || is_link($image)) {
    $fail('image directory unavailable.');
}
if (! is_file($image) && file_put_contents($image, $svg, LOCK_EX) !== strlen($svg)) {
    $fail('image write failed.');
}
if (file_get_contents($image) !== $svg) {
    $fail('image collision.');
}

// Credentials are durable before accounts: a crash leaves a file that the next run reuses, never resets.
$credentialsPath = $root.'/private-runtime/credentials.json';
$emails = ['customer' => 'manual-customer@example.test', 'admin' => 'manual-admin@example.test'];
if (! is_file($credentialsPath)) {
    if ($db->table('users')->where('email', $emails['customer'])->exists()
        || $db->table('admins')->where('email', $emails['admin'])->exists()) {
        $fail('account exists without its private credential file; inspect manually.');
    }
    $json = json_encode([
        'customer' => ['email' => $emails['customer'], 'password' => bin2hex(random_bytes(16))],
        'admin' => ['email' => $emails['admin'], 'password' => bin2hex(random_bytes(16))],
    ], JSON_THROW_ON_ERROR);
    $tmp = $credentialsPath.'.tmp-'.bin2hex(random_bytes(4));
    $handle = fopen($tmp, 'x');
    if ($handle === false || ! chmod($tmp, 0600) || fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || ! fclose($handle) || ! rename($tmp, $credentialsPath)) {
        @unlink($tmp);
        $fail('credential file write failed; no account created.');
    }
}
if (is_link($credentialsPath) || (fileperms($credentialsPath) & 0077)) {
    $fail('credential file must be a private regular file.');
}
$credentials = json_decode(file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
foreach ($emails as $role => $email) {
    if (! isset($credentials[$role]['email'], $credentials[$role]['password'])
        || $credentials[$role]['email'] !== $email || ! is_string($credentials[$role]['password'])
        || ! preg_match('/\A[0-9a-f]{32}\z/', $credentials[$role]['password'])) {
        $fail('credential file schema mismatch.');
    }
}

$db->transaction(function () use ($db, $credentials, $emails, $fail) {
    $categories = $db->table('categories')->where('cat_title', 'MANUAL — Synthetic')->get();
    if ($categories->count() > 1) {
        $fail('category collision: duplicate titles.');
    }
    $category = $categories->first();
    $categoryId = $category ? $category->cat_id : $db->table('categories')->insertGetId([
        'cat_title' => 'MANUAL — Synthetic', 'created_at' => now(), 'updated_at' => now(),
    ], 'cat_id');
    foreach ([
        ['manual-synthetic-sapphire', 'MANUAL — Synthetic Sapphire', 20, true, '/manual/sapphire.svg'],
        ['manual-synthetic-out', 'MANUAL — Synthetic Habis', 0, true, null],
        ['manual-synthetic-retired', 'MANUAL — Synthetic Pensiun', 5, false, null],
    ] as [$slug, $title, $stock, $active, $imagePath]) {
        $row = $db->table('products')->where('product_slug', $slug)->first();
        if (! $row) {
            $db->table('products')->insert([
                'product_cat' => $categoryId, 'product_brand' => 'ZAFIRA MANUAL',
                'product_title' => $title, 'product_slug' => $slug,
                'product_price' => 125000, 'stock' => $stock, 'is_active' => $active,
                'product_image' => $imagePath, 'product_alt' => 'Ilustrasi sintetis lokal.',
                'product_description' => 'Produk fiktif untuk uji manual; bukan inventori nyata.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } elseif ((int) $row->product_cat !== (int) $categoryId || $row->product_brand !== 'ZAFIRA MANUAL'
            || $row->product_title !== $title || (int) $row->product_price !== 125000
            || (bool) $row->is_active !== $active
            || $row->product_image !== $imagePath || $row->product_alt !== 'Ilustrasi sintetis lokal.'
            || $row->product_description !== 'Produk fiktif untuk uji manual; bukan inventori nyata.') {
            $fail("product collision for {$slug}.");
        }
    }
    foreach (['customer' => 'users', 'admin' => 'admins'] as $role => $table) {
        if ($credentials[$role]['email'] !== $emails[$role]) {
            $fail('credential file identity mismatch.');
        }
        $account = $db->table($table)->where('email', $emails[$role])->first();
        if (! $account) {
            $db->table($table)->insert([
                'name' => 'Manual Synthetic '.ucfirst($role), 'email' => $emails[$role],
                'password' => Hash::make($credentials[$role]['password']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } elseif ($account->name !== 'Manual Synthetic '.ucfirst($role)
            || ! Hash::check($credentials[$role]['password'], $account->password)) {
            $fail("{$role} account collision: password does not match credential file.");
        }
    }
    $customer = $db->table('users')->where('email', $emails['customer'])->first();
    $address = $db->table('addresses')->where('user_id', $customer->id)->where('label', 'MANUAL')->first();
    if (! $address) {
        $db->table('addresses')->insert([
            'user_id' => $customer->id, 'label' => 'MANUAL', 'recipient_name' => 'Manual Synthetic Customer',
            'phone' => '0000000000', 'line1' => 'Jalan Fiktif 1', 'city' => 'Kota Fiktif',
            'province' => 'Provinsi Fiktif', 'postal_code' => '00000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    } elseif ($address->recipient_name !== 'Manual Synthetic Customer' || $address->phone !== '0000000000'
        || $address->line1 !== 'Jalan Fiktif 1' || $address->city !== 'Kota Fiktif'
        || $address->province !== 'Provinsi Fiktif' || $address->postal_code !== '00000') {
        $fail('address collision.');
    }
    $voucher = $db->table('vouchers')->where('code', 'MANUAL-ONLY')->first();
    if (! $voucher) {
        $db->table('vouchers')->insert([
            'code' => 'MANUAL-ONLY', 'discount_amount' => 1000, 'quota' => 20,
            'used' => 0, 'per_user_limit' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    } elseif ((int) $voucher->discount_amount !== 1000 || (int) $voucher->quota !== 20
        || (int) $voucher->per_user_limit !== 1 || ! (bool) $voucher->is_active) {
        $fail('voucher collision.');
    }
});
echo "Synthetic fixture ready; existing orders and inventory untouched.\n";
