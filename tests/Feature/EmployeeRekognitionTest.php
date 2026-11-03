<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\RekognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifica que el rostro en AWS Rekognition siga el ciclo de vida del empleado:
 * baja, reactivación, cambio de foto y eliminación.
 */
class EmployeeRekognitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // El admin debe ser el usuario ID 1: el controlador valida auth()->id() === 1 para bajas.
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->admin);
    }

    /**
     * Empleado con usuario vinculado y foto de perfil ya guardada en el disco.
     */
    private function createEmployee(?string $faceId = 'face-actual', bool $active = true): Employee
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->updateProfilePhoto(UploadedFile::fake()->image('rostro.jpg'));

        return Employee::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'is_active' => $active,
            'aws_face_id' => $faceId,
        ]);
    }

    /**
     * Payload mínimo válido para employees.update.
     */
    private function updatePayload(Employee $employee, array $extra = []): array
    {
        return array_merge([
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'birth_date' => $employee->birth_date->format('Y-m-d'),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'base_salary' => $employee->base_salary,
            'hired_at' => $employee->hired_at->format('Y-m-d'),
            'email' => $employee->email,
            'is_active' => $employee->is_active,
        ], $extra);
    }

    /**
     * Payload válido para employees.terminate.
     */
    private function terminationPayload(): array
    {
        return [
            'termination_date' => now()->format('Y-m-d'),
            'reason' => 'resignation',
            'notes' => 'Renuncia voluntaria',
        ];
    }

    public function test_terminar_empleado_elimina_su_rostro_de_rekognition()
    {
        $employee = $this->createEmployee('face-baja');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-baja')->andReturnTrue();
        });

        $response = $this->post(route('employees.terminate', $employee), $this->terminationPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
            'aws_face_id' => null,
        ]);

        // La foto de perfil se conserva para el expediente del empleado.
        $this->assertDatabaseHas('users', ['id' => $employee->user_id]);
        $this->assertNotNull($employee->user->fresh()->profile_photo_path);
    }

    public function test_desactivar_desde_edicion_elimina_el_rostro()
    {
        $employee = $this->createEmployee('face-desactivado');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-desactivado')->andReturnTrue();
        });

        $response = $this->put(route('employees.update', $employee), $this->updatePayload($employee, [
            'is_active' => false,
        ]));

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
            'aws_face_id' => null,
        ]);
    }

    public function test_reactivar_empleado_vuelve_a_indexar_su_rostro()
    {
        $employee = $this->createEmployee(null, false);

        $this->mock(RekognitionService::class, function (MockInterface $mock) use ($employee) {
            $mock->shouldReceive('indexFace')
                ->once()
                ->withArgs(fn ($imageBytes, $externalImageId) => is_string($imageBytes)
                    && $imageBytes !== ''
                    && $externalImageId === (string) $employee->user_id)
                ->andReturn('face-reindexado');
            $mock->shouldNotReceive('deleteFace');
        });

        $response = $this->put(route('employees.update', $employee), $this->updatePayload($employee, [
            'is_active' => true,
        ]));

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => true,
            'aws_face_id' => 'face-reindexado',
        ]);
    }

    public function test_cambiar_foto_elimina_el_rostro_anterior_y_guarda_el_nuevo()
    {
        $employee = $this->createEmployee('face-anterior');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-anterior')->andReturnTrue();
            $mock->shouldReceive('indexFace')->once()->andReturn('face-nuevo');
        });

        $response = $this->put(route('employees.update', $employee), $this->updatePayload($employee, [
            'photo' => UploadedFile::fake()->image('nueva-foto.jpg'),
        ]));

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'aws_face_id' => 'face-nuevo',
        ]);
    }

    public function test_quitar_foto_elimina_el_rostro_y_la_foto()
    {
        $employee = $this->createEmployee('face-foto-quitada');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-foto-quitada')->andReturnTrue();
        });

        $response = $this->put(route('employees.update', $employee), $this->updatePayload($employee, [
            'remove_photo' => true,
        ]));

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'aws_face_id' => null,
        ]);
        $this->assertNull($employee->user->fresh()->profile_photo_path);
    }

    public function test_eliminar_empleado_elimina_su_rostro()
    {
        $employee = $this->createEmployee('face-eliminado');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-eliminado')->andReturnTrue();
        });

        $response = $this->delete(route('employees.destroy', $employee));

        $response->assertRedirect();
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_baja_sin_rostro_registrado_no_llama_a_aws()
    {
        $employee = $this->createEmployee(null);

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('deleteFace');
            $mock->shouldNotReceive('indexFace');
        });

        $response = $this->post(route('employees.terminate', $employee), $this->terminationPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
            'aws_face_id' => null,
        ]);
    }

    public function test_un_fallo_de_aws_no_impide_dar_de_baja_al_empleado()
    {
        $employee = $this->createEmployee('face-con-error');

        $this->mock(RekognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteFace')->once()->with('face-con-error')->andReturnFalse();
        });

        $response = $this->post(route('employees.terminate', $employee), $this->terminationPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success', fn ($value) => str_contains($value, 'Advertencia'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
            'aws_face_id' => null,
        ]);
    }
}
