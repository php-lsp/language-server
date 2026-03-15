<?php

declare(strict_types=1);

namespace App\Module\Debug;

use App\Module\Indexing\Storage\DebugStorageInterface;
use App\Module\Indexing\Storage\Entry;

final class DebugHtmlRenderer
{
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly DebugStorageInterface $storage,
    ) {}

    public function layout(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LSP Debug — Index Inspector</title>
<script src="https://unpkg.com/htmx.org@2.0.4"></script>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace; background: #0d1117; color: #c9d1d9; }
  a { color: #58a6ff; text-decoration: none; }
  a:hover { text-decoration: underline; }

  .header { background: #161b22; border-bottom: 1px solid #30363d; padding: 16px 24px; display: flex; align-items: center; gap: 16px; }
  .header h1 { font-size: 18px; color: #f0f6fc; }
  .header .badge { background: #238636; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
  .header .spacer { flex: 1; }
  .header .btn { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 6px 14px; color: #c9d1d9; cursor: pointer; font-size: 13px; text-decoration: none; }
  .header .btn:hover { background: #30363d; text-decoration: none; }

  .container { max-width: 1200px; margin: 0 auto; padding: 24px; }

  .breadcrumb { margin-bottom: 16px; font-size: 14px; color: #8b949e; }
  .breadcrumb a { color: #58a6ff; cursor: pointer; }
  .breadcrumb .sep { margin: 0 6px; }

  .search-form { display: flex; gap: 8px; margin-bottom: 16px; }
  .search-form input { flex: 1; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 8px 12px; color: #c9d1d9; font-size: 14px; font-family: monospace; }
  .search-form input:focus { outline: none; border-color: #58a6ff; }
  .search-form button { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 8px 16px; color: #c9d1d9; cursor: pointer; font-size: 14px; }
  .search-form button:hover { background: #30363d; }

  table { width: 100%; border-collapse: collapse; background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
  th { text-align: left; padding: 10px 16px; background: #21262d; color: #8b949e; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
  td { padding: 10px 16px; border-bottom: 1px solid #21262d; font-size: 14px; }
  tr.clickable { cursor: pointer; }
  tr.clickable:hover td { background: #1c2128; }
  tr:last-child td { border-bottom: none; }
  .key { font-family: monospace; color: #ff7b72; }
  .value { font-family: monospace; color: #7ee787; }
  .uri { font-family: monospace; color: #8b949e; font-size: 12px; }
  .count { color: #d2a8ff; font-weight: bold; }
  .memory { color: #8b949e; font-size: 12px; font-family: monospace; }
  .num { color: #8b949e; }
  .index-tag { background: #1f2937; border: 1px solid #30363d; border-radius: 4px; padding: 1px 6px; font-size: 11px; color: #8b949e; font-family: monospace; }

  .detail { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px; }
  .detail pre { background: #0d1117; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.6; color: #c9d1d9; white-space: pre-wrap; word-break: break-all; }
  .detail .label { color: #8b949e; font-size: 12px; text-transform: uppercase; margin-bottom: 4px; }
  .detail .section { margin-bottom: 16px; }

  .pagination { display: flex; gap: 8px; margin-top: 16px; align-items: center; }
  .pagination a, .pagination span.btn-disabled { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 6px 12px; color: #c9d1d9; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
  .pagination span.btn-disabled { opacity: 0.4; cursor: not-allowed; }
  .pagination a:hover { background: #30363d; }
  .pagination .info { color: #8b949e; font-size: 13px; }

  .empty { color: #8b949e; padding: 24px; text-align: center; font-style: italic; }
  .hint { color: #6e7681; font-size: 12px; margin-top: 4px; }
  .htmx-indicator { display: none; }
  .htmx-request .htmx-indicator { display: inline; }
  .htmx-request.htmx-indicator { display: inline; }

  .checkbox-cell { width: 30px; text-align: center; }
  .batch-actions { display: flex; gap: 8px; margin-top: 12px; }
  .batch-actions .btn { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 6px 14px; color: #c9d1d9; cursor: pointer; font-size: 13px; }
  .batch-actions .btn:hover:not(:disabled) { background: #30363d; }
  .batch-actions .btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
</head>
<body>

<div class="header">
  <h1><a hx-get="/views/indexes" hx-target="#content" hx-push-url="/" style="color:#f0f6fc">LSP Debug</a></h1>
  <span class="badge">Index Inspector</span>
  <span class="spacer"></span>
  <a class="btn" href="/api/export" target="_blank">Export JSON</a>
</div>

<div class="container">
  <div id="content" hx-get="/views/indexes" hx-trigger="load" hx-target="#content">
    <div class="empty">Loading...</div>
  </div>
</div>

</body>
</html>
HTML;
    }

    public function indexList(): string
    {
        $stats = $this->storage->stats();

        $breadcrumb = '<div class="breadcrumb">Indexes</div>';

        // Global search form
        $searchForm = <<<'HTML'
        <form class="search-form" hx-get="/views/search" hx-target="#content" hx-push-url="true">
          <input type="text" name="pattern" placeholder="Search across all indexes (e.g. Controller, App\Foo...)"
                 hx-get="/views/search" hx-target="#content" hx-trigger="keyup changed delay:300ms" hx-push-url="true">
          <button type="submit">Search</button>
        </form>
        <div class="hint">Wildcards are added automatically. Type any part of a key to search.</div>
        HTML;

        if ($stats === []) {
            return $breadcrumb . $searchForm . '<div class="empty">No indexes registered yet.</div>';
        }

        $rows = '';
        foreach ($stats as $info) {
            $encodedKey = $this->e($info['key']);
            $urlKey = urlencode($info['key']);
            $memoryFormatted = $this->formatBytes($info['memory']);
            $rows .= <<<HTML
            <tr class="clickable">
              <td class="checkbox-cell" onclick="event.stopPropagation()"><input type="checkbox" name="indexes[]" value="{$encodedKey}" class="index-checkbox"></td>
              <td class="key" hx-get="/views/indexes/{$urlKey}/keys" hx-target="#content" hx-push-url="/indexes/{$urlKey}">{$encodedKey}</td>
              <td class="count" hx-get="/views/indexes/{$urlKey}/keys" hx-target="#content" hx-push-url="/indexes/{$urlKey}">{$info['count']}</td>
              <td class="memory" hx-get="/views/indexes/{$urlKey}/keys" hx-target="#content" hx-push-url="/indexes/{$urlKey}">{$memoryFormatted}</td>
            </tr>
            HTML;
        }

        $batchActions = <<<'HTML'
        <div class="batch-actions">
          <button type="button" class="btn batch-btn" id="batch-clear" disabled>Clear</button>
          <button type="button" class="btn batch-btn" id="batch-reindex" disabled>Reindex</button>
          <button type="button" class="btn batch-btn" id="batch-export" disabled>Export JSON</button>
        </div>
        <script>
        (function() {
          const selectAll = document.getElementById('select-all');
          const form = document.getElementById('index-batch-form');

          function updateButtons() {
            const checked = form.querySelectorAll('.index-checkbox:checked');
            form.querySelectorAll('.batch-btn').forEach(b => b.disabled = checked.length === 0);
          }

          selectAll.addEventListener('change', function() {
            form.querySelectorAll('.index-checkbox').forEach(cb => cb.checked = this.checked);
            updateButtons();
          });

          form.addEventListener('change', function(e) {
            if (e.target.classList.contains('index-checkbox')) {
              const all = form.querySelectorAll('.index-checkbox');
              const checked = form.querySelectorAll('.index-checkbox:checked');
              selectAll.checked = all.length === checked.length;
              selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
              updateButtons();
            }
          });

          function getSelected() {
            return Array.from(form.querySelectorAll('.index-checkbox:checked')).map(cb => cb.value);
          }

          function batchAction(action) {
            const indexes = getSelected();
            if (indexes.length === 0) return;

            fetch('/api/batch', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({action: action, indexes: indexes})
            }).then(r => r.json()).then(data => {
              if (action === 'export') {
                const blob = new Blob([JSON.stringify(data.data, null, 2)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'indexes-export.json'; a.click();
                URL.revokeObjectURL(url);
              } else {
                htmx.ajax('GET', '/views/indexes', {target: '#content'});
              }
            });
          }

          document.getElementById('batch-clear').addEventListener('click', () => batchAction('clear'));
          document.getElementById('batch-reindex').addEventListener('click', () => batchAction('reindex'));
          document.getElementById('batch-export').addEventListener('click', () => batchAction('export'));
        })();
        </script>
        HTML;

        return <<<HTML
        {$breadcrumb}
        {$searchForm}
        <form id="index-batch-form">
        <table>
          <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Index Key</th>
            <th>Entries</th>
            <th>Memory</th>
          </tr>
          {$rows}
        </table>
        {$batchActions}
        </form>
        HTML;
    }

    /**
     * @param array<string, string> $query
     */
    public function globalSearch(array $query): string
    {
        $pattern = $query['pattern'] ?? '';

        $breadcrumb = <<<'HTML'
        <div class="breadcrumb">
          <a hx-get="/views/indexes" hx-target="#content" hx-push-url="/">Indexes</a>
          <span class="sep">›</span>
          Search
        </div>
        HTML;

        $encodedPattern = $this->e($pattern);

        $searchForm = <<<HTML
        <form class="search-form" hx-get="/views/search" hx-target="#content" hx-push-url="true">
          <input type="text" name="pattern" placeholder="Search across all indexes..."
                 value="{$encodedPattern}"
                 hx-get="/views/search" hx-target="#content" hx-trigger="keyup changed delay:300ms" hx-push-url="true">
          <button type="submit">Search</button>
        </form>
        <div class="hint">Wildcards are added automatically. Type any part of a key to search.</div>
        HTML;

        if ($pattern === '') {
            return $breadcrumb . $searchForm . '<div class="empty">Enter a search query.</div>';
        }

        // Auto-wrap with wildcards if no glob chars
        $searchPattern = $pattern;
        if (!str_contains($searchPattern, '*') && !str_contains($searchPattern, '?')) {
            $searchPattern = '*' . $searchPattern . '*';
        }

        $results = [];
        foreach ($this->storage->searchAll($searchPattern, 100) as $hit) {
            $results[] = $hit;
        }

        if ($results === []) {
            return $breadcrumb . $searchForm . '<div class="empty">No results found.</div>';
        }

        $rows = '';
        foreach ($results as $i => $hit) {
            $num = $i + 1;
            $encodedKey = $this->e($hit['key']);
            $encodedIndex = $this->e($hit['index']);
            $urlIndex = urlencode($hit['index']);
            $urlKey = urlencode($hit['key']);
            $rows .= <<<HTML
            <tr class="clickable" hx-get="/views/indexes/{$urlIndex}/entries/{$urlKey}" hx-target="#content" hx-push-url="/indexes/{$urlIndex}/entries/{$urlKey}">
              <td class="num">{$num}</td>
              <td class="key">{$encodedKey}</td>
              <td><span class="index-tag">{$encodedIndex}</span></td>
              <td class="uri">{$this->e($hit['uri'])}</td>
            </tr>
            HTML;
        }

        $count = count($results);

        return <<<HTML
        {$breadcrumb}
        {$searchForm}
        <table>
          <tr><th>#</th><th>Key</th><th>Index</th><th>URI</th></tr>
          {$rows}
        </table>
        <div class="pagination"><span class="info">{$count} results</span></div>
        HTML;
    }

    /**
     * @param array<string, string> $query
     */
    public function keysList(string $indexName, array $query = []): string
    {
        $pattern = $query['pattern'] ?? '';
        $limit = max(1, (int) ($query['limit'] ?? self::DEFAULT_LIMIT));
        $offset = max(0, (int) ($query['offset'] ?? 0));

        // Auto-wrap with wildcards if no glob chars
        $searchPattern = $pattern;
        if ($searchPattern !== '' && !str_contains($searchPattern, '*') && !str_contains($searchPattern, '?')) {
            $searchPattern = '*' . $searchPattern . '*';
        }

        $keys = [];
        foreach ($this->storage->read($indexName) as $entry) {
            if ($searchPattern !== '' && !fnmatch($searchPattern, $entry->key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
                continue;
            }
            $keys[] = $entry->key;
        }

        $total = count($keys);
        $pageKeys = array_slice($keys, $offset, $limit);

        $encodedIndex = $this->e($indexName);
        $urlIndex = urlencode($indexName);
        $encodedPattern = $this->e($pattern);

        $breadcrumb = <<<HTML
        <div class="breadcrumb">
          <a hx-get="/views/indexes" hx-target="#content" hx-push-url="/">Indexes</a>
          <span class="sep">›</span>
          {$encodedIndex}
        </div>
        HTML;

        $searchForm = <<<HTML
        <form class="search-form" hx-get="/views/indexes/{$urlIndex}/keys" hx-target="#content" hx-push-url="true">
          <input type="text" name="pattern" placeholder="Filter keys (e.g. Controller, Service...)" value="{$encodedPattern}"
                 hx-get="/views/indexes/{$urlIndex}/keys" hx-target="#content" hx-trigger="keyup changed delay:300ms" hx-push-url="true">
          <button type="submit">Search</button>
        </form>
        <div class="hint">Wildcards are added automatically. Type any part of a key to search.</div>
        HTML;

        if ($pageKeys === []) {
            return $breadcrumb . $searchForm . '<div class="empty">No entries found.</div>';
        }

        $rows = '';
        foreach ($pageKeys as $i => $key) {
            $num = $offset + $i + 1;
            $encodedKey = $this->e($key);
            $urlKey = urlencode($key);
            $rows .= <<<HTML
            <tr class="clickable" hx-get="/views/indexes/{$urlIndex}/entries/{$urlKey}" hx-target="#content" hx-push-url="/indexes/{$urlIndex}/entries/{$urlKey}">
              <td class="num">{$num}</td>
              <td class="key">{$encodedKey}</td>
            </tr>
            HTML;
        }

        $table = <<<HTML
        <table>
          <tr><th>#</th><th>Key</th></tr>
          {$rows}
        </table>
        HTML;

        $pagination = $this->pagination($urlIndex, $pattern, $offset, $limit, $total);

        return $breadcrumb . $searchForm . $table . $pagination;
    }

    public function entryDetail(string $indexName, string $entryKey): string
    {
        $encodedIndex = $this->e($indexName);
        $urlIndex = urlencode($indexName);
        $encodedKey = $this->e($entryKey);

        $breadcrumb = <<<HTML
        <div class="breadcrumb">
          <a hx-get="/views/indexes" hx-target="#content" hx-push-url="/">Indexes</a>
          <span class="sep">›</span>
          <a hx-get="/views/indexes/{$urlIndex}/keys" hx-target="#content" hx-push-url="/indexes/{$urlIndex}">{$encodedIndex}</a>
          <span class="sep">›</span>
          {$encodedKey}
        </div>
        HTML;

        $entry = $this->storage->find($indexName, $entryKey);

        if ($entry === null) {
            return $breadcrumb . '<div class="empty">Entry not found.</div>';
        }

        $valueJson = $this->e($this->formatValue($entry->value));
        $uri = $this->e($entry->uri);

        return <<<HTML
        {$breadcrumb}
        <div class="detail">
          <div class="section">
            <div class="label">Key</div>
            <pre>{$encodedKey}</pre>
          </div>
          <div class="section">
            <div class="label">URI</div>
            <pre>{$uri}</pre>
          </div>
          <div class="section">
            <div class="label">Value</div>
            <pre>{$valueJson}</pre>
          </div>
        </div>
        HTML;
    }

    private function pagination(string $urlIndex, string $pattern, int $offset, int $limit, int $total): string
    {
        $patternParam = $pattern !== '' ? '&pattern=' . urlencode($pattern) : '';

        $prevOffset = max(0, $offset - $limit);
        $nextOffset = $offset + $limit;

        $from = $offset + 1;
        $to = min($offset + $limit, $total);

        $prevBtn = $offset > 0
            ? "<a hx-get=\"/views/indexes/{$urlIndex}/keys?offset={$prevOffset}&limit={$limit}{$patternParam}\" hx-target=\"#content\" hx-push-url=\"true\">← Prev</a>"
            : '<span class="btn-disabled">← Prev</span>';

        $nextBtn = $nextOffset < $total
            ? "<a hx-get=\"/views/indexes/{$urlIndex}/keys?offset={$nextOffset}&limit={$limit}{$patternParam}\" hx-target=\"#content\" hx-push-url=\"true\">Next →</a>"
            : '<span class="btn-disabled">Next →</span>';

        return <<<HTML
        <div class="pagination">
          {$prevBtn}
          <span class="info">{$from}–{$to} of {$total}</span>
          {$nextBtn}
        </div>
        HTML;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    private function formatValue(mixed $value): string
    {
        $serialized = $this->serializeValue($value);

        return (string) json_encode($serialized, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function serializeValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return array_map($this->serializeValue(...), $value);
        }

        if (is_object($value)) {
            $result = ['__class' => $value::class];
            $ref = new \ReflectionObject($value);

            foreach ($ref->getProperties() as $property) {
                $result[$property->getName()] = $this->serializeValue($property->getValue($value));
            }

            return $result;
        }

        return '(' . get_debug_type($value) . ')';
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
