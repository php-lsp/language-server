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

    #[TestDox('layout contains hash-based routing for initial load')]
    public function testLayoutContainsHashRouting(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('navigateToHash', $html);
        $this->assertStringContainsString('hashchange', $html);
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

    // --- index list ---

    #[TestDox('indexList shows memory usage for each index')]
    public function testIndexListShowsMemory(): void
    {
        $html = $this->renderer->indexList();

        $this->assertStringContainsString('Memory', $html);
        $this->assertMatchesRegularExpression('/\\d+(\\.\\d+)?\\s*(B|KB|MB)/', $html);
    }

    #[TestDox('indexList contains global search form')]
    public function testIndexListHasGlobalSearch(): void
    {
        $html = $this->renderer->indexList();

        $this->assertStringContainsString('hx-get="/views/search"', $html);
        $this->assertStringContainsString('delay:300ms', $html);
    }

    // --- global search ---

    #[TestDox('globalSearch returns results across all indexes')]
    public function testGlobalSearchFindsResults(): void
    {
        $html = $this->renderer->globalSearch(['pattern' => 'Foo']);

        $this->assertStringContainsString('App\\Foo', $html);
        $this->assertStringContainsString('php.classes.fqn', $html);
    }

    #[TestDox('globalSearch auto-wraps pattern with wildcards')]
    public function testGlobalSearchAutoWildcards(): void
    {
        $html = $this->renderer->globalSearch(['pattern' => 'Controller']);

        $this->assertStringContainsString('App\\Controller\\Home', $html);
    }

    #[TestDox('globalSearch shows empty message for no query')]
    public function testGlobalSearchEmptyQuery(): void
    {
        $html = $this->renderer->globalSearch([]);

        $this->assertStringContainsString('Enter a search query', $html);
    }

    #[TestDox('globalSearch shows no results message')]
    public function testGlobalSearchNoResults(): void
    {
        $html = $this->renderer->globalSearch(['pattern' => 'NonExistentXYZ']);

        $this->assertStringContainsString('No results found', $html);
    }

    // --- flexible search ---

    #[TestDox('keysList auto-wraps pattern with wildcards when no glob chars')]
    public function testKeysListAutoWildcards(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn', ['pattern' => 'Controller']);

        $this->assertStringContainsString('App\\Controller\\Home', $html);
        $this->assertStringNotContainsString('App\\Foo', $html);
    }

    #[TestDox('keysList preserves explicit glob pattern')]
    public function testKeysListExplicitGlob(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn', ['pattern' => 'App\\*']);

        $this->assertStringContainsString('App\\Foo', $html);
        $this->assertStringContainsString('App\\Bar', $html);
        $this->assertStringContainsString('App\\Controller\\Home', $html);
    }

    // --- layout ---

    #[TestDox('layout contains export JSON button')]
    public function testLayoutContainsExportButton(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('/api/export', $html);
        $this->assertStringContainsString('Export JSON', $html);
    }

    // --- debounce ---

    #[TestDox('keysList search input has debounce trigger')]
    public function testKeysListSearchHasDebounce(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn');
        $this->assertStringContainsString('delay:300ms', $html);
    }

    #[TestDox('index list has checkboxes for batch selection')]
    public function testIndexListHasCheckboxes(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('id="select-all"', $html);
        $this->assertStringContainsString('class="index-checkbox"', $html);
        $this->assertStringContainsString('index-batch-form', $html);
    }

    #[TestDox('index list has batch action buttons')]
    public function testIndexListHasBatchActions(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('batch-clear', $html);
        $this->assertStringContainsString('batch-reindex', $html);
        $this->assertStringContainsString('batch-export', $html);
    }

    // --- Navigation tabs ---

    #[TestDox('index list has navigation tabs')]
    public function testIndexListHasTabs(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('nav-tabs', $html);
        $this->assertStringContainsString('Files', $html);
    }

    // --- Sortable table ---

    #[TestDox('index list has sortable columns')]
    public function testIndexListHasSortableColumns(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('class="sortable"', $html);
        $this->assertStringContainsString('sortTable', $html);
    }

    #[TestDox('index list has data-sort attributes for numeric sorting')]
    public function testIndexListHasDataSort(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('data-sort=', $html);
    }

    // --- Confirmation dialogs ---

    #[TestDox('batch actions have confirmation dialogs')]
    public function testBatchActionsHaveConfirmation(): void
    {
        $html = $this->renderer->indexList();
        $this->assertStringContainsString('confirm(', $html);
    }

    // --- File browser ---

    #[TestDox('file list renders files with index count')]
    public function testFileListRendersFiles(): void
    {
        $html = $this->renderer->fileList();
        $this->assertStringContainsString('Foo.php', $html);
        $this->assertStringContainsString('functions.php', $html);
        $this->assertStringContainsString('of 2', $html);
    }

    #[TestDox('file list has navigation tabs')]
    public function testFileListHasTabs(): void
    {
        $html = $this->renderer->fileList();
        $this->assertStringContainsString('nav-tabs', $html);
        $this->assertStringContainsString('Indexes', $html);
    }

    #[TestDox('file list filters by pattern')]
    public function testFileListFiltersByPattern(): void
    {
        $html = $this->renderer->fileList(['pattern' => 'Foo']);
        $this->assertStringContainsString('Foo.php', $html);
        $this->assertStringNotContainsString('functions.php', $html);
    }

    #[TestDox('file list shows empty state')]
    public function testFileListEmpty(): void
    {
        $emptyRenderer = new DebugHtmlRenderer(new InMemoryStorage());
        $html = $emptyRenderer->fileList();
        $this->assertStringContainsString('No files found', $html);
    }

    #[TestDox('file detail shows indexes for a file')]
    public function testFileDetailShowsIndexes(): void
    {
        $html = $this->renderer->fileDetail('file:///src/Foo.php');
        $this->assertStringContainsString('php.classes.fqn', $html);
        $this->assertStringContainsString('App\\Foo', $html);
        $this->assertStringContainsString('file:///src/Foo.php', $html);
    }

    #[TestDox('file detail shows empty state for unknown URI')]
    public function testFileDetailUnknown(): void
    {
        $html = $this->renderer->fileDetail('file:///nonexistent.php');
        $this->assertStringContainsString('No entries found', $html);
    }

    // --- Autocomplete ---

    #[TestDox('keys list has autocomplete input')]
    public function testKeysListHasAutocomplete(): void
    {
        $html = $this->renderer->keysList('php.classes.fqn');
        $this->assertStringContainsString('key-suggestions', $html);
        $this->assertStringContainsString('fetchSuggestions', $html);
    }

    // --- Layout features ---

    #[TestDox('layout has status bar')]
    public function testLayoutHasStatusBar(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('status-bar', $html);
        $this->assertStringContainsString('refreshStatus', $html);
    }

    #[TestDox('layout has toast container')]
    public function testLayoutHasToastContainer(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('toast-container', $html);
        $this->assertStringContainsString('showToast', $html);
    }

    #[TestDox('layout has sortTable function')]
    public function testLayoutHasSortFunction(): void
    {
        $html = $this->renderer->layout();
        $this->assertStringContainsString('function sortTable', $html);
    }
}
