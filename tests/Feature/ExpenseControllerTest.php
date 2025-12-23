<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Autenticamos un usuario para todas las pruebas
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_expenses()
    {
        Expense::factory()->count(3)->create();

        $response = $this->get(route('expenses.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Expense/Index')
            ->has('expenses', 3) 
        );
    }

    public function test_can_create_expense()
    {
        $expenseData = [
            'concept' => 'Compra de Hielo',
            'amount' => 150.50,
            'date' => now()->format('Y-m-d'),
            'notes' => 'Urgencia por calor',
        ];

        $response = $this->post(route('expenses.store'), $expenseData);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'concept' => 'Compra de Hielo',
            'amount' => 150.50,
            'notes' => 'Urgencia por calor',
        ]);
    }

    public function test_cannot_create_expense_with_invalid_data()
    {
        // Monto negativo y sin concepto
        $response = $this->post(route('expenses.store'), [
            'amount' => -10,
            'date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['concept', 'amount', 'date']);
    }

    public function test_can_update_expense()
    {
        $expense = Expense::factory()->create([
            'concept' => 'Pago Luz',
            'amount' => 500
        ]);

        $response = $this->put(route('expenses.update', $expense), [
            'concept' => 'Pago Luz (Corregido)',
            'amount' => 550,
            'date' => $expense->date->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('expenses.index'));
        
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'concept' => 'Pago Luz (Corregido)',
            'amount' => 550
        ]);
    }

    public function test_can_delete_expense()
    {
        $expense = Expense::factory()->create();

        $response = $this->delete(route('expenses.destroy', $expense));

        $response->assertRedirect(route('expenses.index'));
        
        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id
        ]);
    }
}