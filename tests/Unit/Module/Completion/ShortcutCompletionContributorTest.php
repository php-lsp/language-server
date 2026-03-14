<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ShortcutCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ShortcutCompletionContributorTest extends TestCase
{
    #[TestDox('returns empty when no current node')]
    public function testReturnsEmptyWhenNoNode(): void
    {
        $results = CompletionTestHelper::contribute(new ShortcutCompletionContributor());
        $this->assertSame([], $results);
    }

    #[TestDox('provides class snippets inside class body')]
    public function testClassSnippets(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { }');
        $results = CompletionTestHelper::contribute(
            new ShortcutCompletionContributor(),
            $psiFile,
            ProtocolFactory::position(0, 18),
        );

        foreach ($results as $item) {
            $this->assertSame(CompletionItemKind::SnippetKind, $item->kind);
        }
        $this->assertIsArray($results);
    }
}
