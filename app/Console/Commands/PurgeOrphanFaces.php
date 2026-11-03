<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\RekognitionService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PurgeOrphanFaces extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'rekognition:purge-orphan-faces
                            {--delete : Elimina realmente los rostros huérfanos (sin esta opción solo se reportan)}
                            {--collection= : Sobrescribe la colección configurada en config/rekognition.php}
                            {--force : Continúa aunque la base de datos no tenga empleados (peligroso: borraría todos los rostros)}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Reporta y (opcionalmente) elimina de AWS Rekognition los rostros que ya no pertenecen a ningún empleado, para dejar de pagar por ellos.';

    /**
     * Ejecuta el comando.
     */
    public function handle(RekognitionService $rekognition): int
    {
        $collectionOption = $this->option('collection');

        if ($collectionOption) {
            config(['rekognition.collection_id' => $collectionOption]);
            // El servicio lee la colección en el constructor, se reinstancia con el override.
            $rekognition = new RekognitionService();
        }

        $collectionId = config('rekognition.collection_id');
        $this->info('Revisando la colección: ' . $collectionId);

        // Un rostro es válido solo si algún empleado (incluidos los dados de baja)
        // lo tiene registrado en aws_face_id.
        try {
            $totalEmployees = Employee::withTrashed()->count();

            $referencedFaceIds = Employee::withTrashed()
                ->whereNotNull('aws_face_id')
                ->pluck('aws_face_id')
                ->filter()
                ->values()
                ->all();

            $employeesByUserId = $this->employeesByUserId();
        } catch (QueryException $e) {
            $this->error('No se pudo consultar la base de datos (tabla employees).');
            $this->line('Detalle: ' . Str::limit($e->getMessage(), 240));
            $this->line('Revisa DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD en el .env y confirma que el servidor MySQL esté corriendo.');
            Log::error("rekognition:purge-orphan-faces no pudo consultar la base de datos: " . $e->getMessage());

            return Command::FAILURE;
        }

        // Salvaguarda: si la base de datos está vacía (o apunta a la base equivocada)
        // todos los rostros parecerían huérfanos y un --delete vaciaría la colección.
        if ($totalEmployees === 0 && ! $this->option('force')) {
            $this->error('La base de datos no tiene empleados registrados, así que todos los rostros parecerían huérfanos.');
            $this->line('No se eliminó nada. Verifica que apuntas a la base de datos correcta o usa --force para continuar de todas formas.');

            return Command::FAILURE;
        }

        try {
            $faces = $rekognition->listFaces();
        } catch (\Throwable $e) {
            $this->error('No se pudo leer la colección en AWS: ' . $e->getMessage());
            Log::error("rekognition:purge-orphan-faces no pudo leer la colección {$collectionId}: " . $e->getMessage());

            return Command::FAILURE;
        }

        $orphans = [];
        foreach ($faces as $face) {
            if ($face['face_id'] && in_array($face['face_id'], $referencedFaceIds, true)) {
                continue;
            }
            $orphans[] = $face;
        }

        $this->line('Rostros en la colección: ' . count($faces));
        $this->line('Rostros vinculados a un empleado: ' . (count($faces) - count($orphans)));

        if (empty($orphans)) {
            $this->info('No hay rostros huérfanos. Nada por limpiar.');

            return Command::SUCCESS;
        }

        $this->warn('Rostros huérfanos detectados: ' . count($orphans));
        $this->table(
            ['FaceId', 'ExternalImageId (user_id)', 'Empleado', 'Estado'],
            array_map(function (array $face) use ($employeesByUserId) {
                return [
                    $face['face_id'] ?? '—',
                    $face['external_image_id'] ?? '—',
                    $this->describeEmployee($face, $employeesByUserId),
                    $this->describeStatus($face, $employeesByUserId),
                ];
            }, $orphans)
        );

        if (! $this->option('delete')) {
            $this->comment('Modo simulación: no se eliminó nada. Ejecuta con --delete para borrarlos de AWS.');

            return Command::SUCCESS;
        }

        $deleted = $rekognition->deleteFaces(array_column($orphans, 'face_id'));

        $this->info("Rostros eliminados de AWS: {$deleted}");
        Log::info("rekognition:purge-orphan-faces eliminó {$deleted} rostros huérfanos de la colección {$collectionId}.");

        return Command::SUCCESS;
    }

    /**
     * Empleados (incluidos los eliminados) indexados por user_id.
     *
     * @return Collection<int|string, Employee>
     */
    private function employeesByUserId(): Collection
    {
        return Employee::withTrashed()
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'first_name', 'last_name', 'is_active', 'deleted_at'])
            ->keyBy('user_id');
    }

    /**
     * Nombre del empleado al que pertenece el rostro (si se puede determinar).
     *
     * @param  Collection<int|string, Employee>  $employeesByUserId
     */
    private function describeEmployee(array $face, Collection $employeesByUserId): string
    {
        $userId = $face['external_image_id'] ?? null;

        if ($userId === null || ! ctype_digit((string) $userId)) {
            return 'Desconocido';
        }

        $employee = $employeesByUserId->get((int) $userId);

        return $employee ? trim("{$employee->first_name} {$employee->last_name}") : 'Desconocido';
    }

    /**
     * Estado laboral del empleado al que pertenece el rostro.
     *
     * @param  Collection<int|string, Employee>  $employeesByUserId
     */
    private function describeStatus(array $face, Collection $employeesByUserId): string
    {
        $userId = $face['external_image_id'] ?? null;

        if ($userId === null || ! ctype_digit((string) $userId)) {
            return '—';
        }

        $employee = $employeesByUserId->get((int) $userId);

        if (! $employee) {
            return '—';
        }

        if ($employee->deleted_at !== null) {
            return 'Eliminado';
        }

        return $employee->is_active ? 'Activo (rostro duplicado)' : 'Baja';
    }
}
