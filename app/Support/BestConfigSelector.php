<?php

namespace App\Support;

/**
 * Memilih konfigurasi (alpha, K) terbaik sesuai BL-49.
 *
 * Kriteria: mean macro-F1 tertinggi; tie-break berurutan accuracy lebih tinggi,
 * SD macro-F1 lebih kecil, alpha lebih kecil, K lebih besar.
 */
class BestConfigSelector
{
    /**
     * @param  array<int, array<string, mixed>>  $configs
     * @return array<string, mixed>|null
     */
    public function select(array $configs): ?array
    {
        if ($configs === []) {
            return null;
        }

        $ranked = $configs;
        usort($ranked, fn (array $a, array $b): int => $this->compare($a, $b));

        return $ranked[0];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compare(array $a, array $b): int
    {
        $f1A = $this->macroF1($a);
        $f1B = $this->macroF1($b);

        if ($f1A !== $f1B) {
            return $f1A > $f1B ? -1 : 1;
        }

        $accA = $this->accuracy($a);
        $accB = $this->accuracy($b);

        if ($accA !== $accB) {
            return $accA > $accB ? -1 : 1;
        }

        $stdA = $this->macroF1Std($a);
        $stdB = $this->macroF1Std($b);

        if ($stdA !== $stdB) {
            return $stdA < $stdB ? -1 : 1;
        }

        $alphaA = (float) $a['alpha'];
        $alphaB = (float) $b['alpha'];

        if ($alphaA !== $alphaB) {
            return $alphaA < $alphaB ? -1 : 1;
        }

        $kA = (int) $a['k'];
        $kB = (int) $b['k'];

        return $kB <=> $kA;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function macroF1(array $config): float
    {
        return (float) data_get($config, 'metrics.macro.f1.mean');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function macroF1Std(array $config): float
    {
        return (float) data_get($config, 'metrics.macro.f1.std');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function accuracy(array $config): float
    {
        return (float) data_get($config, 'metrics.accuracy.mean');
    }
}
