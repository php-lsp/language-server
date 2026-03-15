<?php

declare(strict_types=1);

namespace App\Tests\Playground\Support;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;

/**
 * Collects code coverage from the LSP server process.
 *
 * ## How It Works
 *
 * The server process is started with coverage enabled (PCOV or Xdebug).
 * When the server shuts down, it writes raw coverage data to a `.cov` file.
 *
 * This class provides a bootstrap script that can be prepended to the server
 * entry point to capture coverage, and a merge utility that combines coverage
 * data from multiple server runs into a single report.
 *
 * ## Usage
 *
 * ### Collecting coverage from the server
 *
 * Set `E2E_COVERAGE_DIR` environment variable before running tests:
 *
 * ```bash
 * E2E_COVERAGE_DIR=var/coverage php vendor/bin/phpunit --testsuite playground
 * ```
 *
 * ### Merging coverage data
 *
 * After tests complete, merge coverage files:
 *
 * ```bash
 * php tests/Playground/Support/merge-coverage.php var/coverage
 * ```
 */
final class CoverageBootstrap
{
    /**
     * Generate a PHP bootstrap script that enables coverage collection
     * in the server process.
     *
     * This script should be prepended to the server's PHP execution.
     *
     * @param string $coverageFile Path where coverage data will be written
     * @param string $sourceDir Directory to collect coverage for
     * @return string PHP code for the bootstrap script
     */
    public static function generateBootstrapScript(string $coverageFile, string $sourceDir): string
    {
        return <<<PHP
        <?php
        // E2E Coverage Bootstrap — auto-generated, do not edit
        if (!\\extension_loaded('pcov') && !\\extension_loaded('xdebug')) {
            return; // No coverage driver available
        }

        \$filter = new \\SebastianBergmann\\CodeCoverage\\Filter();
        \$filter->includeDirectory('{$sourceDir}');

        try {
            \$driver = (new \\SebastianBergmann\\CodeCoverage\\Driver\\Selector())->forLineCoverage(\$filter);
            \$coverage = new \\SebastianBergmann\\CodeCoverage\\CodeCoverage(\$driver, \$filter);
            \$coverage->start('e2e-server');

            register_shutdown_function(static function () use (\$coverage) {
                \$coverage->stop();
                (new \\SebastianBergmann\\CodeCoverage\\Report\\PHP())->process(
                    \$coverage,
                    '{$coverageFile}',
                );
            });
        } catch (\\Throwable \$e) {
            // Silently ignore coverage errors
        }
        PHP;
    }

    /**
     * Merge multiple coverage data files into a single coverage report.
     *
     * @param string $coverageDir Directory containing .cov files
     * @param string $sourceDir Source directory for coverage filter
     * @param string|null $cloverOutput Path for Clover XML output
     * @param string|null $htmlOutput Path for HTML output
     */
    public static function mergeCoverage(
        string $coverageDir,
        string $sourceDir,
        ?string $cloverOutput = null,
        ?string $htmlOutput = null,
    ): void {
        $filter = new Filter();
        $filter->includeDirectory($sourceDir);

        $driver = (new Selector())->forLineCoverage($filter);
        $merged = new CodeCoverage($driver, $filter);

        $files = \glob($coverageDir . '/*.cov');
        if ($files === false || $files === []) {
            echo "No coverage files found in {$coverageDir}\n";
            return;
        }

        foreach ($files as $file) {
            $data = require $file;
            if ($data instanceof CodeCoverage) {
                $merged->merge($data);
            }
        }

        if ($cloverOutput !== null) {
            (new Clover())->process($merged, $cloverOutput);
            echo "Clover coverage report written to: {$cloverOutput}\n";
        }

        if ($htmlOutput !== null) {
            (new HtmlReport())->process($merged, $htmlOutput);
            echo "HTML coverage report written to: {$htmlOutput}\n";
        }
    }
}
