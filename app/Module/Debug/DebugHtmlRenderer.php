<?php

declare(strict_types=1);

namespace App\Module\Debug;

final class DebugHtmlRenderer
{
    public function render(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LSP Debug — Index Inspector</title>
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
  .breadcrumb a { color: #58a6ff; }
  .breadcrumb span { margin: 0 6px; }

  .search-bar { display: flex; gap: 8px; margin-bottom: 16px; }
  .search-bar input { flex: 1; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 8px 12px; color: #c9d1d9; font-size: 14px; font-family: monospace; }
  .search-bar input:focus { outline: none; border-color: #58a6ff; }
  .search-bar button { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 8px 16px; color: #c9d1d9; cursor: pointer; font-size: 14px; }
  .search-bar button:hover { background: #30363d; }

  table { width: 100%; border-collapse: collapse; background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
  th { text-align: left; padding: 10px 16px; background: #21262d; color: #8b949e; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #30363d; }
  td { padding: 10px 16px; border-bottom: 1px solid #21262d; font-size: 14px; }
  tr:hover td { background: #1c2128; }
  tr:last-child td { border-bottom: none; }
  td.key { font-family: monospace; color: #ff7b72; }
  td.value { font-family: monospace; color: #7ee787; max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  td.uri { font-family: monospace; color: #8b949e; font-size: 12px; }
  td.count { color: #d2a8ff; font-weight: bold; }
  .clickable { cursor: pointer; }

  .detail { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px; }
  .detail pre { background: #0d1117; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.6; color: #c9d1d9; }
  .detail .label { color: #8b949e; font-size: 12px; text-transform: uppercase; margin-bottom: 4px; }
  .detail .section { margin-bottom: 16px; }

  .pagination { display: flex; gap: 8px; margin-top: 16px; align-items: center; }
  .pagination button { background: #21262d; border: 1px solid #30363d; border-radius: 6px; padding: 6px 12px; color: #c9d1d9; cursor: pointer; }
  .pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
  .pagination .info { color: #8b949e; font-size: 13px; }

  .loading { color: #8b949e; padding: 24px; text-align: center; }
  .error { color: #f85149; padding: 24px; text-align: center; }
  .empty { color: #8b949e; padding: 24px; text-align: center; font-style: italic; }
</style>
</head>
<body>

<div class="header">
  <h1>LSP Debug</h1>
  <span class="badge">Index Inspector</span>
</div>

<div class="container">
  <div class="breadcrumb" id="breadcrumb"></div>
  <div id="content"><div class="loading">Loading...</div></div>
</div>

<script>
const API = '';
let state = { view: 'indexes', index: null, key: null, offset: 0, limit: 50, pattern: '' };

function $(sel) { return document.querySelector(sel); }

async function api(path) {
  const res = await fetch(API + path);
  return res.json();
}

function h(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

function renderBreadcrumb() {
  const bc = $('#breadcrumb');
  let parts = ['<a href="#" onclick="navigate(\'indexes\'); return false">Indexes</a>'];
  if (state.index) {
    parts.push('<span>›</span>');
    parts.push(`<a href="#" onclick="navigate('keys', '${h(state.index)}'); return false">${h(state.index)}</a>`);
  }
  if (state.key) {
    parts.push('<span>›</span>');
    parts.push(`<span>${h(state.key)}</span>`);
  }
  bc.innerHTML = parts.join('');
}

async function navigate(view, index, key) {
  state.view = view;
  state.index = index || null;
  state.key = key || null;
  if (view === 'keys') state.offset = 0;
  renderBreadcrumb();
  await render();
}

async function render() {
  const c = $('#content');
  c.innerHTML = '<div class="loading">Loading...</div>';

  try {
    switch (state.view) {
      case 'indexes': return await renderIndexes(c);
      case 'keys': return await renderKeys(c);
      case 'entry': return await renderEntry(c);
      case 'search': return await renderSearch(c);
    }
  } catch (e) {
    c.innerHTML = `<div class="error">Error: ${h(e.message)}</div>`;
  }
}

async function renderIndexes(c) {
  const data = await api('/api/indexes');
  const entries = Object.values(data);

  if (entries.length === 0) {
    c.innerHTML = '<div class="empty">No indexes registered yet.</div>';
    return;
  }

  let html = '<table><tr><th>Index Key</th><th>Entries</th></tr>';
  for (const idx of entries) {
    html += `<tr class="clickable" onclick="navigate('keys', '${h(idx.key)}')">
      <td class="key">${h(idx.key)}</td>
      <td class="count">${idx.count}</td>
    </tr>`;
  }
  html += '</table>';
  c.innerHTML = html;
}

async function renderKeys(c) {
  const qs = `?limit=${state.limit}&offset=${state.offset}` + (state.pattern ? `&pattern=${encodeURIComponent(state.pattern)}` : '');
  const data = await api(`/api/indexes/${encodeURIComponent(state.index)}/keys${qs}`);

  let html = `<div class="search-bar">
    <input type="text" id="patternInput" placeholder="Glob pattern (e.g. App\\Controller\\*)" value="${h(state.pattern)}"
      onkeydown="if(event.key==='Enter'){state.pattern=$('#patternInput').value;state.offset=0;render();}">
    <button onclick="state.pattern=$('#patternInput').value;state.offset=0;render();">Search</button>
  </div>`;

  if (data.keys.length === 0) {
    html += '<div class="empty">No entries found.</div>';
    c.innerHTML = html;
    return;
  }

  html += '<table><tr><th>#</th><th>Key</th></tr>';
  data.keys.forEach((key, i) => {
    html += `<tr class="clickable" onclick="navigate('entry', '${h(state.index)}', '${h(key)}')">
      <td style="color:#8b949e">${data.offset + i + 1}</td>
      <td class="key">${h(key)}</td>
    </tr>`;
  });
  html += '</table>';

  html += `<div class="pagination">
    <button onclick="state.offset=Math.max(0,state.offset-state.limit);render();" ${data.offset === 0 ? 'disabled' : ''}>← Prev</button>
    <span class="info">${data.offset + 1}–${Math.min(data.offset + data.limit, data.total)} of ${data.total}</span>
    <button onclick="state.offset+=state.limit;render();" ${data.offset + data.limit >= data.total ? 'disabled' : ''}>Next →</button>
  </div>`;

  c.innerHTML = html;
}

async function renderEntry(c) {
  const data = await api(`/api/indexes/${encodeURIComponent(state.index)}/entries/${encodeURIComponent(state.key)}`);

  if (data.error) {
    c.innerHTML = `<div class="error">${h(data.error)}</div>`;
    return;
  }

  c.innerHTML = `<div class="detail">
    <div class="section"><div class="label">Key</div><pre>${h(data.key)}</pre></div>
    <div class="section"><div class="label">URI</div><pre>${h(data.uri)}</pre></div>
    <div class="section"><div class="label">Value</div><pre>${h(JSON.stringify(data.value, null, 2))}</pre></div>
  </div>`;
}

// Init
navigate('indexes');
</script>
</body>
</html>
HTML;
    }
}
