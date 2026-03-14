<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\KeywordDefinitions;
use App\Module\Completion\KeywordsCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class KeywordsCompletionContributorTest extends TestCase
{
    #[TestDox('contributes all keywords')]
    public function testContributesAllKeywords(): void
    {
        $results = CompletionTestHelper::contribute(new KeywordsCompletionContributor());

        $totalKeywords = array_sum(array_map(fn($pair) => count($pair[1]), KeywordDefinitions::ALL));
        $this->assertCount($totalKeywords, $results);
    }

    #[TestDox('each item has label and kind')]
    public function testItemsHaveLabelAndKind(): void
    {
        $results = CompletionTestHelper::contribute(new KeywordsCompletionContributor());

        foreach ($results as $item) {
            $this->assertNotEmpty($item->label);
            $this->assertNotNull($item->kind);
        }
    }
}
