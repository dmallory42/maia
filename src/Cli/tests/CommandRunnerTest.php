<?php

declare(strict_types=1);

namespace Maia\Cli\Tests;

use Maia\Cli\Command;
use Maia\Cli\CommandRunner;
use Maia\Cli\Output;
use PHPUnit\Framework\TestCase;

class StubCommand extends Command
{
    /** @var array<int, string> */
    public array $receivedArgs = [];

    public function name(): string
    {
        return 'create:controller';
    }

    public function description(): string
    {
        return 'Create a controller file';
    }

    public function execute(array $args, Output $output): int
    {
        $this->receivedArgs = $args;
        $output->json(['ok' => true, 'args' => $args]);

        return 0;
    }
}

class CommandRunnerTest extends TestCase
{
    public function testParsesCommandNameAndArguments(): void
    {
        $runner = new CommandRunner();
        $command = new StubCommand();
        $runner->register($command);

        $code = $runner->run(['maia', 'create:controller', 'UserController']);

        $this->assertSame(0, $code);
        $this->assertSame(['UserController'], $command->receivedArgs);
    }

    public function testJsonFlagSwitchesOutputToJson(): void
    {
        $runner = new CommandRunner();
        $command = new StubCommand();
        $runner->register($command);

        $runner->run(['maia', 'create:controller', 'UserController', '--json']);

        $output = $runner->lastOutput();
        $this->assertNotNull($output);
        $this->assertStringContainsString('{"ok":true,"args":["UserController"]}', $output->buffer());
    }

    public function testHelpFlagShowsCommandHelpText(): void
    {
        $runner = new CommandRunner();
        $runner->register(new StubCommand());

        $code = $runner->run(['maia', 'create:controller', '--help']);

        $this->assertSame(0, $code);
        $output = $runner->lastOutput();
        $this->assertNotNull($output);
        $this->assertStringContainsString('create:controller', $output->buffer());
        $this->assertStringContainsString('Create a controller file', $output->buffer());
    }

    public function testUnknownCommandReturnsError(): void
    {
        $runner = new CommandRunner();

        $code = $runner->run(['maia', 'nope']);

        $this->assertSame(1, $code);
        $output = $runner->lastOutput();
        $this->assertNotNull($output);
        $this->assertStringContainsString('Unknown command', $output->buffer());
    }
}
