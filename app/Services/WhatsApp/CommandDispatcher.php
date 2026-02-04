<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Commands\HelpCommand;
use App\Services\WhatsApp\Commands\ResiCommand;
use App\Services\WhatsApp\Commands\RestartWorkerCommand;
use App\Services\WhatsApp\Commands\StatusRequestCommand;
use App\Services\WhatsApp\Commands\StockTransactionCommand;
use App\Services\WhatsApp\Commands\TemperatureInputCommand;
use App\Services\WhatsApp\Commands\WhitelistCommand;
use Illuminate\Support\Facades\Log;

class CommandDispatcher
{
    private array $commands = [];

    // Commands that are allowed for everyone (public access)
    private array $publicCommands = [
        '/resi',
        '/help',
        '/bantuan',
    ];

    public function __construct(
        private GowaClient $client,
        private TemplateService $templateService,
        private WhitelistService $whitelistService,
        private ResiCommand $resiCommand,
        private HelpCommand $helpCommand,
        private TemperatureInputCommand $temperatureCommand,
        private StockTransactionCommand $stockCommand,
        private StatusRequestCommand $statusCommand,
        private RestartWorkerCommand $restartCommand,
        private WhitelistCommand $whitelistCommand,
    ) {
        // Register commands
        $this->commands = [
            '/resi' => $this->resiCommand,
            '/help' => $this->helpCommand,
            '/bantuan' => $this->helpCommand,
            '/suhu' => $this->temperatureCommand,
            '/stok' => $this->stockCommand,
            '/status' => $this->statusCommand,
            '/restart' => $this->restartCommand,
            '/whitelist' => $this->whitelistCommand,
        ];
    }

    public function handle(string $fromJid, string $message): array
    {
        $message = trim($message);

        // Parse command
        $parsed = $this->parseCommand($message);

        if (! $parsed['command']) {
            return [
                'status' => 'ignored',
                'response' => null,
            ];
        }

        $command = $parsed['command'];
        $params = $parsed['params'];

        // Find handler
        $handler = $this->commands[$command] ?? null;

        if (! $handler) {
            $response = $this->templateService->render('command', 'UNKNOWN_COMMAND', [
                'command' => $command,
            ]);
            $this->sendReply($fromJid, $response);

            return [
                'command' => $command,
                'params' => $params,
                'status' => 'unknown_command',
                'response' => $response,
            ];
        }

        // Access Control Check
        if (! $this->isAllowed($fromJid, $command)) {
            $response = $this->templateService->get('command', 'ACCESS_DENIED');
            $this->sendReply($fromJid, $response);

            return [
                'command' => $command,
                'params' => $params,
                'status' => 'access_denied',
                'response' => $response,
            ];
        }

        // Execute command
        try {
            $response = $handler->execute($fromJid, $params);
            $this->sendReply($fromJid, $response);

            return [
                'command' => $command,
                'params' => $params,
                'status' => 'success',
                'response' => $response,
            ];

        } catch (\Throwable $e) {
            Log::error('Command execution error', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            $response = $this->templateService->get('command', 'COMMAND_ERROR');
            $this->sendReply($fromJid, $response);

            return [
                'command' => $command,
                'params' => $params,
                'status' => 'error',
                'response' => $response,
            ];
        }
    }

    private function parseCommand(string $message): array
    {
        // Check if message starts with /
        if (! str_starts_with($message, '/')) {
            return ['command' => null, 'params' => []];
        }

        // Split by whitespace
        $parts = preg_split('/\s+/', $message, 2);
        $command = strtolower($parts[0]);
        $paramsString = $parts[1] ?? '';

        // Parse parameters
        $params = array_filter(explode(' ', trim($paramsString)));

        return [
            'command' => $command,
            'params' => $params,
        ];
    }

    private function isAllowed(string $fromJid, string $command): bool
    {
        // Public commands are always allowed
        if (in_array($command, $this->publicCommands)) {
            return true;
        }

        // Check whitelist for protected commands
        return $this->whitelistService->isAllowed($fromJid);
    }

    private function sendReply(string $toJid, string $message): void
    {
        $this->client->sendMessage($toJid, $message);
    }
}
