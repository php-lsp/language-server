#!/usr/bin/env php
<?php

/**
 * Merge E2E coverage data from multiple server runs into a single report.
 *
 * Usage:
 *   php tests/Playground/Support/merge-coverage.php <coverage-dir> [--clover=path] [--html=path]
 *
 * Example:
 *   php tests/Playground/Support/merge-coverage.php var/coverage \
 *       --clover=var/coverage/clover.xml \
 *       --html=var/coverage/html
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Tests\Playground\Support\CoverageBootstrap;

$coverageDir = $argv[1] ?? null;
if ($coverageDir === null) {
    echo "Usage: php merge-coverage.php <coverage-dir> [--clover=path] [--html=path]\n";
    exit(1);
}

$cloverOutput = null;
$htmlOutput = null;

foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--clover=')) {
        $cloverOutput = substr($arg, 9);
    } elseif (str_starts_with($arg, '--html=')) {
        $htmlOutput = substr($arg, 7);
    }
}

$sourceDir = dirname(__DIR__, 3) . '/app';

CoverageBootstrap::mergeCoverage($coverageDir, $sourceDir, $cloverOutput, $htmlOutput);
