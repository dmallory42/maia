<?php

return static function (array $vars): string {
    return <<<'SCRIPT'
<?php

require __DIR__ . '/../vendor/autoload.php';

use Maia\Core\App;
use Maia\Core\Http\Request;

$app = App::create(__DIR__ . '/../config', __DIR__ . '/../.env');
$registerRoutes = require __DIR__ . '/../routes/api.php';
if (is_callable($registerRoutes)) {
    $registerRoutes($app);
}

$request = Request::capture();
$response = $app->handle($request);
$response->send();
SCRIPT;
};
