<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\KeywordCategory;
use App\Module\Completion\KeywordDefinitions;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class KeywordDefinitionsTest extends TestCase
{
    #[TestDox('ALL contains all categories')]
    public function testAllContainsAllCategories(): void
    {
        $categories = array_map(fn(array $pair) => $pair[0], KeywordDefinitions::ALL);

        foreach (KeywordCategory::cases() as $case) {
            $this->assertContains($case, $categories, "Missing category: {$case->value}");
        }
    }

    #[TestDox('getCompletionKind returns valid kind for each category')]
    public function testGetCompletionKind(): void
    {
        foreach (KeywordCategory::cases() as $category) {
            $kind = KeywordDefinitions::getCompletionKind($category);
            $this->assertInstanceOf(CompletionItemKind::class, $kind);
        }
    }

    #[TestDox('language constructs get FunctionKind')]
    public function testLanguageConstructsAreFunctionKind(): void
    {
        $this->assertSame(
            CompletionItemKind::FunctionKind,
            KeywordDefinitions::getCompletionKind(KeywordCategory::LANGUAGE_CONSTRUCT),
        );
    }

    #[TestDox('operators get OperatorKind')]
    public function testOperatorsAreOperatorKind(): void
    {
        $this->assertSame(
            CompletionItemKind::OperatorKind,
            KeywordDefinitions::getCompletionKind(KeywordCategory::OPERATOR),
        );
    }

    #[TestDox('magic constants get ConstantKind')]
    public function testMagicConstantsAreConstantKind(): void
    {
        $this->assertSame(
            CompletionItemKind::ConstantKind,
            KeywordDefinitions::getCompletionKind(KeywordCategory::MAGIC_CONSTANT),
        );
    }

    #[TestDox('getCompletionSuffix returns () for language constructs')]
    public function testLanguageConstructSuffix(): void
    {
        $this->assertSame('()', KeywordDefinitions::getCompletionSuffix(KeywordCategory::LANGUAGE_CONSTRUCT));
    }

    #[TestDox('getCompletionSuffix returns space for other categories')]
    public function testDefaultSuffix(): void
    {
        $this->assertSame(' ', KeywordDefinitions::getCompletionSuffix(KeywordCategory::CONTROL_FLOW));
        $this->assertSame(' ', KeywordDefinitions::getCompletionSuffix(KeywordCategory::DECLARATION));
    }

    #[TestDox('control flow keywords contain expected items')]
    public function testControlFlowKeywords(): void
    {
        $this->assertContains('if', KeywordDefinitions::CONTROL_FLOW);
        $this->assertContains('return', KeywordDefinitions::CONTROL_FLOW);
        $this->assertContains('match', KeywordDefinitions::CONTROL_FLOW);
    }
}
