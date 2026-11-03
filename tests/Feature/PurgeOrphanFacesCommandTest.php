<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\RekognitionService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifica el comando que limpia los rostros huérfanos de la colección de Rekognition.
 */
class PurgeOrphanFacesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Empleado activo con su rostro vigente en AWS.
        User::factory()->create(['email_verified_at' => now()]); // Usuario ID 1
        $user = User::factory()->create(['email_verified_at' => now()]); // Usuario ID 2
        Employee::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'is_active' => true,
            'aws_face_id' => 'face-vigente',
        ]);
    }

    /**
     * Rostros simulados en la colección: uno vigente y dos huérfanos.
     */
    private function faces(): array
    {
        return [
            ['face_id' => 'face-vigente', 'external_image_id' => '2', 'image_id' => 'img-1', 'confidence' => 99.9],
            ['face_id' => 'face-huerfano', 'external_image_id' => '99', 'image_id' => 'img-2', 'confidence' => 99.9],
            ['face_id' => 'face-viejo', 'external_image_id' => '2', 'image_id' => 'img-3', 'confidence' => 99.9],
        ];
    }

    public function test_reporte_sin_bandera_delete_no_elimina_nada()
    {
        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listFaces')->once()->andReturn($this->faces());
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces')
            ->expectsOutputToContain('Rostros en la colección: 3')
            ->expectsOutputToContain('Rostros vinculados a un empleado: 1')
            ->expectsOutputToContain('Rostros huérfanos detectados: 2')
            ->expectsOutputToContain('Modo simulación')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_con_bandera_delete_elimina_los_rostros_huerfanos()
    {
        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listFaces')->once()->andReturn($this->faces());
            $mock->shouldReceive('deleteFaces')
                ->once()
                ->with(['face-huerfano', 'face-viejo'])
                ->andReturn(2);
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('Rostros eliminados de AWS: 2')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_sin_huerfanos_no_llama_a_eliminar()
    {
        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listFaces')->once()->andReturn([
                ['face_id' => 'face-vigente', 'external_image_id' => '2', 'image_id' => 'img-1', 'confidence' => 99.9],
            ]);
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('No hay rostros huérfanos')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_falla_con_codigo_de_error_si_aws_no_responde()
    {
        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listFaces')->once()->andThrow(new \RuntimeException('sin credenciales'));
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces')
            ->expectsOutputToContain('No se pudo leer la colección en AWS')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_falla_con_mensaje_claro_si_no_hay_conexion_a_la_base_de_datos()
    {
        // Simula una base de datos inaccesible: la tabla consultada no existe.
        Schema::disableForeignKeyConstraints();
        Schema::drop('employees');
        Schema::enableForeignKeyConstraints();

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('listFaces');
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('No se pudo consultar la base de datos')
            ->expectsOutputToContain('DB_HOST')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_aborta_si_la_base_de_datos_no_tiene_empleados()
    {
        Employee::withTrashed()->forceDelete();

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('listFaces');
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('no tiene empleados registrados')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_con_force_continua_aunque_no_haya_empleados()
    {
        Employee::withTrashed()->forceDelete();

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listFaces')->once()->andReturn([
                ['face_id' => 'face-huerfano', 'external_image_id' => '99', 'image_id' => 'img-1', 'confidence' => 99.9],
            ]);
            $mock->shouldNotReceive('deleteFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--force' => true])
            ->expectsOutputToContain('Rostros huérfanos detectados: 1')
            ->assertExitCode(Command::SUCCESS);
    }
}
