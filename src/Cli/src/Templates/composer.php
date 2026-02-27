<?php

return static function (array $vars): string {
    $projectName = $vars['project_name'] ?? 'my-app';

    $payload = [
        'name' => "app/{$projectName}",
        'type' => 'project',
        'require' => [
            'php' => '^8.2',
            'maia/framework' => '^0.1',
        ],
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
            ],
        ],
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return ($json ?: '{}') . PHP_EOL;
};
