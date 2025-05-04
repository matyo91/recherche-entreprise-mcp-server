<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AstraService
{
    private const ASTRA_API_URL = 'https://%s.apps.astra.datastax.com/api/json/v1';
    private const COLLECTION_NAME = 'entreprises';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiToken,
        private readonly string $datacenterId,
        private readonly string $keyspace,
        private readonly string $token
    ) {
    }

    private function embedData(array $entreprise): array
    {
        $openaiEndpoint = 'https://api.openai.com/v1/embeddings';
        $openaiToken = $this->openAiApiToken;

        // Préparer le texte à embedder (concaténation des champs pertinents)
        $textToEmbed = sprintf(
            '%s %s %s %s %s %s',
            $entreprise['nom_raison_sociale'] ?? '',
            $entreprise['activite_principale'] ?? '',
            $entreprise['categorie_entreprise'] ?? '',
            $entreprise['siege']['adresse'] ?? '',
            $entreprise['siege']['code_postal'] ?? '',
            $entreprise['siege']['libelle_commune'] ?? ''
        );

        $response = $this->httpClient->request('POST', $openaiEndpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $openaiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'input' => $textToEmbed,
                'model' => 'text-embedding-ada-002',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Erreur lors de l\'appel à l\'API OpenAI : %s',
                $response->getContent(false)
            ));
        }

        $data = json_decode($response->getContent(), true);
        $embedding = $data['data'][0]['embedding'];

        // Ajouter l'embedding aux données de l'entreprise
        $entreprise['$vector'] = $embedding;
        return $entreprise;
    }

    /**
     * Insère ou met à jour une ou plusieurs entreprises dans Astra DB (Data API)
     * @param array $entreprises Tableau d'une ou plusieurs entreprises (chaque entreprise = tableau associatif)
     * @throws \Exception
     */
    public function upsertEntreprises(array $entreprises): void
    {
        $endpoint = sprintf(
            '%s/%s/%s',
            sprintf(self::ASTRA_API_URL, $this->datacenterId),
            $this->keyspace,
            self::COLLECTION_NAME
        );

        foreach ($entreprises as $entreprise) {
            // Générer l'embedding pour l'entreprise
            $entrepriseWithEmbedding = $this->embedData($entreprise);

            // Vérifier si l'entreprise existe déjà (par SIREN)
            $filter = ['siren' => $entrepriseWithEmbedding['siren']];
            $findPayload = [
                'findOne' => [
                    'filter' => $filter
                ]
            ];
            $findResponse = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Token' => $this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $findPayload,
            ]);
            $findResult = json_decode($findResponse->getContent(), true);
            $exists = isset($findResult['data']['document']) && $findResult['data']['document'] !== null;

            if ($exists) {
                // Mise à jour si l'entreprise existe
                $updatePayload = [
                    'updateOne' => [
                        'filter' => $filter,
                        'update' => ['$set' => $entrepriseWithEmbedding]
                    ]
                ];
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => [
                        'Token' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $updatePayload,
                ]);
            } else {
                // Insertion si l'entreprise n'existe pas
                $insertPayload = [
                    'insertOne' => [
                        'document' => $entrepriseWithEmbedding
                    ]
                ];
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => [
                        'Token' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $insertPayload,
                ]);
            }

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(sprintf(
                    'Erreur lors de l\'upsert dans Astra : %s',
                    $response->getContent(false)
                ));
            }
        }
    }
} 