<?php

declare(strict_types=1);

namespace App\Tests\Unit\Core\Contracts\Signature;

use App\Core\Contracts\Signature\SignatureConsumer;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SignatureConsumerTest extends TestCase
{
    #[TestDox('collects signature information items')]
    public function testCollectsItems(): void
    {
        $consumer = new SignatureConsumer();
        $sig = ProtocolFactory::signatureInformation('test()');

        ($consumer)($sig);

        $this->assertCount(1, $consumer->results);
        $this->assertSame($sig, $consumer->results[0]);
    }

    #[TestDox('accepts multiple items')]
    public function testAcceptsMultiple(): void
    {
        $consumer = new SignatureConsumer();
        $sig1 = ProtocolFactory::signatureInformation('a()');
        $sig2 = ProtocolFactory::signatureInformation('b()');

        ($consumer)($sig1, $sig2);

        $this->assertCount(2, $consumer->results);
    }
}
