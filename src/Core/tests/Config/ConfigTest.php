<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Config;

use Maia\Core\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = sys_get_temp_dir() . '/maia_config_' . uniqid('', true);
        mkdir($this->configDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->configDir . '/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        rmdir($this->configDir);
    }

    public function testLoadsConfigFiles(): void
    {
        file_put_contents($this->configDir . '/app.php', '<?php return ["name" => "Maia", "debug" => true];');
        $config = new Config($this->configDir);

        $this->assertEquals('Maia', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
    }

    public function testReturnsDefaultForMissingKey(): void
    {
        $config = new Config($this->configDir);

        $this->assertEquals('default', $config->get('missing.key', 'default'));
        $this->assertNull($config->get('missing.key'));
    }

    public function testReturnsEntireFileConfig(): void
    {
        file_put_contents($this->configDir . '/database.php', '<?php return ["host" => "localhost", "port" => 3306];');
        $config = new Config($this->configDir);

        $result = $config->get('database');
        $this->assertEquals(['host' => 'localhost', 'port' => 3306], $result);
    }
}
