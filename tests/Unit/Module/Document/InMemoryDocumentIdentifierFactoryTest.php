<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Document;

use App\Module\Document\InMemoryDocumentIdentifierFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class InMemoryDocumentIdentifierFactoryTest extends TestCase
{
    #[TestDox('create throws due to FifoCache not supporting array access')]
    public function testCreateThrowsDueToArrayAccess(): void
    {
        $factory = new InMemoryDocumentIdentifierFactory();

        $this->expectException(\Error::class);
        $factory->create('file:///test.php');
    }
}
