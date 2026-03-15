<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Debug;

use App\Module\Debug\DebugHtmlRenderer;
use App\Module\Indexing\Storage\InMemoryStorage;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class DebugHtmlRendererTest extends TestCase
{
    private DebugHtmlRenderer $renderer;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
        $this->storage->write('php.classes.fqn', [
            'App\\Foo' => 'App\\Foo',
            'App\\Bar' => 'App\\Bar',
            'App\\Controller\\Home' => 'App\\Controller\\Home',
        ], 'file:///src/Foo.php');
        $this->storage->write('php.functions.fqn', [
            'App\\hello' => 'App\\hello',
        ], 'file:///src/functions.php');

        $this->renderer = new DebugHtmlRenderer($this->storage);
    }

    #[TestDox('layout returns valid HTML document with HTMX')]
    public function testLayoutReturnsHtml(): void
    {
        $html = $this->renderer->layout();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('htmx.org', $html);
    }

    #[TestDox('layout contains page title')]
    public function testLayoutContainsTitle(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('LSP Debug', $html);
    }

    #[TestDox('layout contains HTMX trigger for initial load')]
    public function testLayoutContainsHtmxTrigger(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('hx-get="/views/indexes"', $html);
        $this->assertStringContainsString('hx-trigger="load"', $html);
    }

    #[TestDox('layout contains CSS styles')]
    public function testLayoutContainsStyles(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('<style>', $html);
    }

    #[TestDox('indexList renders table with all indexes')]
    public function testIndexListRendersTable(): void
    {
        $html = $this->renderer->indexList();

        $this->assertStringContainsString('php.classes.fqn', $html);
        $this->assertStringContainsString('php.functions.fqn', $html);
    }

    #[TestDox('indexList renders clickable rows with HTMX attributes')]
    public function testIndexListHasHtmxLinks(): void
    {
        $html = $this->renderer->indexList();

        $this->assertStringContainsString('hx-get="/views/indexes/', $html);
        $this->assertStringContainsString('hx-target="#content"', $html);
    }

    #[TestDox('indexList renders empty message when no indexes')]
    public function testIndexListEmpty(): void
    {
        $renderer = new DebugHtmlRenderer(new InMemoryStorage());
        $html = $renderer->indexList();

        $this->assertStringContainsString('No indexes registered yet', $html);
    }

    #[TestDox('keysList renders keys for an index')]
    public function testKeysListRendersKeys(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn');

        $this->assertStringContainsString('App\\Foo', $html);
        $this->assertStringContainsString('App\\Bar', $html);
        $this->assertStringContainsString('App\\Controller\\Home', $html);
    }

    #[TestDox('keysList renders search form with HTMX')]
    public function testKeysListHasSearchForm(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('hx-get=', $html);
        $this->assertStringContainsString('name="pattern"', $html);
    }

    #[TestDox('keysList filters by glob pattern')]
    public function testKeysListFiltersWithPattern(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn', ['pattern' => 'App\\Controller\\*']);

        $this->assertStringContainsString('App\\Controller\\Home', $html);
        $this->assertStringNotContainsString('App\\Foo', $html);
        $this->assertStringNotContainsString('App\\Bar', $html);
    }

    #[TestDox('keysList supports pagination')]
    public function testKeysListPagination(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn', ['limit' => '2', 'offset' => '0']);

        $this->assertStringContainsString('of 3', $html);
        $this->assertStringContainsString('Next', $html);
    }

    #[TestDox('keysList renders empty message when no matches')]
    public function testKeysListNoMatches(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn', ['pattern' => 'Vendor\\*']);

        $this->assertStringContainsString('No entries found', $html);
    }

    #[TestDox('keysList renders breadcrumb with index name')]
    public function testKeysListBreadcrumb(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn');

        $this->assertStringContainsString('Indexes', $html);
        $this->assertStringContainsString('php.classes.fqn', $html);
    }

    #[TestDox('entryDetail renders entry key, value, and URI')]
    public function testEntryDetailRendersEntry(): void
    {
        $html = $this->renderer->entryDetail('php.classes.fqn', 'App\\Foo');

        $this->assertStringContainsString('App\\Foo', $html);
        $this->assertStringContainsString('file:///src/Foo.php', $html);
    }

    #[TestDox('entryDetail renders not found for unknown key')]
    public function testEntryDetailNotFound(): void
    {
        $html = $this->renderer->entryDetail('php.classes.fqn', 'App\\NotExist');

        $this->assertStringContainsString('Entry not found', $html);
    }

    #[TestDox('entryDetail renders breadcrumb with index and key')]
    public function testEntryDetailBreadcrumb(): void
    {
        $html = $this->renderer->entryDetail('php.classes.fqn', 'App\\Foo');

        $this->assertStringContainsString('Indexes', $html);
        $this->assertStringContainsString('php.classes.fqn', $html);
        $this->assertStringContainsString('App\\Foo', $html);
    }

    #[TestDox('entryDetail serializes object values to JSON')]
    public function testEntryDetailSerializesObjects(): void
    {
        $this->storage->write('objs', ['k1' => (object) ['name' => 'test']], 'file:///a.php');

        $html = $this->renderer->entryDetail('objs', 'k1');

        $this->assertStringContainsString('stdClass', $html);
        $this->assertStringContainsString('test', $html);
    }

    #[TestDox('HTML output is properly escaped')]
    public function testHtmlEscaping(): void
    {
        $this->storage->write('xss', ['<script>alert(1)</script>' => 'value'], 'file:///x.php');

        $html = $this->renderer->keysList('xss');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
