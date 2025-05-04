<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DatasetExperimentService
{
    private const ARIZE_API_URL = 'https://api.arize.com/v1/dataset';
    private const SDK_VERSION = '1.0.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $arizeApiKey,
        private readonly string $modelId = 'entreprise_classification',
        private readonly string $spaceId = 'default'
    ) {
    }

    public function createDatasetExperiment(
        string $experimentName,
        array $features,
        array $predictions,
        array $groundTruth = [],
        array $metadata = []
    ): array {
        $payload = [
            'space_key' => $this->spaceId,
            'model_id' => $this->modelId,
            'experiment_name' => $experimentName,
            'dataset' => [
                'features' => $features,
                'predictions' => $predictions,
                'ground_truth' => $groundTruth,
                'metadata' => $metadata
            ]
        ];

        try {
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
                throw new \RuntimeException(sprintf(
                    'Error creating dataset experiment: %s',
                    $response->getContent(false)
                ));
            }

            return json_decode($response->getContent(), true);
        } catch (\Exception $e) {
            error_log(sprintf(
                'Exception while creating dataset experiment: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    public function logBatchPredictions(
        string $experimentName,
        array $batchData
    ): void {
        foreach ($batchData as $data) {
            $this->createDatasetExperiment(
                $experimentName,
                $data['features'] ?? [],
                $data['predictions'] ?? [],
                $data['ground_truth'] ?? [],
                $data['metadata'] ?? []
            );
        }
    }

    public function getExperimentResults(
        string $experimentName,
        array $filters = []
    ): array {
        try {
            $response = $this->httpClient->request('GET', self::ARIZE_API_URL . '/results', [
                'headers' => [
                    'Authorization' => $this->arizeApiKey,
                    'Content-Type' => 'application/json',
                    'Grpc-Metadata-arize-space-id' => $this->spaceId,
                ],
                'query' => [
                    'experiment_name' => $experimentName,
                    'filters' => json_encode($filters)
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(sprintf(
                    'Error fetching experiment results: %s',
                    $response->getContent(false)
                ));
            }

            return json_decode($response->getContent(), true);
        } catch (\Exception $e) {
            error_log(sprintf(
                'Exception while fetching experiment results: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }
} 