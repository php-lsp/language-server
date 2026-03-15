<?php

declare(strict_types=1);

namespace App\Controller\Concern;

use Psr\Log\LoggerInterface;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;
use function React\Promise\Timer\timeout;

trait RunsContributorsInParallel
{
    private function runContributorsInParallel(
        array $contributors,
        object $context,
        object $consumer,
        LoggerInterface $logger,
        float $timeoutSeconds = 1.0,
    ): void {
        $promises = [];
        foreach ($contributors as $contributor) {
            $promises[] = timeout(
                async(
                    static function () use ($contributor, $context, $consumer): void {
                        $contributor->contribute($context, $consumer);
                    },
                )()->catch(static function (\Throwable $e) use ($logger): void {
                    $logger->error('Contributor failed: ' . $e->getMessage());
                }),
                time: $timeoutSeconds,
            )->catch(static function (\Throwable $e) use ($logger): void {
                $logger->warning('Contributor timed out: ' . $e->getMessage());
            });
        }

        await(all($promises));
    }
}
