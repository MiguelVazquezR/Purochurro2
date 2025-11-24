<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_shifts()
    {
        Shift::factory()->count(3)->create();

        $response = $this->get(route('shifts.index'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shift/Index')
                ->has('shifts', 3)
            );
    }

    public function test_can_create_shift()
    {
        $data = [
            'name' => 'Matutino',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'color' => '#FF0000',
        ];

        $response = $this->post(route('shifts.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('shifts', [
            'name' => 'Matutino',
            'color' => '#FF0000'
        ]);
    }

    public function test_can_update_shift()
    {
        $shift = Shift::factory()->create();

        $response = $this->put(route('shifts.update', $shift), [
            'name' => 'Editado',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'color' => '#00FF00',
            'is_active' => false
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'name' => 'Editado',
            'is_active' => false
        ]);
    }
}