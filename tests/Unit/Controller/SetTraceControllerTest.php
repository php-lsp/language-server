<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SetTraceController;
use App\Tests\Support\MockHelper;
use App\Tests\TestCase;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Protocol\Type\SetTraceParams;
use Lsp\Protocol\Type\TraceValue;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SetTraceControllerTest extends TestCase
{
    #[TestDox('logs trace value name')]
    public function testLogsTraceValue(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with($this->stringContains('Verbose'));

        $controller = new SetTraceController($logger);
        $editor = MockHelper::mock(EditorInterface::class);
        $params = new SetTraceParams(value: TraceValue::Verbose);

        $controller($editor, $params);
    }
}
