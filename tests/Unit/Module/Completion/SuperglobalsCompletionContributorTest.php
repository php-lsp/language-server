<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\SuperglobalsCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SuperglobalsCompletionContributorTest extends TestCase
{
    #[TestDox('contributes all superglobals')]
    public function testContributesSuperglobals(): void
    {
        $results = CompletionTestHelper::contribute(new SuperglobalsCompletionContributor());

        $labels = array_map(fn($item) => $item->label, $results);
        $this->assertContains('$_GET', $labels);
        $this->assertContains('$_POST', $labels);
        $this->assertContains('$_SERVER', $labels);
        $this->assertContains('$GLOBALS', $labels);
        $this->assertCount(9, $results);
    }

    #[TestDox('all items have VariableKind')]
    public function testItemsAreVariableKind(): void
    {
        $results = CompletionTestHelper::contribute(new SuperglobalsCompletionContributor());

        foreach ($results as $item) {
            $this->assertSame(CompletionItemKind::VariableKind, $item->kind);
        }
    }
}
