<?php

return static function (array $vars): string {
    $project = $vars['project_name'] ?? 'my-app';

    return <<<MD
# {$project}

## Project Structure
- `app/Controllers` handles HTTP orchestration.
- `app/Services` contains business logic.
- `app/Models` contains ORM models.
- `app/Middleware` contains HTTP middleware.
- `app/Requests` contains form request classes.
- `config` stores runtime configuration.
- `routes/api.php` registers routes.

## Development
- Start dev server: `maia up`
- Create components: `maia create:controller`, `maia create:service`, `maia create:model`, `maia create:middleware`, `maia create:request`, `maia create:migration`, `maia create:test`
- Run migrations: `maia migrate`, `maia migrate:rollback`, `maia migrate:status`
- Inspect app state: `maia routes --json`, `maia describe --json`
- Run tests: `vendor/bin/phpunit`

## Configuration
- Environment values are defined in `.env`.
- Base config files live in `config/*.php`.
MD;
};
