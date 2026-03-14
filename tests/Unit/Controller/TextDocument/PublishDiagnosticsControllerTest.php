<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\TextDocument;

use App\Controller\TextDocument\PublishDiagnosticsController;
use App\Module\Notification\ServerNotificationSender;
use App\Tests\TestCase;
use Lsp\Protocol\Type\PublishDiagnosticsParams;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class PublishDiagnosticsControllerTest extends TestCase
{
    #[TestDox('constructor accepts ServerNotificationSender')]
    public function testConstructor(): void
    {
        $sender = (new \ReflectionClass(ServerNotificationSender::class))->newInstanceWithoutConstructor();
        $controller = new PublishDiagnosticsController($sender);

        $this->assertInstanceOf(PublishDiagnosticsController::class, $controller);
    }
}
