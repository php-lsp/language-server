#!/usr/bin/env php
<?php

/**
 * Merges multiple Clover XML coverage reports into a single percentage.
 *
 * Usage: php merge-coverage.php coverage-unit.xml coverage-functional.xml coverage-e2e.xml
 *    or: php merge-coverage.php coverage-*.xml
 *
 * Outputs "Lines: XX.XX%" to stdout and writes it to coverage-output.txt.
 */

$files = [];
foreach (array_slice($argv, 1) as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        $files[] = $file;
    }
}

if ($files === []) {
    echo "No coverage files found\n";
    exit(1);
}

$mergedLines = [];
$totalStatements = 0;
$coveredStatements = 0;

foreach ($files as $file) {
    $xml = @simplexml_load_file($file);
    if ($xml === false) {
        fprintf(STDERR, "Warning: could not parse %s\n", $file);
        continue;
    }

    // Collect file nodes from both flat and package-nested structures
    $fileNodes = [];
    foreach ($xml->project->file ?? [] as $node) {
        $fileNodes[] = $node;
    }
    foreach ($xml->project->package ?? [] as $package) {
        foreach ($package->file ?? [] as $node) {
            $fileNodes[] = $node;
        }
    }

    foreach ($fileNodes as $fileNode) {
        $fileName = (string) $fileNode['name'];
        foreach ($fileNode->line ?? [] as $line) {
            if ((string) $line['type'] !== 'stmt') {
                continue;
            }

            $key = $fileName . ':' . (int) $line['num'];
            $count = (int) $line['count'];

            if (!isset($mergedLines[$key])) {
                $mergedLines[$key] = 0;
                $totalStatements++;
            }
            if ($mergedLines[$key] === 0 && $count > 0) {
                $coveredStatements++;
            }
            $mergedLines[$key] = max($mergedLines[$key], $count);
        }
    }
}

$percentage = $totalStatements > 0
    ? round(($coveredStatements / $totalStatements) * 100, 2)
    : 0;

$output = sprintf("Lines: %s%% (%d/%d)\n", $percentage, $coveredStatements, $totalStatements);
echo $output;
file_put_contents('coverage-output.txt', "Lines: {$percentage}%\n");
