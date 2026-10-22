<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Explicit driver allowlist; Laravel's generic concurrency retry also includes MySQL 1205. */
final class TransactionLockRetry
{
    public static function retryable(Throwable $exception, string $driver): bool
    {
        $allowed = false;
        do {
            $info = $exception instanceof QueryException || $exception instanceof \PDOException
                ? (array) ($exception->errorInfo ?? []) : [];
            $code = (int) ($info[1] ?? 0);
            if ($driver === 'mysql' && $code === 1205) {
                return false;
            }
            $allowed = $allowed || match ($driver) {
                'mysql' => ($info[0] ?? null) === '40001' && $code === 1213,
                'sqlite' => in_array($code, [5, 6], true),
                default => false,
            };
        } while ($exception = $exception->getPrevious());

        return $allowed;
    }

    public static function transaction(callable $operation, int $attempts = 3): mixed
    {
        $nested = DB::transactionLevel() > 0;
        for ($attempt = 1; ; $attempt++) {
            try {
                // Never delegate the retry policy to the framework's wider classifier.
                return DB::transaction($operation, 1);
            } catch (Throwable $exception) {
                if ($nested || $attempt >= $attempts || ! self::retryable($exception, DB::getDriverName())) {
                    throw $exception;
                }
                usleep(25000 * $attempt);
            }
        }
    }
}
