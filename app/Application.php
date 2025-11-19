<?php

declare(strict_types=1);

namespace App;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\Indexing\AsIndexer;
use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Infrastructure\Symfony\LSPCompilerPass;
use Lsp\Extension\DocumentManager\DocumentManagerExtension;
use Lsp\Kernel\LanguageServerKernel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class Application extends LanguageServerKernel
{
    public const ATTRIBUTES = [
        AsIndexer::class => 'lsp.indexers',
        AsCompletionContributor::class => 'lsp.completionContributors',
        AsReferenceContributor::class => 'lsp.referenceContributors',
        AsDocumentationContributor::class => 'lsp.documentationContributors',
        AsSignatureContributor::class => 'lsp.signatureContributors',
    ];

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        foreach (self::ATTRIBUTES as $class => $tag) {
            $container->registerAttributeForAutoconfiguration(
                attributeClass: $class,
                configurator: static function (ChildDefinition $definition) use ($tag): void {
                    $definition->addTag($tag);
                },
            );
        }
//        dump($container->get(LoggerInterface::class));
        $container->addCompilerPass(new LSPCompilerPass());
        $container->addCompilerPass(new DocumentManagerExtension());
    }
}
