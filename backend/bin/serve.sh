#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8888}"
cd "$ROOT/public"
echo "Ledger PHP API on http://localhost:${PORT}"
exec php -S "0.0.0.0:${PORT}" index.php
