<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Config;

use Maia\Core\Config\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir() . '/maia_test_' . uniqid('', true) . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }

        Env::reset();
    }

    public function testLoadsEnvFile(): void
    {
        file_put_contents($this->envFile, "APP_NAME=Maia\nDEBUG=true\nDB_PORT=3306\n");
        Env::load($this->envFile);

        $this->assertEquals('Maia', Env::get('APP_NAME'));
        $this->assertEquals('true', Env::get('DEBUG'));
        $this->assertEquals('3306', Env::get('DB_PORT'));
    }

    public function testReturnsDefaultForMissingKey(): void
    {
        Env::load($this->envFile);

        $this->assertEquals('fallback', Env::get('MISSING_KEY', 'fallback'));
        $this->assertNull(Env::get('MISSING_KEY'));
    }

    public function testIgnoresCommentsAndBlankLines(): void
    {
        file_put_contents($this->envFile, "# comment\n\nAPP_NAME=Maia\n  # another comment\n");
        Env::load($this->envFile);

        $this->assertEquals('Maia', Env::get('APP_NAME'));
    }

    public function testHandlesQuotedValues(): void
    {
        file_put_contents($this->envFile, "APP_NAME=\"My App\"\nSECRET='s3cret'\n");
        Env::load($this->envFile);

        $this->assertEquals('My App', Env::get('APP_NAME'));
        $this->assertEquals('s3cret', Env::get('SECRET'));
    }
}
