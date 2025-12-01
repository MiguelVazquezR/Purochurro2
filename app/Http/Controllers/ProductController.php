<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
        $products = Product::withSum('inventories as stock', 'quantity')
            ->orderBy('name')
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
                    'stock' => $product->stock ?? 0,
                    'image_url' => $product->getFirstMediaUrl('product_image', 'thumb'),
                ];
            });

        return Inertia::render('Product/Index', [
            'products' => $products
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo producto.
     */
    public function create()
    {
        // Pasamos las categorías ordenadas para el selector
        return Inertia::render('Product/Create', [
            'categories' => Category::orderBy('name')->get()
        ]);
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id', // Validación de categoría
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'employee_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'is_sellable' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $validated['cost'] = $validated['cost'] ?? 0;
        $validated['employee_price'] = $validated['employee_price'] ?? 0;

        $product = Product::create($validated);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('product_image');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product)
    {
        $product->loadSum('inventories as stock', 'quantity');
        $product->load('category');

        return Inertia::render('Product/Show', [
            'product' => array_merge($product->toArray(), [
                'stock' => $product->stock ?? 0,
                'image_url' => $product->getFirstMediaUrl('product_image'),
            ])
        ]);
    }

    public function edit(Product $product)
    {
        return Inertia::render('Product/Edit', [
            'product' => array_merge($product->toArray(), [
                'image_url' => $product->getFirstMediaUrl('product_image'),
            ]),
            // También necesitamos categorías en Edit
            'categories' => Category::orderBy('name')->get()
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'barcode')->ignore($product->id)
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

        $validated['cost'] = $validated['cost'] ?? 0;
        $validated['employee_price'] = $validated['employee_price'] ?? 0;

        $product->update($validated);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')
                ->toMediaCollection('product_image');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }
}