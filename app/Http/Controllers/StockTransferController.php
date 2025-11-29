<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType; // Importante
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockTransferController extends Controller
{
    public function index()
    {
        return Inertia::render('StockTransfer/Index', [
            'locations' => Location::all(),
            'products' => Product::where('is_active', true)
                ->where('track_inventory', true)
                ->orderBy('name')
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_location_id' => 'required|exists:locations,id|different:to_location_id',
            'to_location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $sourceInventory = Inventory::firstOrCreate(
                ['location_id' => $validated['from_location_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            if ($sourceInventory->quantity < $validated['quantity']) {
                return back()->with('error', "Stock insuficiente en origen. Disponible: {$sourceInventory->quantity}");
            }

            $destInventory = Inventory::firstOrCreate(
                ['location_id' => $validated['to_location_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            $sourceInventory->decrement('quantity', $validated['quantity']);
            $destInventory->increment('quantity', $validated['quantity']);

            StockMovement::create([
                'product_id' => $validated['product_id'],
                'from_location_id' => $validated['from_location_id'],
                'to_location_id' => $validated['to_location_id'],
                'quantity' => $validated['quantity'],
                'type' => StockMovementType::TRANSFER, // <-- AGREGADO
                'user_id' => auth()->id(),
                'notes' => $validated['notes']
            ]);

            DB::commit();
            return back()->with('success', 'Traspaso realizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al realizar el traspaso: ' . $e->getMessage());
        }
    }
}