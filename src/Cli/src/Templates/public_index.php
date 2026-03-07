<?php

return static function (array $vars): string {
    return <<<'SCRIPT'
<?php

require __DIR__ . '/../vendor/autoload.php';

use Maia\Core\App;
use Maia\Core\Http\Request;
use Maia\Orm\Connection;
use Maia\Orm\Model;

$app = App::create(__DIR__ . '/../config', __DIR__ . '/../.env');
$database = require __DIR__ . '/../config/database.php';

$connection = new Connection(
    (string) ($database['dsn'] ?? 'sqlite:' . __DIR__ . '/../database/database.sqlite'),
    isset($database['username']) ? (string) $database['username'] : null,
    isset($database['password']) ? (string) $database['password'] : null
);

$app->container()->instance(Connection::class, $connection);
Model::setConnection($connection);

$registerRoutes = require __DIR__ . '/../routes/api.php';
if (is_callable($registerRoutes)) {
    $registerRoutes($app);
}

$request = Request::capture();
$response = $app->handle($request);
$response->send();
SCRIPT;
};
