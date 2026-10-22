<?php

namespace App\Support;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class StorefrontCacheReclaimer
{
    /** Delete at most one batch, including expired generations nobody reads again. */
    public static function pruneExpired(int $limit = 100, ?string $storeName = null): int
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Storefront reclamation batch must be between 1 and 1000.');
        }

        $storeName ??= config('cache.default');
        $store = Cache::store($storeName)->getStore();
        if (! $store instanceof DatabaseStore) {
            return 0;
        }

        $connection = $store->getConnection();
        $query = $connection->table(config("cache.stores.{$storeName}.table", 'cache'))
            ->where('expiration', '<=', now()->timestamp)
            ->where(function ($query) use ($store, $connection): void {
                $key = $connection->getQueryGrammar()->wrap('key');
                foreach (['storefront:products:', 'storefront:categories:', 'storefront:related:'] as $family) {
                    $prefix = $store->getPrefix().$family;
                    // Literal prefix comparison: cache prefixes may contain LIKE wildcards.
                    $query->orWhereRaw("substr({$key}, 1, ?) = ?", [strlen($prefix), $prefix]);
                }
            });
        $keys = (clone $query)->orderBy('key')->limit($limit)->pluck('key')->all();
        if ($keys === []) {
            return 0;
        }

        // Recheck expiration in case a selected key was refreshed concurrently.
        return $query->whereIn('key', $keys)->delete();
    }
}
