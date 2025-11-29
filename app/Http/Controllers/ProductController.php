<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Muestra la lista de productos.
     */
    public function index()
    {
        // Obtenemos productos con su imagen (URL de la conversión thumb o la original)
        // Usamos 'map' para formatear la URL de la imagen para el frontend
        $products = Product::orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'price' => $product->price,
                    'employee_price' => $product->employee_price,
                    'cost' => $product->cost,
                    'is_sellable' => $product->is_sellable,
                    'track_inventory' => $product->track_inventory,
                    'is_active' => $product->is_active,
                    'image_url' => $product->getFirstMediaUrl('product_image', 'thumb'), // URL de la imagen
                ];
            });

        return Inertia::render('Product/Index', [
            'products' => $products
        ]);
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'employee_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'is_sellable' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048', // Validación de imagen (2MB max)
        ]);

        $product = Product::create($validated);

        // Manejo de imagen con Spatie Media Library
        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('product_image');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    /**
     * Actualiza un producto existente.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')->ignore($product->id) // Ignorar el ID actual
            ],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'employee_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'is_sellable' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $product->update($validated);

        // Si se sube una nueva imagen, Spatie la reemplaza automáticamente
        // porque definimos ->singleFile() en el modelo Product.
        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('product_image');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Elimina un producto (Soft Delete).
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }
}
