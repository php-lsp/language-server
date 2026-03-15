<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony;

use Lsp\Extension\DocumentManager\ArgumentResolver\EditorArgumentResolver;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactory;
use Lsp\Extension\DocumentManager\Editor\Document\DocumentFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\Document\UriFactory;
use Lsp\Extension\DocumentManager\Editor\Document\UriFactoryInterface;
use Lsp\Extension\DocumentManager\Editor\EditorProvider;
use Lsp\Extension\DocumentManager\Editor\EditorProviderInterface;
use Lsp\Server\ConnectionProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers DocumentManager services (URI factory, document factory, editor
 * provider, argument resolver) without the vendor controllers.
 *
 * App-level controllers in {@see \App\Controller\TextDocument} replace the
 * vendor controllers and dispatch domain events via the event bus.
 */
final class DocumentManagerCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $this->registerServices($container);
        $this->registerEditorArgumentResolver($container);
    }

    private function registerServices(ContainerBuilder $container): void
    {
        $container->register(UriFactoryInterface::class, UriFactory::class);

        $container->register(DocumentFactoryInterface::class, DocumentFactory::class)->setArgument(
            '$factory',
            new Reference(UriFactoryInterface::class),
        );
    }

    private function registerEditorArgumentResolver(ContainerBuilder $container): void
    {
        $container->register(EditorProviderInterface::class, EditorProvider::class)->setArgument(
            '$connections',
            new Reference(ConnectionProviderInterface::class),
        );

        $container
            ->register(EditorArgumentResolver::class, EditorArgumentResolver::class)
            ->setArgument('$provider', new Reference(EditorProviderInterface::class))
            ->addTag('lsp.dispatcher.argument_resolver');
    }
}
