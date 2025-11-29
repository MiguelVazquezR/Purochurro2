<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Aws\Laravel\AwsFacade as AWS; 

class RekognitionService
{
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
                'FaceMatchThreshold' => config('rekognition.confidence_threshold', 85),
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

    public function deleteFace(string $faceId): bool
    {
        try {
            $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds'      => [$faceId],
            ]);
            return true;
        } catch (AwsException $e) {
            Log::error('Rekognition Delete Error: ' . $e->getMessage());
            return false;
        }
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