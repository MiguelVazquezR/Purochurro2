<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Aws\Laravel\AwsFacade as AWS; 

class RekognitionService
{
    /** Límite de rostros por llamada a deleteFaces (AWS admite hasta 4096). */
    protected const DELETE_BATCH_SIZE = 1000;

    /** Máximo de rostros por página en listFaces. */
    protected const LIST_PAGE_SIZE = 1000;

    protected $client;
    protected $collectionId;

    public function __construct()
    {
        $this->client = AWS::createClient('rekognition');
        $this->collectionId = config('rekognition.collection_id', 'employees_collection');
    }

    /**
     * Busca un rostro en la colección.
     * Retorna array con 'FaceId' y 'Confidence', o null.
     */
    public function searchFace(string $imageBytes): ?array
    {
        try {
            $result = $this->client->searchFacesByImage([
                'CollectionId'       => $this->collectionId,
                'FaceMatchThreshold' => config('rekognition.confidence_threshold', 80),
                'Image'              => ['Bytes' => $imageBytes],
                'MaxFaces'           => 1,
            ]);

            if (!empty($result['FaceMatches'])) {
                return [
                    'face_id' => $result['FaceMatches'][0]['Face']['FaceId'],
                    'confidence' => $result['FaceMatches'][0]['Similarity']
                ];
            }
            
            return null;

        } catch (AwsException $e) {
            Log::error('Rekognition Search Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Indexa un rostro (Para el registro de empleados).
     */
    public function indexFace(string $imageBytes, string $externalId): ?string
    {
        try {
            // Aseguramos que la colección exista antes de indexar
            $this->ensureCollectionExists();

            $result = $this->client->indexFaces([
                'CollectionId'        => $this->collectionId,
                'DetectionAttributes' => ['DEFAULT'],
                'ExternalImageId'     => (string) $externalId, // AWS pide string, usamos ID empleado
                'Image'               => ['Bytes' => $imageBytes],
                'MaxFaces'            => 1,
                'QualityFilter'       => 'AUTO',
            ]);

            if (!empty($result['FaceRecords'])) {
                return $result['FaceRecords'][0]['Face']['FaceId'];
            }

            Log::warning("Rekognition: No face found in image for Employee ID: $externalId");
            return null;

        } catch (AwsException $e) {
            Log::error('Rekognition Index Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Elimina un rostro de la colección.
     *
     * Es idempotente: si el rostro ya no existe en la colección se considera
     * una eliminación exitosa (el objetivo era dejar de pagar por él).
     */
    public function deleteFace(string $faceId): bool
    {
        try {
            $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds'      => [$faceId],
            ]);
            return true;
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                Log::info("Rekognition: el rostro {$faceId} ya no existía en la colección {$this->collectionId}.");
                return true;
            }

            Log::error('Rekognition Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina varios rostros en lotes.
     *
     * @param  array<int, string>  $faceIds
     * @return int Cantidad de rostros eliminados (o ya inexistentes).
     */
    public function deleteFaces(array $faceIds): int
    {
        $faceIds = array_values(array_unique(array_filter($faceIds)));

        if (empty($faceIds)) {
            return 0;
        }

        $deleted = 0;

        foreach (array_chunk($faceIds, self::DELETE_BATCH_SIZE) as $batch) {
            try {
                $result = $this->client->deleteFaces([
                    'CollectionId' => $this->collectionId,
                    'FaceIds'      => $batch,
                ]);

                $deleted += count($result['DeletedFaces'] ?? $batch);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                    // El lote contiene rostros que ya no existen: se reintenta uno por uno
                    // para no perder los que sí siguen en la colección.
                    foreach ($batch as $faceId) {
                        if ($this->deleteFace($faceId)) {
                            $deleted++;
                        }
                    }
                    continue;
                }

                Log::error('Rekognition Delete Batch Error: ' . $e->getMessage());
            }
        }

        return $deleted;
    }

    /**
     * Lista todos los rostros de la colección (paginado).
     *
     * @return array<int, array{face_id: ?string, external_image_id: ?string, image_id: ?string, confidence: ?float}>
     */
    public function listFaces(): array
    {
        $this->ensureCollectionExists();

        $faces = [];
        $nextToken = null;

        do {
            $params = [
                'CollectionId' => $this->collectionId,
                'MaxResults'   => self::LIST_PAGE_SIZE,
            ];

            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $result = $this->client->listFaces($params);

            foreach ($result['Faces'] ?? [] as $face) {
                $faces[] = [
                    'face_id'           => $face['FaceId'] ?? null,
                    'external_image_id' => $face['ExternalImageId'] ?? null,
                    'image_id'          => $face['ImageId'] ?? null,
                    'confidence'        => $face['Confidence'] ?? null,
                ];
            }

            $nextToken = $result['NextToken'] ?? null;
        } while ($nextToken);

        return $faces;
    }

    /**
     * Helper para inicializar la colección si no existe en AWS.
     */
    public function ensureCollectionExists(): void
    {
        try {
            $this->client->describeCollection(['CollectionId' => $this->collectionId]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                $this->client->createCollection(['CollectionId' => $this->collectionId]);
                Log::info("Colección de Rekognition creada: {$this->collectionId}");
            }
        }
    }
}