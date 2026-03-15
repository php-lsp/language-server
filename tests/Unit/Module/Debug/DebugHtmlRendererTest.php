<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Debug;

use App\Module\Debug\DebugHtmlRenderer;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DebugHtmlRendererTest extends TestCase
{
    #[TestDox('render returns valid HTML document')]
    public function testRendersHtml(): void
    {
        $renderer = new DebugHtmlRenderer();
        $html = $renderer->render();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    #[TestDox('render contains page title')]
    public function testContainsTitle(): void
    {
        $html = (new DebugHtmlRenderer())->render();
        $this->assertStringContainsString('LSP Debug', $html);
    }

    #[TestDox('render contains API fetch JavaScript')]
    public function testContainsJavaScript(): void
    {
        $html = (new DebugHtmlRenderer())->render();
        $this->assertStringContainsString('/api/indexes', $html);
        $this->assertStringContainsString('fetch(', $html);
    }

    #[TestDox('render contains CSS styles')]
    public function testContainsStyles(): void
    {
        $html = (new DebugHtmlRenderer())->render();
        $this->assertStringContainsString('<style>', $html);
    }

    #[TestDox('render output is non-empty string')]
    public function testNonEmpty(): void
    {
        $html = (new DebugHtmlRenderer())->render();
        $this->assertNotEmpty($html);
        $this->assertIsString($html);
    }
}
