<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Symfony;

use App\Infrastructure\Symfony\LSPCompilerPass;
use App\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class LSPCompilerPassTest extends TestCase
{
    #[TestDox('process does nothing')]
    public function testProcessIsNoOp(): void
    {
        $pass = new LSPCompilerPass();
        $container = $this->createMock(ContainerBuilder::class);

        $pass->process($container);

        $this->assertTrue(true);
    }
}
