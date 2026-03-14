<?php

declare(strict_types=1);

namespace App\Module\Completion;

use App\Core\Contracts\Completion\AsCompletionContributor;
use App\Core\Contracts\Completion\CompletionConsumer;
use App\Core\Contracts\Completion\CompletionContext;
use App\Core\Contracts\Completion\CompletionContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\InsertTextFormat;
use PhpParser\Node;

#[AsCompletionContributor]
final class ShortcutCompletionContributor implements CompletionContributor
{
    public function contribute(CompletionContext $context, CompletionConsumer $consumer): void
    {
        $element = $context->currentNode();
        if ($element === null) {
            return;
        }

        foreach ($this->getAll($element) as $item) {
            $consumer($item);
        }
    }

    private function getAll(Node $node): iterable
    {
        yield from $this->globalEntities($node);
        yield from $this->classSnippets($node);
    }

    public function classSnippets(Node $node): \Generator
    {
        $parent = Tree::parent($node);
        if (!$parent instanceof Node\Stmt\Class_) {
            return;
        }

        yield 'pubf' => new CompletionItem(
            label: 'pubf',
            kind: CompletionItemKind::SnippetKind,
            detail: 'public function',
            insertText: <<<'TEXT'
                public function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
        yield 'pubsf' => new CompletionItem(
            label: 'pubsf',
            kind: CompletionItemKind::SnippetKind,
            detail: 'public static function',
            insertText: <<<'TEXT'
                public static function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'prof' => new CompletionItem(
            label: 'prof',
            kind: CompletionItemKind::SnippetKind,
            detail: 'protected function',
            insertText: <<<'TEXT'
                protected function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
        yield 'prosf' => new CompletionItem(
            label: 'prosf',
            kind: CompletionItemKind::SnippetKind,
            detail: 'protected static function',
            insertText: <<<'TEXT'
                protected static function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'prif' => new CompletionItem(
            label: 'prif',
            kind: CompletionItemKind::SnippetKind,
            detail: 'private function',
            insertText: <<<'TEXT'
                private function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
        yield 'prisf' => new CompletionItem(
            label: 'prisf',
            kind: CompletionItemKind::SnippetKind,
            detail: 'private static function',
            insertText: <<<'TEXT'
                private static function ${1:name}(${2}): ${3:void}
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield '__construct' => new CompletionItem(
            label: '__construct',
            kind: CompletionItemKind::SnippetKind,
            detail: 'public function __construct() {}',
            insertText: <<<'TEXT'
                public function __construct($1)
                {
                    ${0}
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
    }

    private function globalEntities(Node $node): iterable
    {
        $parent = Tree::parent($node);
        if (!$parent instanceof Node\Stmt\Namespace_ || !$parent instanceof Node\Stmt\Declare_) {
            return;
        }

        yield 'class' => new CompletionItem(
            label: 'class',
            kind: CompletionItemKind::SnippetKind,
            detail: 'class {}',
            insertText: <<<'TEXT'
                class ${1:Name}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
        yield 'cle' => new CompletionItem(
            label: 'cle',
            kind: CompletionItemKind::SnippetKind,
            detail: 'class extends',
            insertText: <<<'TEXT'
                class ${1:Name} extends ${2:Parent}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
        yield 'cli' => new CompletionItem(
            label: 'cli',
            kind: CompletionItemKind::SnippetKind,
            detail: 'class implements',
            insertText: <<<'TEXT'
                class ${1:Name} implements ${2:Parent}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'interface' => new CompletionItem(
            label: 'interface',
            kind: CompletionItemKind::SnippetKind,
            detail: 'interface {}',
            insertText: <<<'TEXT'
                interface ${1:Name}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'enum' => new CompletionItem(
            label: 'enum',
            kind: CompletionItemKind::SnippetKind,
            detail: 'enum {}',
            insertText: <<<'TEXT'
                enum ${1:Name}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'trait' => new CompletionItem(
            label: 'trait',
            kind: CompletionItemKind::SnippetKind,
            detail: 'trait {}',
            insertText: <<<'TEXT'
                trait ${1:Name}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );

        yield 'fun' => new CompletionItem(
            label: 'fun',
            kind: CompletionItemKind::SnippetKind,
            detail: 'function {}',
            insertText: <<<'TEXT'
                function ${1:name}(${2}): ${3:void}
                {
                    $0
                }
                TEXT,
            insertTextFormat: InsertTextFormat::Snippet,
        );
    }
}
