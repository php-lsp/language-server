# GitHub Codespaces

GitHub Codespaces allows you to run the PHP Language Server directly in the
browser or in a remote VS Code instance — no local setup required.

## Quick Start

1. Open the repository on GitHub.
2. Click **Code → Codespaces → Create codespace on main** (or any branch).
3. Wait for the container to build and `postCreateCommand` to finish
   (Composer and npm dependencies install automatically).
4. Start the LSP server in the terminal:
   ```shell
   php ./bin/lsp serve 'App\Application' --port=5007
   ```
5. The VS Code extension connects to `127.0.0.1:5007` automatically.

## How It Works

The Codespace is configured by three files:

| File | Purpose |
|------|---------|
| `.devcontainer/Dockerfile` | PHP 8.4 image with Composer, Node.js, git |
| `.devcontainer/devcontainer.json` | Container settings, port forwarding, extension setup |
| `.devcontainer/setup.sh` | Post-create script — installs Composer and npm deps |

Port `5007` is forwarded inside the container and marked as **ignore** for
auto-forward so that Codespaces does not try to open it in a browser tab.

## Auto-Starting the Server

A VS Code task is pre-configured to start the server when the workspace
opens. Check **Terminal → Run Task → Start LSP Server** if it did not start
automatically, or set the task to run on folder open:

1. Open **Command Palette** (`Ctrl+Shift+P` / `Cmd+Shift+P`).
2. Run **Tasks: Manage Automatic Tasks in Folder**.
3. Select **Allow Automatic Tasks in Folder**.
4. Reload the window (`Ctrl+Shift+P` → **Developer: Reload Window**).

The server will now start automatically every time the Codespace opens.

## Manual Connection (If Auto-Connect Fails)

If the VS Code extension does not connect to the server automatically,
follow these steps:

### 1. Verify the Server is Running

Open a terminal in the Codespace and check if the server is listening:

```shell
php ./bin/lsp serve 'App\Application' --port=5007
```

You should see output indicating the server started on `tcp://127.0.0.1:5007`.

### 2. Install the Extension Manually

If the bundled extension did not load:

```shell
cd client/vscode
npm install
```

Then open the **Extensions** sidebar (`Ctrl+Shift+X`), click the `...` menu,
choose **Install from VSIX...**, or press `F5` to launch the extension in
a development host.

### 3. Check Extension Settings

Open **Settings** (`Ctrl+,`) and search for `php-lsp`. Verify:

| Setting | Value |
|---------|-------|
| `php-lsp.host` | `127.0.0.1` |
| `php-lsp.port` | `5007` |
| `php-lsp.enabled` | `true` |

These are pre-configured in `.vscode/settings.json`, but user settings may
override them.

### 4. Restart the Extension

1. Open **Command Palette** (`Ctrl+Shift+P`).
2. Run **php-lsp: Disable Language Server Extension**.
3. Run **php-lsp: Enable Language Server Extension**.

Or reload the window: **Developer: Reload Window**.

### 5. Check the Output Panel

1. Open **Output** panel (`Ctrl+Shift+U`).
2. Select **php-lsp** from the dropdown.
3. Look for connection errors — typically `ECONNREFUSED` means the server
   is not running yet.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `ECONNREFUSED 127.0.0.1:5007` | Server is not running. Start it manually (see step 1 above). |
| Extension not found | Run `cd client/vscode && npm install`, then press `F5`. |
| Port 5007 already in use | Kill the old process: `lsof -ti:5007 \| xargs kill -9`, then restart. |
| Composer install fails | Run `composer install` manually. Check PHP version with `php -v` (must be 8.4+). |
| Container build fails | Rebuild: **Command Palette → Codespaces: Rebuild Container**. |

## Using with the PHAR Build

You can also run the compiled PHAR in a Codespace:

```shell
composer build:prod
php var/prod/build.phar serve 'App\Application' --port=5007
```

This is useful for testing the production build without leaving the
Codespace environment.
