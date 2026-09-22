<?php

namespace App\Console\Commands;

use App\Support\CsvReviewImporter;
use Illuminate\Console\Command;

class ImportCsvArtifacts extends Command
{
    protected $signature = 'artifacts:import
        {--file= : jalur ke berkas CSV (default: arknights_endfield_reviews2.csv di root proyek)}
        {--top=200 : jumlah kata teratas untuk words.json}';

    protected $description = 'Mengimpor ulasan CSV menjadi artefak nyata (proxy rating) dan menimpanya';

    public function handle(CsvReviewImporter $importer): int
    {
        $path = $this->option('file') ?: base_path('arknights_endfield_reviews2.csv');

        try {
            $summary = $importer->import($path, (int) $this->option('top'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Artefak ditulis ke '.config('artifacts.path'));
        $this->components->info('Sumber: '.basename($path));
        $this->line(sprintf(
            '  raw=%d included=%d (positif %d, negatif %d)',
            $summary['raw'],
            $summary['included'],
            $summary['class_distribution']['positif'],
            $summary['class_distribution']['negatif'],
        ));

        foreach ($summary['excluded'] as $reason => $count) {
            if ($count > 0) {
                $this->line(sprintf('  excluded#%s=%d', $reason, $count));
            }
        }

        return self::SUCCESS;
    }
}
