<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Logger;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;

final class LoggerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/llm-lib-test-' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $file = $this->tempDir . '/conversation.json';
        if (is_file($file)) {
            unlink($file);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testSaveConversationWritesPrettyPrintedJson(): void
    {
        $path = $this->tempDir . '/conversation.json';
        $logger = new Logger($path);
        $conversation = new Conversation(logger: $logger);
        $conversation->addMessage(new Message(Role::User, 'hello'));

        self::assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertSame('hello', $decoded['messages'][0]['content']);
        self::assertStringContainsString("\n", (string) file_get_contents($path));
    }
}
