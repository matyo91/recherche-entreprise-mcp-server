<?php 

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ServiceSubscriberInterface as ServiceServiceSubscriberInterface;

class McpController extends AbstractController implements ServiceServiceSubscriberInterface
{
    private const APP_VERSION = '0.1.0';

    #[Route('/mcp', name: 'mcp_endpoint', methods: ['GET'])]
    public function handleMcp(Request $request): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable nginx buffering

        $response->setCallback(function () use ($request) {
            // Get the request body stream
            $stream = $request->getContent(true);
            
            $buffer = '';
            while (true) {
                // Read from the stream
                $chunk = fread($stream, 8192);
                
                if ($chunk === false || $chunk === '') {
                    // No more data to read
                    break;
                }
                
                $buffer .= $chunk;
                
                // Process complete lines
                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);
                    
                    if (trim($line) !== '') {
                        $this->processLine($line);
                    }
                }
            }
            
            // Process any remaining data in the buffer
            if (trim($buffer) !== '') {
                $this->processLine($buffer);
            }
        });

        return $response;
    }

    private function processLine(string $line): void
    {
        try {
            $payload = json_decode($line, true, JSON_THROW_ON_ERROR);
            $method = $payload['method'] ?? null;
            $id = $payload['id'] ?? 0;

            $response = match ($method) {
                'initialize' => $this->sendInitialize(),
                'tools/list' => $this->sendToolsList(),
                'tools/call' => $this->callTool($payload['params'] ?? []),
                'notifications/initialized' => null,
                default => $this->sendProtocolError(sprintf('Method "%s" not found', $method)),
            };
        } catch (\Throwable $e) {
            $response = $this->sendApplicationError($e);
            $id = $payload['id'] ?? 0 ?? null;
        }

        if (!$response) {
            return;
        }

        $response['id'] = $id;
        $response['jsonrpc'] = '2.0';

        echo "data: " . json_encode($response) . "\n\n";
        ob_flush();
        flush();
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
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        return match ($name) {
            'list_commands' =>  $this->callCommand('list', ['--format' => 'md']),
            'call_command' => $this->callCommand($arguments['command_name'] ?? ''),
            default => $this->sendProtocolError(sprintf('Tool "%s" not found', $name)),
        };
    }

    private function callCommand(string $commandName, array $parameters = []): array
    {
        try {
            return [
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'command',
                        ],
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return $this->sendApplicationError($e);
        }
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