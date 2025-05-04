<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ArizeService
{
    private const ARIZE_API_URL = 'https://api.arize.com/v1/log';
    private const SDK_VERSION = '1.0.0'; // Set your SDK version here

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $arizeApiKey,
        private readonly string $modelId = 'entreprise_classification',
        private readonly string $spaceId = 'default'
    ) {
    }

    private function sanitizeText(string $text): string
    {
        // Remove any problematic characters and ensure proper encoding
        $text = trim($text);
        // Remove control characters and non-printable characters
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        // Ensure proper UTF-8 encoding
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        // Escape any special characters
        $text = addslashes($text);
        return $text;
    }

    private function prepareFeatures(array $input): array
    {
        $features = [];
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $features[$key] = $this->sanitizeText($value);
            } elseif (is_array($value)) {
                $features[$key] = $this->prepareFeatures($value);
            } else {
                $features[$key] = $value;
            }
        }
        return $features;
    }

    public function logPrediction(array $input, array $prediction, array $metrics = []): void
    {
        $payload = [
            'space_key' => $this->spaceId,
            'model_id' => $this->modelId,
            'prediction' => [
                'features' => $this->prepareFeatures($input),
                'prediction_label' => [
                    'score_categorical' => [
                        'category' => [
                            'category' => $this->sanitizeText($prediction['category'] ?? 'default')
                        ],
                        'score_value' => [
                            'value' => (float)($prediction['score'] ?? 1.0)
                        ]
                    ]
                ],
                'tags' => array_map(function($value) {
                    if (is_string($value)) {
                        return $this->sanitizeText($value);
                    } elseif (is_array($value)) {
                        return $this->prepareFeatures($value);
                    }
                    return $value;
                }, $metrics)
            ],
            'environment_params' => [
                'production' => []
            ]
        ];

        try {
            // Convert the payload to JSON and then back to array to ensure proper encoding
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonPayload === false) {
                throw new \RuntimeException('Failed to encode payload to JSON');
            }
            $payload = json_decode($jsonPayload, true);

            $response = $this->httpClient->request('POST', self::ARIZE_API_URL, [
                'headers' => [
                    'Authorization' => $this->arizeApiKey,
                    'Content-Type' => 'application/json',
                    'Grpc-Metadata-arize-space-id' => $this->spaceId,
                    'Grpc-Metadata-arize-interface' => 'stream',
                    'Grpc-Metadata-sdk-language' => 'php',
                    'Grpc-Metadata-language-version' => PHP_VERSION,
                    'Grpc-Metadata-sdk-version' => self::SDK_VERSION,
                ],
                'json' => $payload,
            ]);

            if ($response->getStatusCode() !== 200) {
                // Log the error but don't throw to avoid breaking the main flow
                error_log(sprintf(
                    'Error sending metrics to Arize: %s',
                    $response->getContent(false)
                ));
            }
        } catch (\Exception $e) {
            // Log the error but don't throw to avoid breaking the main flow
            error_log(sprintf(
                'Exception while sending metrics to Arize: %s',
                $e->getMessage()
            ));
        }
    }
} 