<?php

declare(strict_types=1);

namespace Maia\Core\Tests\Logging;

use Maia\Core\Logging\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/maia_test_' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testWritesJsonLogEntry(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('User created', ['id' => 42]);

        $line = trim((string) file_get_contents($this->logFile));
        $entry = json_decode($line, true);

        $this->assertEquals('info', $entry['level']);
        $this->assertEquals('User created', $entry['message']);
        $this->assertEquals(42, $entry['context']['id']);
        $this->assertArrayHasKey('timestamp', $entry);
    }

    public function testRespectsLogLevel(): void
    {
        $logger = new Logger($this->logFile, 'warning');
        $logger->info('This should be ignored');
        $logger->debug('This too');
        $logger->warning('This should appear');

        $content = (string) file_get_contents($this->logFile);
        $this->assertStringContainsString('This should appear', $content);
        $this->assertStringNotContainsString('This should be ignored', $content);
    }

    public function testAllLogLevels(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');

        $lines = array_filter(explode("\n", trim((string) file_get_contents($this->logFile))));
        $this->assertCount(4, $lines);
    }

    public function testNullChannelDiscards(): void
    {
        $logger = Logger::null();
        $logger->info('This goes nowhere');

        $this->assertTrue(true);
    }

    public function testStderrChannel(): void
    {
        $logger = Logger::stderr('info');

        $this->assertInstanceOf(Logger::class, $logger);
    }
}
