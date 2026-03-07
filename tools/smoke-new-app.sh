#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK_DIR="$(mktemp -d)"
APP_NAME="smoke-app"
APP_DIR="${WORK_DIR}/${APP_NAME}"
PORT="${MAIA_SMOKE_PORT:-8123}"
SERVER_LOG="${WORK_DIR}/server.log"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]] && kill -0 "${SERVER_PID}" 2>/dev/null; then
    kill "${SERVER_PID}" 2>/dev/null || true
    wait "${SERVER_PID}" 2>/dev/null || true
  fi

  rm -rf "${WORK_DIR}"
}

trap cleanup EXIT

cd "${WORK_DIR}"
php "${ROOT_DIR}/bin/maia" new "${APP_NAME}" --no-install >/dev/null

cd "${APP_DIR}"

composer config repositories.maia path "${ROOT_DIR}"
composer config minimum-stability dev
composer config prefer-stable true
composer require maia/framework:* --no-update --no-interaction
composer install --no-interaction --prefer-dist >/dev/null

cp .env.example .env
touch database/database.sqlite

cat <<'PHP' > app/Controllers/HealthController.php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Maia\Core\Http\Response;
use Maia\Core\Routing\Controller;
use Maia\Core\Routing\Route;

#[Controller('/health')]
class HealthController
{
    #[Route('/', method: 'GET')]
    public function show(): Response
    {
        return Response::json(['ok' => true]);
    }
}
PHP

cat <<'PHP' > routes/api.php
<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use Maia\Core\App;

return static function (App $app): void {
    $app->registerController(HealthController::class);
};
PHP

php -S "127.0.0.1:${PORT}" -t public >"${SERVER_LOG}" 2>&1 &
SERVER_PID=$!

for _ in {1..20}; do
  if curl --silent --fail "http://127.0.0.1:${PORT}/health/" >/dev/null 2>&1; then
    break
  fi

  sleep 0.25
done

RESPONSE="$(curl --silent --fail "http://127.0.0.1:${PORT}/health/")"

if [[ "${RESPONSE}" != '{"ok":true}' ]]; then
  echo "Unexpected smoke response: ${RESPONSE}" >&2
  exit 1
fi

echo "Smoke test passed"
