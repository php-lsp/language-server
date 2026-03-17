<?php

declare(strict_types=1);

namespace App;

use App\Core\Contracts\CallHierarchy\AsCallHierarchyContributor;
use App\Core\Contracts\CodeAction\AsCodeActionContributor;
use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Declaration\AsDeclarationContributor;
use App\Core\Contracts\Definition\AsDefinitionContributor;
use App\Core\Contracts\Documentation\AsDocumentationContributor;
use App\Core\Contracts\DocumentSymbol\AsDocumentSymbolContributor;
use App\Core\Contracts\FoldingRange\AsFoldingRangeContributor;
use App\Core\Contracts\Highlight\AsDocumentHighlightContributor;
use App\Core\Contracts\Implementation\AsImplementationContributor;
use App\Core\Contracts\Indexing\AsIndexer;
use App\Core\Contracts\InlayHint\AsInlayHintContributor;
use App\Core\Contracts\References\AsReferenceContributor;
use App\Core\Contracts\SelectionRange\AsSelectionRangeContributor;
use App\Core\Contracts\SemanticToken\AsSemanticTokenContributor;
use App\Core\Contracts\Signature\AsSignatureContributor;
use App\Core\Contracts\TypeDefinition\AsTypeDefinitionContributor;
use App\DependencyInjection\HydratorCompilerPass;
use App\Infrastructure\Symfony\DocumentManagerCompilerPass;
use App\Infrastructure\Symfony\LSPCompilerPass;
use Lsp\Kernel\LanguageServerKernel;
use Override;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class Application extends LanguageServerKernel
{
    /** @var array<class-string, string> */
    public const array ATTRIBUTES = [
        AsIndexer::class => 'lsp.indexers',
        AsCallHierarchyContributor::class => 'lsp.callHierarchyContributors',
        AsCodeActionContributor::class => 'lsp.codeActionContributors',
        AsCompletionContributor::class => 'lsp.completionContributors',
        AsReferenceContributor::class => 'lsp.referenceContributors',
        AsDeclarationContributor::class => 'lsp.declarationContributors',
        AsDefinitionContributor::class => 'lsp.definitionContributors',
        AsDocumentSymbolContributor::class => 'lsp.documentSymbolContributors',
        AsDocumentationContributor::class => 'lsp.documentationContributors',
        AsFoldingRangeContributor::class => 'lsp.foldingRangeContributors',
        AsDocumentHighlightContributor::class => 'lsp.documentHighlightContributors',
        AsImplementationContributor::class => 'lsp.implementationContributors',
        AsInlayHintContributor::class => 'lsp.inlayHintContributors',
        AsSelectionRangeContributor::class => 'lsp.selectionRangeContributors',
        AsSemanticTokenContributor::class => 'lsp.semanticTokenContributors',
        AsSignatureContributor::class => 'lsp.signatureContributors',
        AsTypeDefinitionContributor::class => 'lsp.typeDefinitionContributors',
    ];

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        foreach (self::ATTRIBUTES as $class => $tag) {
            $container->registerAttributeForAutoconfiguration(
                attributeClass: $class,
                configurator: static function (ChildDefinition $definition, object $attribute) use ($tag): void {
                    $priority = property_exists($attribute, 'priority') ? (int) $attribute->priority : 0;
                    $definition->addTag($tag, ['priority' => $priority]);
                },
            );
        }
        $container->addCompilerPass(new LSPCompilerPass());
        $container->addCompilerPass(new DocumentManagerCompilerPass());
        $container->addCompilerPass(new HydratorCompilerPass());
    }
}
