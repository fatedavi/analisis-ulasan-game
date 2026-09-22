<?php

$files = [
    'app/Support/DemoArtifacts.php',
    'app/Support/CsvReviewImporter.php',
    'tests/Unit/DemoArtifactsTest.php',
    'tests/Unit/CsvReviewImporterTest.php',
];

foreach ($files as $path) {
    $lines = file($path);
    printf("=== %s (total %d) ===\n", $path, count($lines));

    foreach ($lines as $i => $line) {
        $n = $i + 1;
        $trimmed = rtrim($lineapsed);

        if (str_contains($trimmed, 'resource_path') || str_contains($trimmed, 'stopwords-id.txt') || str_contains($trimmed, 'stopwords_id.txt')) {
            printf("STOP%04d|%s\n", $n, $trimmed);
        }

        if (str_contains($trimmed, 'new DemoArtifacts') || str_contains($trimmed, 'new LexiconClassifier') || str_contains($trimmed, 'new LexiconClassifier;')) {
            printf("CTOR%04d|%s\n", $n, $trimmed);
        }

        if (str_contains($trimmed, 'use App\\Support\\LexiconClassifier') || str_contains($trimmed, 'use App\\Support\\LexiconClassifier;')) {
            printf("IMPORT%04d|%s\n", $n, $trimmed);
        }
    }

    echo "\n";
}

echo "\n=== resource_path/stopwords context in DemoArtifacts (315-330) ===\n";
$lines = file('app/Support/DemoArtifacts.php');
for ($i = 314; $i < 331; $i++) {
    printf("D%04d|%s\n", $i + 1, rtrim($lines[$i]));
}

echo "\n=== resource_path/stopwords context in CsvReviewImporter (520-548) ===\n";
$lines = file('app/Support/CsvReviewImporter.php');
for ($i = 519; $i < 549; $i++) {
    printf("C%04d|%s\n", $i + 1, rtrim($lines[$i]));
}
