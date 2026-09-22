<?php

namespace Tests\Feature\Concerns;

use App\Support\DemoArtifacts;
use App\Support\LexiconClassifier;
trait PreparesDemoArtifacts
{
    private string $artifactsDir;

    protected function prepareArtifacts(bool $empty = false): void
    {
        $this->artifactsDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'ak-artifacts-'.uniqid('', true);

        if (! $empty) {
            (new DemoArtifacts(new LexiconClassifier))->write($this->artifactsDir);
        }

        config()->set('artifacts.path', $this->artifactsDir);
    }

    protected function writeArtifact(string $filename, string $content): void
    {
        file_put_contents($this->artifactsDir.DIRECTORY_SEPARATOR.$filename, $content);
    }

    protected function cleanupArtifacts(): void
    {
        if (! isset($this->artifactsDir) || ! is_dir($this->artifactsDir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->artifactsDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->artifactsDir);
        config()->set('artifacts.path', storage_path('app/artifacts'));
    }
}
