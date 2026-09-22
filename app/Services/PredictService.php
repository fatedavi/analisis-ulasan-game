<?php

namespace App\Services;

use App\Support\LexiconClassifier;

/**
 * Prediksi sentimen demo berbasis leksikon kata kunci Indonesia.
 *
 * BUKAN model Multinomial Naive Bayes. Ini hanya pameran alur PRD BL-61–BL-64
 * agar UI bisa diuji sebelum artefak/model nyata tersedia. Respons memakai
 * bentuk yang sama dengan POST /api/predict PRD §8, ditandai "demo", dan
 * berbagi kamus dengan LexiconClassifier (sumber prediksi artefak).
 */
class PredictService
{
    public function __construct(private readonly LexiconClassifier $classifier) {}

    /**
     * Klasifikasi satu ulasan.
     *
     * @return array<string, mixed>|null null bila tidak ada token valid (BL-61)
     */
    public function classify(string $text): ?array
    {
        $tokens = preg_split('/[^a-zA-Z]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) > 0));

        if ($tokens === []) {
            return null;
        }

        $score = $this->classifier->classify($text);

        if ($score === null) {
            return null;
        }

        $probaPos = $score['proba_pos'];

        return [
            'label' => $score['label'],
            'proba' => [
                'negatif' => round(1 - $probaPos, 4),
                'positif' => round($probaPos, 4),
            ],
            'text_final' => implode(' ', $tokens),
            'demo' => true,
            'note' => $score['detected']
                ? 'Prediksi demo berbasis kata kunci, bukan model Naive Bayes.'
                : 'Tidak ada kata kunci terdeteksi; prediksi mengikuti asumsi netral (50%).',
        ];
    }
}
