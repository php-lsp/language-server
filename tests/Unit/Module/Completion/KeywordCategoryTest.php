<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\KeywordCategory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class KeywordCategoryTest extends TestCase
{
    #[TestDox('has all expected cases')]
    public function testCases(): void
    {
        $cases = KeywordCategory::cases();

        $this->assertCount(7, $cases);
        $this->assertSame('control_flow', KeywordCategory::CONTROL_FLOW->value);
        $this->assertSame('declaration', KeywordCategory::DECLARATION->value);
        $this->assertSame('modifier', KeywordCategory::MODIFIER->value);
        $this->assertSame('type', KeywordCategory::TYPE->value);
        $this->assertSame('language_construct', KeywordCategory::LANGUAGE_CONSTRUCT->value);
        $this->assertSame('operator', KeywordCategory::OPERATOR->value);
        $this->assertSame('magic_constant', KeywordCategory::MAGIC_CONSTANT->value);
    }
}
