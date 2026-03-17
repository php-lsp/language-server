<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Document;

use App\Module\Document\InMemoryDocumentIdentifierFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\TextDocumentIdentifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InMemoryDocumentIdentifierFactoryTest extends TestCase
{
    #[TestDox('create returns TextDocumentIdentifier for file URI')]
    public function testCreateReturnsTextDocumentIdentifier(): void
    {
        $factory = new InMemoryDocumentIdentifierFactory();

        $result = $factory->create('file:///test.php');

        $this->assertInstanceOf(TextDocumentIdentifier::class, $result);
        $this->assertSame('file:///test.php', $result->uri);
    }

    #[TestDox('create caches and returns same instance')]
    public function testCreateCachesResult(): void
    {
        $factory = new InMemoryDocumentIdentifierFactory();

        $first = $factory->create('file:///test.php');
        $second = $factory->create('file:///test.php');

        $this->assertSame($first, $second, 'Should return cached instance');
    }

    #[TestDox('create handles plain file paths')]
    public function testCreateHandlesPlainFilePaths(): void
    {
        $factory = new InMemoryDocumentIdentifierFactory();

        $result = $factory->create('/home/user/test.php');

        $this->assertInstanceOf(TextDocumentIdentifier::class, $result);
        $this->assertStringStartsWith('file://', $result->uri);
    }
}
