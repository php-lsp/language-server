<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class LSPCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void {}
}
