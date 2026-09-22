<?php

namespace App\Console\Commands;

use App\Support\DemoArtifacts;
use App\Support\LexiconClassifier;
use Illuminate\Console\Command;

class GenerateDemoArtifacts extends Command
{
    protected $signature = 'artifacts:demo
        {--force : timpa artefak yang sudah ada}';

    protected $description = 'Membangkitkan artefak demo deterministik (seed 42) untuk preview dashboard';

    public function handle(DemoArtifacts $demo): int
    {
        $path = (string) config('artifacts.path');
        $existing = $this->existingFiles($path);

        if ($existing !== [] && ! $this->option('force')) {
            $this->error('Artefak berikut sudah ada di '.$path.':');

            foreach ($existing as $file) {
                $this->line('  - '.$file);
            }

            $this->warn('Jalankan dengan --force untuk menimpa.');

            return self::FAILURE;
        }

        $demo->write($path);

        $this->components->info('Artefak demo ditulis ke '.$path);
        $best = $demo->best();
        $this->components->info('Konfigurasi terbaik (demo): alpha '.$best['alpha'].', K '.$best['k'].'.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function existingFiles(string $path): array
    {
        $existing = [];

        foreach ((new DemoArtifacts(new LexiconClassifier))->files() as $filename => $_content) {
            if (is_file(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename)) {
                $existing[] = $filename;
            }
        }

        return $existing;
    }
}
