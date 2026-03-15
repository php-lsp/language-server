<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Hydrator\TypeLangMapper;
use Lsp\Contracts\Hydrator\ExtractorInterface;
use Lsp\Contracts\Hydrator\HydratorInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

final class HydratorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->register(TypeLangMapper::class, TypeLangMapper::class)->setArgument('$cache', new Reference(
            id: CacheInterface::class,
            invalidBehavior: ContainerInterface::NULL_ON_INVALID_REFERENCE,
        ));

        $container->setAlias(HydratorInterface::class, TypeLangMapper::class);
        $container->setAlias(ExtractorInterface::class, TypeLangMapper::class);
    }
}
