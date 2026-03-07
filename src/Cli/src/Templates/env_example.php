<?php

return static function (array $vars): string {
    return <<<'ENV'
APP_NAME=MaiaApp
APP_ENV=local
APP_DEBUG=true

DB_DSN=sqlite:database/database.sqlite
DB_USERNAME=
DB_PASSWORD=

JWT_SECRET=change-me-please-change-me-please!
API_KEYS=

LOG_LEVEL=info
CORS_ALLOWED_ORIGINS=
ENV;
};
