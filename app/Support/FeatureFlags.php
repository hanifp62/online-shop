<?php

namespace App\Support;

/**
 * Pemilih fitur deterministik berbasis konfigurasi.
 *
 * Cohort dihitung dari hash CRC32 atas `<flag>:<subject>`, diambil modulo 100,
 * lalu dibandingkan dengan `rollout` (0–100). Hash CRC32 tidak aman secara
 * kriptografis, tetapi cukup untuk membagi pengunjung menjadi bucket yang stabil
 * dan tidak bias — nilai ini tidak pernah dipakai untuk keamanan atau otorisasi.
 */
class FeatureFlags
{
    /**
     * @param  non-empty-string  $subject  ID stabil per pengguna/sesi (mis. id admin), bukan input bebas.
     */
    public function enabled(string $flag, string $subject): bool
    {
        $flagConfig = config("features.{$flag}");

        if (! is_array($flagConfig)) {
            return false;
        }

        $enabled = $flagConfig['enabled'] ?? false;
        if (filter_var($enabled, FILTER_VALIDATE_BOOL) === false) {
            return false;
        }

        // Nilai di luar 0–100 atau bukan bilangan bulat = salah konfigurasi: gagal tertutup.
        $rollout = filter_var($flagConfig['rollout'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($rollout === false || $rollout === 0) {
            return false;
        }
        if ($rollout === 100) {
            return true;
        }

        return $this->bucket($flag, $subject) < $rollout;
    }

    /**
     * Bucket deterministik 0–99.
     */
    public function bucket(string $flag, string $subject): int
    {
        return crc32($flag.':'.$subject) % 100;
    }
}
