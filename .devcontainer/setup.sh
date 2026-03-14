#!/usr/bin/env bash
set -euo pipefail

echo "==> Installing PHP dependencies..."
composer install --no-interaction --prefer-dist

echo "==> Installing VS Code extension dependencies..."
cd client/vscode && npm install && cd ../..

echo "==> Setup complete!"
echo "    Run the LSP server:  php ./bin/lsp serve 'App\\Application' --port=5007"
