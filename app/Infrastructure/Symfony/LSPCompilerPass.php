<?php

namespace App\Infrastructure\Symfony;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class LSPCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void {}
}
