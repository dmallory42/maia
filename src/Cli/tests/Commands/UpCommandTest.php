<?php

declare(strict_types=1);

namespace Maia\Cli\Tests\Commands;

use Maia\Cli\Commands\UpCommand;
use Maia\Cli\Output;
use PHPUnit\Framework\TestCase;

class UpCommandTest extends TestCase
{
    public function testDefaultsToPort8000(): void
    {
        $command = new UpCommand();
        $output = new Output();

        $code = $command->execute(['--dry-run'], $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('localhost:8000', $output->buffer());
    }

    public function testRespectsPortFlag(): void
    {
        $command = new UpCommand();
        $output = new Output();

        $code = $command->execute(['--port', '9001', '--dry-run'], $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('localhost:9001', $output->buffer());
    }

    public function testPointsServerAtPublicIndex(): void
    {
        $command = new UpCommand();
        $output = new Output();

        $command->execute(['--dry-run'], $output);

        $this->assertStringContainsString(' -t public public/index.php', $output->buffer());
    }
}
