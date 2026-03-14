<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SetTraceController;
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
    #[TestDox('logs trace value')]
    public function testLogsTraceValue(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $controller = new SetTraceController($logger);
        $editor = $this->createMock(EditorInterface::class);
        $params = new SetTraceParams(value: TraceValue::Verbose);

        $controller($editor, $params);
    }
}
