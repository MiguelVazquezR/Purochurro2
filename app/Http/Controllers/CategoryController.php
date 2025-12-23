<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Category/Index', [
            'categories' => Category::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'color' => ['nullable', 'string', 'max:7'],
        ]);
        $validated['color'] = '#'.$validated['color'];

        Category::create($validated);

        // CAMBIO: Usamos back() para mantener al usuario en el formulario de producto si viene de ahí
        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'color' => ['nullable', 'string', 'max:7'],
        ]);
        $validated['color'] = '#'.$validated['color'];

        $category->update($validated);

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'No se puede eliminar porque tiene productos asociados.');
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}