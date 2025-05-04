<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:mcp',
    description: 'Starts an MCP server',
)]
class McpCommand extends Command
{
    private const APP_VERSION = '0.1.0';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buffer = '';

        while (true) {
            $line = fgets(STDIN);
            if (false === $line) {
                usleep(1000);

                continue;
            }
            $buffer .= $line;
            if (str_contains($buffer, "\n")) {
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);
                foreach ($lines as $line) {
                    $this->processLine($output, $line);
                }
            }
        }

        return Command::SUCCESS;
    }

    private function processLine(OutputInterface $output, string $line): void
    {
        try {
            $payload = json_decode($line, true, JSON_THROW_ON_ERROR);

            $method = $payload['method'] ?? null;

            $response = match ($method) {
                // protocols
                'initialize' => $this->sendInitialize(),
                'tools/list' => $this->sendToolsList(),
                'tools/call' => $this->callTool($payload['params']),
                'notifications/initialized' => null,
                default => $this->sendProtocolError(\sprintf('Method "%s" not found', $method)),
            };
        } catch (\Throwable $e) {
            $response = $this->sendApplicationError($e);
        }

        if (!$response) {
            return;
        }

        $response['id'] = $payload['id'] ?? 0;
        $response['jsonrpc'] = '2.0';

        $output->writeln(json_encode($response));
    }

    private function sendInitialize(): array
    {
        return [
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => [
                        'listChanged' => true,
                    ],
                ],
                'serverInfo' => [
                    'name' => 'symfony-app',
                    'version' => self::APP_VERSION,
                ],
            ],
        ];
    }

    private function sendToolsList(): array
    {
        return [
            'result' => [
                'tools' => [
                    [
                        'name' => 'list_commands',
                        'description' => 'List all symfony commands.',
                        'inputSchema' => [
                            'type' => 'object',
                            '$schema' => 'http://json-schema.org/draft-07/schema#',
                        ],
                    ],
                    [
                        'name' => 'call_command',
                        'description' => 'Call a symfony command.',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'command_name' => [
                                    'type' => 'string',
                                ],
                                'parameters' => [
                                    'type' => 'object',
                                    'description' => 'Additional parameters for the command',
                                    'default' => new \stdClass(),
                                ],
                            ],
                            'required' => [
                                'command_name',
                            ],
                            'additionalProperties' => false,
                            '$schema' => 'http://json-schema.org/draft-07/schema#',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function callTool(array $params): array
    {
        $name = $params['name'];
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'list_commands' =>  $this->callCommand('list', ['--format' => 'md']),
            'call_command' => $this->callCommand(
                $arguments['command_name'],
                isset($arguments['parameters']) && is_array($arguments['parameters']) ? $arguments['parameters'] : []
            ),
            default => $this->sendProtocolError(\sprintf('Tool "%s" not found', $name)),
        };
    }

    private function callCommand(string $commandName, array $parameters = []): array
    {
        $command = $this->getApplication()->find($commandName);

        $inputParams = array_merge(['command' => $command], $parameters);
        $input = new ArrayInput($inputParams);
        $output = new BufferedOutput();

        $command->run($input, $output);

        return [
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $output->fetch(),
                    ],
                ],
            ],
        ];
    }

    private function sendProtocolError(string $message): array
    {
        return [
            'error' => [
                'code' => -32601,
                'message' => $message,
            ],
        ];
    }

    private function sendApplicationError(\Throwable $e): array
    {
        return [
            'error' => [
                'code' => -32601,
                'message' => 'Something gone wrong! ' . $e->getMessage(),
            ],
        ];
    }
}