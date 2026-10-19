<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Run only through launch.sh artisan tinker --execute='require base_path("scripts/amadis/pdf-ui-fixture.php");'.
require_once __DIR__.'/guard.php';
$root = amadisGuard();
$db = DB::connection();
if ($db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== $root.'/database.sqlite'
    || $db->table('orders')->exists() || ! is_file($root.'/private-runtime/credentials.json')) {
    throw new RuntimeException('PDF fixture requires a fresh provisioned isolated root without orders.');
}
$names = ['王小明', 'Madame Élodie de Longueville',
    'Yang Mulia Putri Cantika Maharani Sasikirana Kusumaningrat dari Keraton Surakarta', '   '];
$rows = $db->transaction(function () use ($names) {
    $users = [];
    foreach ($names as $i => $name) {
        $users[] = User::create(['name' => $name, 'email' => "pdf-fiction-$i@example.test", 'password' => bin2hex(random_bytes(32))]);
    }
    $rows = [];
    for ($i = 1; $i <= 48; $i++) {
        $user = $users[($i - 1) % 4];
        $total = 100000 + $i * 1000;
        $order = Order::create([
            'user_id' => $user->id, 'status' => $i % 6 === 0 ? 'cancelled' : 'pending',
            'subtotal' => $total, 'discount' => 0, 'shipping_fee' => 0, 'total' => $total,
            'idempotency_key' => 'pdf-ui-fiction-'.$i, 'payload_hash' => str_repeat('a', 64),
            'shipping_address' => ['line1' => 'Alamat Fiktif', 'city' => 'Kota Fiktif', 'postal_code' => '00000'],
        ]);
        $date = sprintf('2026-10-13 08:%02d:00', $i);
        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        $rows[] = ['id' => $order->id, 'user_id' => $user->id, 'name' => $user->name, 'created_at_utc' => $date,
            'status' => $order->status, 'total' => $total];
    }

    return $rows;
});
echo json_encode(['provenance' => 'Fictional PDF UI fixture; not operational transactions', 'generated_at_utc' => gmdate('c'),
    'count' => count($rows), 'non_cancelled_total' => array_sum(array_column(array_filter($rows, fn ($r) => $r['status'] !== 'cancelled'), 'total')),
    'orders' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
