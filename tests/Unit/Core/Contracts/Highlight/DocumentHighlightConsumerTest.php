<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Highlight;

use App\Core\Contracts\Highlight\DocumentHighlightConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\DocumentHighlight;
use Lsp\Protocol\Type\DocumentHighlightKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
#[TestDox('DocumentHighlightConsumer')]
final class DocumentHighlightConsumerTest extends TestCase
{
    #[TestDox('starts with empty results')]
    public function testStartsEmpty(): void
    {
        $consumer = new DocumentHighlightConsumer();
        $this->assertSame([], $consumer->results);
    }

    #[TestDox('collects highlights via invoke')]
    public function testCollectsHighlights(): void
    {
        $consumer = new DocumentHighlightConsumer();
        $highlight = new DocumentHighlight(
            range: ProtocolFactory::range(),
            kind: DocumentHighlightKind::Text,
        );

        $consumer($highlight);

        $this->assertCount(1, $consumer->results);
        $this->assertSame($highlight, $consumer->results[0]);
    }

    #[TestDox('collects multiple highlights')]
    public function testCollectsMultipleHighlights(): void
    {
        $consumer = new DocumentHighlightConsumer();
        $h1 = new DocumentHighlight(range: ProtocolFactory::range(), kind: DocumentHighlightKind::Read);
        $h2 = new DocumentHighlight(range: ProtocolFactory::range(), kind: DocumentHighlightKind::Write);

        $consumer($h1, $h2);

        $this->assertCount(2, $consumer->results);
    }
}
