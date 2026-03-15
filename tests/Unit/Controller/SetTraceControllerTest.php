<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SetTraceController;
use App\Tests\TestCase;
use Lsp\Protocol\Type\SetTraceParams;
use Lsp\Protocol\Type\TraceValue;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SetTraceControllerTest extends TestCase
{
    #[TestDox('logs trace value name')]
    public function testLogsTraceValue(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('info')
            ->with($this->stringContains('trace'));
        $logger->method('getHandlers')->willReturn([]);

        $controller = new SetTraceController($logger);
        $params = new SetTraceParams(value: TraceValue::Verbose);

        $controller($params);
    }

    #[TestDox('sets handler levels based on trace value')]
    public function testSetsHandlerLevels(): void
    {
        $handler = new StreamHandler('php://memory', Level::Debug);
        $logger = new Logger('test', [$handler]);

        $controller = new SetTraceController($logger);

        // Off → Warning level
        $controller(new SetTraceParams(value: TraceValue::Off));
        $this->assertSame(Level::Warning, $handler->getLevel());

        // Messages → Info level
        $controller(new SetTraceParams(value: TraceValue::Messages));
        $this->assertSame(Level::Info, $handler->getLevel());

        // Verbose → Debug level
        $controller(new SetTraceParams(value: TraceValue::Verbose));
        $this->assertSame(Level::Debug, $handler->getLevel());
    }
}
