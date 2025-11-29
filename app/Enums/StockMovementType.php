<?php

namespace App\Enums;

enum StockMovementType: string
{
    case TRANSFER = 'transfer';         // Traspaso entre almacenes
    case PURCHASE = 'purchase';         // Compra a proveedor
    case ADJUSTMENT_IN = 'adjustment_in'; // Entrada por ajuste (inventario inicial o corrección)
    case ADJUSTMENT_OUT = 'adjustment_out'; // Salida por ajuste (conteo cíclico)
    case WASTE = 'waste';               // Merma (Caducado, Dañado, Accidente)
    case SALE = 'sale';                 // Venta (Salida por POS)

    public function label(): string
    {
        return match($this) {
            self::TRANSFER => 'Traspaso Interno',
            self::PURCHASE => 'Compra / Entrada',
            self::ADJUSTMENT_IN => 'Ajuste de Entrada (+)',
            self::ADJUSTMENT_OUT => 'Ajuste de Salida (-)',
            self::WASTE => 'Merma / Desperdicio',
            self::SALE => 'Venta',
        };
    }
}