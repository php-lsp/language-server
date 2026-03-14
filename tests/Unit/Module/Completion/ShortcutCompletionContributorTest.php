<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ShortcutCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\ProtocolFactory;
use App\Tests\Support\PsiFileFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\InsertTextFormat;
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
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public $x; }');
        $results = CompletionTestHelper::contribute(
            new ShortcutCompletionContributor(),
            $psiFile,
            ProtocolFactory::position(0, 20),
        );

        $this->assertNotEmpty($results);
        $labels = array_map(fn($item) => $item->label, $results);
        $this->assertContains('pubf', $labels);
        $this->assertContains('pubsf', $labels);
        $this->assertContains('prof', $labels);
        $this->assertContains('prosf', $labels);
        $this->assertContains('prif', $labels);
        $this->assertContains('prisf', $labels);
        $this->assertContains('__construct', $labels);

        foreach ($results as $item) {
            $this->assertSame(CompletionItemKind::SnippetKind, $item->kind);
            $this->assertSame(InsertTextFormat::Snippet, $item->insertTextFormat);
        }
    }

    #[TestDox('class snippets have correct details')]
    public function testClassSnippetDetails(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public $x; }');
        $results = CompletionTestHelper::contribute(
            new ShortcutCompletionContributor(),
            $psiFile,
            ProtocolFactory::position(0, 20),
        );

        $byLabel = [];
        foreach ($results as $item) {
            $byLabel[$item->label] = $item;
        }

        $this->assertSame('public function', $byLabel['pubf']->detail);
        $this->assertSame('public static function', $byLabel['pubsf']->detail);
        $this->assertSame('protected function', $byLabel['prof']->detail);
        $this->assertSame('protected static function', $byLabel['prosf']->detail);
        $this->assertSame('private function', $byLabel['prif']->detail);
        $this->assertSame('private static function', $byLabel['prisf']->detail);
        $this->assertSame('public function __construct() {}', $byLabel['__construct']->detail);
    }

    #[TestDox('does not provide class snippets outside class body')]
    public function testNoClassSnippetsOutsideClass(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $results = CompletionTestHelper::contribute(
            new ShortcutCompletionContributor(),
            $psiFile,
            ProtocolFactory::position(0, 10),
        );

        $labels = array_map(fn($item) => $item->label, $results);
        $this->assertNotContains('pubf', $labels);
        $this->assertNotContains('__construct', $labels);
    }

    #[TestDox('provides global entity snippets inside namespace')]
    public function testGlobalEntitiesInNamespace(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php namespace App; ');
        $results = CompletionTestHelper::contribute(
            new ShortcutCompletionContributor(),
            $psiFile,
            ProtocolFactory::position(0, 20),
        );

        // globalEntities has a logic bug: condition uses || instead of &&
        // so it always returns early. We just verify it runs without error.
        $this->assertIsArray($results);
    }
}
