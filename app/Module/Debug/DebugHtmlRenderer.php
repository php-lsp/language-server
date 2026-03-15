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
  .num { color: #8b949e; }

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
  .htmx-indicator { display: none; }
  .htmx-request .htmx-indicator { display: inline; }
  .htmx-request.htmx-indicator { display: inline; }
</style>
</head>
<body>

<div class="header">
  <h1><a hx-get="/views/indexes" hx-target="#content" hx-push-url="/" style="color:#f0f6fc">LSP Debug</a></h1>
  <span class="badge">Index Inspector</span>
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

        if ($stats === []) {
            return $breadcrumb . '<div class="empty">No indexes registered yet.</div>';
        }

        $rows = '';
        foreach ($stats as $info) {
            $encodedKey = $this->e($info['key']);
            $urlKey = urlencode($info['key']);
            $rows .= <<<HTML
            <tr class="clickable" hx-get="/views/indexes/{$urlKey}/keys" hx-target="#content" hx-push-url="/indexes/{$urlKey}">
              <td class="key">{$encodedKey}</td>
              <td class="count">{$info['count']}</td>
            </tr>
            HTML;
        }

        return <<<HTML
        {$breadcrumb}
        <table>
          <tr><th>Index Key</th><th>Entries</th></tr>
          {$rows}
        </table>
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

        $keys = [];
        foreach ($this->storage->read($indexName) as $entry) {
            if ($pattern !== '' && !fnmatch($pattern, $entry->key, \FNM_CASEFOLD | \FNM_NOESCAPE)) {
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
          <input type="text" name="pattern" placeholder="Glob pattern (e.g. App\Controller\*)" value="{$encodedPattern}">
          <button type="submit">Search</button>
        </form>
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
