<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    business: Object,
    date: String,
    calculation: Object // Contiene { daily_salary, start_date, end_date, details: [], total }
});

const printDocument = () => {
    window.print();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);
};
</script>

<template>
    <Head title="Recibo de finiquito" />

    <div class="min-h-screen bg-gray-100 p-8 print:p-0 print:bg-white font-serif text-gray-900">
        
        <!-- Barra de herramientas (Pantalla) -->
        <div class="max-w-[21.59cm] mx-auto mb-6 flex justify-between items-center print:hidden">
            <button 
                @click="printDocument" 
                class="bg-green-700 text-white px-4 py-2 rounded shadow hover:bg-green-800 font-sans font-bold flex items-center gap-2"
            >
                <i class="pi pi-print"></i> Imprimir recibo
            </button>
            <span class="text-sm text-gray-500 font-sans">Formato carta</span>
        </div>

        <!-- Documento -->
        <div class="page-content mx-auto bg-white">
            
            <!-- Encabezado -->
            <div class="text-center mb-8 border-b-2 border-black pb-4">
                <h1 class="font-bold text-xl uppercase tracking-wider">{{ business.name }}</h1>
                <h2 class="font-bold text-lg mt-2">RECIBO DE FINIQUITO Y LIQUIDACIÓN</h2>
            </div>

            <div class="text-sm text-right mb-6">
                Zapopan, Jalisco a {{ date }}
            </div>

            <!-- Datos Generales -->
            <div class="mb-8 grid grid-cols-2 gap-y-2 text-sm">
                <div>
                    <span class="font-bold">Colaborador:</span> {{ employee.first_name }} {{ employee.last_name }}
                </div>
                <div>
                    <span class="font-bold">Fecha ingreso:</span> {{ calculation.start_date }}
                </div>
                <div>
                    <span class="font-bold">Puesto:</span> Ayudante General
                </div>
                <div>
                    <span class="font-bold">Fecha baja:</span> {{ calculation.end_date }}
                </div>
                <div>
                    <span class="font-bold">Salario diario:</span> {{ formatCurrency(calculation.daily_salary) }}
                </div>
                <div>
                    <span class="font-bold">Antigüedad:</span> {{ calculation.antiguedad_years }} años
                </div>
            </div>

            <!-- Cuerpo del texto -->
            <p class="text-justify text-sm leading-relaxed mb-6">
                Recibí de la empresa <strong>{{ business.name }}</strong> la cantidad neta de <strong>{{ formatCurrency(calculation.total) }}</strong>, 
                por concepto de pago total y definitivo de mis prestaciones laborales devengadas hasta la fecha de terminación de mi relación de trabajo.
                <br><br>
                Manifiesto expresamente que no se me adeuda cantidad alguna por concepto de salarios, horas extras, vacaciones, prima vacacional, aguinaldo, ni ninguna otra prestación derivada de la Ley Federal del Trabajo o de mi contrato individual de trabajo.
            </p>

            <!-- Tabla de Desglose -->
            <div class="mb-8">
                <h3 class="font-bold uppercase text-sm mb-2 border-b border-gray-300">Desglose de Conceptos</h3>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 print:bg-gray-50">
                            <th class="text-left p-2 border border-gray-300">Concepto</th>
                            <th class="text-center p-2 border border-gray-300">Días / Unidad</th>
                            <th class="text-right p-2 border border-gray-300">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in calculation.details" :key="index">
                            <td class="p-2 border border-gray-300">{{ item.concept }}</td>
                            <td class="text-center p-2 border border-gray-300 text-gray-600">{{ item.days }}</td>
                            <td class="text-right p-2 border border-gray-300">{{ formatCurrency(item.amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="font-bold bg-gray-50 print:bg-transparent">
                            <td colspan="2" class="text-right p-2 border border-gray-300 uppercase">Total neto a pagar</td>
                            <td class="text-right p-2 border border-gray-300 text-base">{{ formatCurrency(calculation.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Firmas -->
            <div class="flex justify-center mt-20 break-inside-avoid">
                <div class="text-center w-1/2">
                    <div class="border-t border-black pt-4">
                        <p class="font-bold uppercase">{{ employee.first_name }} {{ employee.last_name }}</p>
                        <p class="text-sm text-gray-500 mt-1">RECIBÍ DE CONFORMIDAD</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Pantalla */
.page-content {
    width: 21.59cm;
    min-height: 14cm; /* Media carta aprox para recibo simple */
    padding: 2.5cm;
    background-color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Impresión */
@media print {
    @page {
        size: letter;
        margin: 2cm;
    }
    body {
        margin: 0;
        background: white;
    }
    .page-content {
        width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>