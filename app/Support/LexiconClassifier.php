<?php

namespace App\Support;

/**
 * Klasifikasi sentimen berbasis leksikon kata kunci Indonesia + slang game.
 *
 * Satu-satunya sumber prediksi berbasis teks di aplikasi: dipakai oleh
 * PredictService (demo /api/predict) dan CsvReviewImporter (label_pred di
 * artefak). `detected=false` menandakan tidak ada kata kunci yang cocok, lalu
 * pemanggil menerapkan fallback (mis. ikuti rating dengan keyakinan rendah).
 */
class LexiconClassifier
{
    /** @var array<int, string> */
    private const POSITIVE = [
        'bagus', 'mantap', 'mantab', 'keren', 'seru', 'asyik', 'asik', 'suka',
        'senang', 'menarik', 'mantul', 'kece', 'gacor', 'nyaman', 'indah',
        'lancar', 'mulus', 'terbaik', 'tergila', 'rekomendasi', 'recommend',
        'recommended', 'recomended', 'top', 'best', 'great', 'good', 'nice',
        'wow', 'luar', 'ok', 'oke', 'sip', 'optimal', 'kualitas', 'wajib',
        'candu', 'bikin', 'ketagihan', 'sepuas', 'memuaskan', 'puas', 'bangga',
        'favorit', 'juara', 'lengkap', 'presisi', 'halus', 'yes', 'mantep',
        'kerenn', 'kerennn', 'cakep', 'gokil', 'nagih', 'smooth', 'epic',
        'perfect', 'amazing', 'sempurna', 'memukau', 'menyenangkan', 'seneng',
        'bersyukur', 'memanjakan', 'baguss', 'bgus', 'bagusss', 'anjay',
        'sukses', 'masterpiece', 'enak',
    ];

    /** @var array<int, string> */
    private const NEGATIVE = [
        'buruk', 'jelek', 'parah', 'payah', 'sampah', 'busuk', 'benci', 'bodoh',
        'tolol', 'dongo', 'kesal', 'jengkel', 'kecewa', 'mengecewakan', 'muak',
        'lag', 'lagging', 'error', 'crash', 'bug', 'force', 'close', 'stuck',
        'banned', 'hang', 'ribet', 'susah', 'sulit', 'bosan', 'membosankan',
        'menyesal', 'frustasi', 'bangkrut', 'greedy', 'mahal', 'nguras', 'boros',
        'kuota', 'buang', 'pay', 'worst', 'bad', 'terrible', 'trash', 'sucks',
        'jeblok', 'lemah', 'lambat', 'kun', 'scam', 'ngulang', 'ngulangin',
        'keulang', 'keulangin', 'ulang', 'diulang', 'mengulang', 'reset',
        'kereset', 'malah', 'sia', 'habis', 'muak', 'males', 'malas', 'capek',
        'cape', 'kesel', 'ngeselin', 'burik', 'pelit', 'gagal', 'rusak',
        'melelahkan', 'bermasalah', 'nyesel',
    ];

    /** @var array<int, string> */
    private const NEGATORS = [
        'tidak', 'tak', 'gak', 'ga', 'nggak', 'enggak', 'bukan', 'belum',
        'jangan', 'tanpa', 'kurang', 'kagak', 'ogah',
    ];

    /**
     * Klasifikasi satu teks ulasan.
     *
     * @return array{label: string, proba_pos: float, detected: bool}|null
     *                                                                     null bila teks tidak memiliki token valid
     */
    public function classify(string $text): ?array
    {
        $tokens = preg_split('/[^a-zA-Z]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));

        if ($tokens === []) {
            return null;
        }

        $positiveHits = 0;
        $negativeHits = 0;
        $negated = false;

        foreach ($tokens as $token) {
            if (in_array($token, self::NEGATORS, true)) {
                $negated = true;

                continue;
            }

            if ($negated) {
                if (in_array($token, self::POSITIVE, true)) {
                    $negativeHits++;
                } elseif (in_array($token, self::NEGATIVE, true)) {
                    $positiveHits++;
                }

                $negated = false;

                continue;
            }

            if (in_array($token, self::POSITIVE, true)) {
                $positiveHits++;
            } elseif (in_array($token, self::NEGATIVE, true)) {
                $negativeHits++;
            }
        }

        $totalHits = $positiveHits + $negativeHits;
        $detected = $totalHits > 0;
        $probaPos = $totalHits > 0 ? ($positiveHits + 0.5) / ($totalHits + 1) : 0.5;

        return [
            'label' => $probaPos >= 0.5 ? 'positif' : 'negatif',
            'proba_pos' => round($probaPos, 4),
            'detected' => $detected,
        ];
    }
}
