<?php

declare(strict_types=1);

namespace App;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Indexing\AsIndexer;
use App\Infrastructure\Symfony\LSPCompilerPass;
use Lsp\Extension\DocumentManager\DocumentManagerExtension;
use Lsp\Kernel\LanguageServerKernel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class Application extends LanguageServerKernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(
            attributeClass: AsIndexer::class,
            configurator: static function (ChildDefinition $definition, AsIndexer $attr): void {
                $definition->addTag('lsp.indexers');
            },
        );
        $container->registerAttributeForAutoconfiguration(
            attributeClass: AsCompletionContributor::class,
            configurator: static function (ChildDefinition $definition, AsCompletionContributor $attr): void {
                dump($definition);
                $definition->addTag('lsp.completionContributors');
            },
        );
//        dump($container->get(LoggerInterface::class));
        $container->addCompilerPass(new LSPCompilerPass());
        $container->addCompilerPass(new DocumentManagerExtension());
    }
}
