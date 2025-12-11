<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx';
import Checkbox from 'primevue/checkbox'; 

dayjs.locale('es-mx');

const props = defineProps({
    payrollData: Array, // Datos pre-calculados del servicio
    startDate: String,
    endDate: String,
});

const dateRangeLabel = computed(() => {
    const start = dayjs(props.startDate);
    const end = dayjs(props.endDate);
    if (start.month() === end.month()) {
        return `${start.format('D')} - ${end.format('D [de] MMM YYYY')}`;
    }
    return `${start.format('D [de] MMM')} - ${end.format('D [de] MMM YYYY')}`;
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

// --- Lógica de Selección ---
const selectedIds = ref([]);

onMounted(() => {
    if (props.payrollData && props.payrollData.length > 0) {
        selectedIds.value = props.payrollData.map(d => d.employee.id);
    }
});

const allSelected = computed(() => {
    return props.payrollData.length > 0 && selectedIds.value.length === props.payrollData.length;
});

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.payrollData.map(d => d.employee.id);
    }
};

const printableEmployees = computed(() => {
    return props.payrollData.filter(d => selectedIds.value.includes(d.employee.id));
});

// --- Construcción de Datos para Recibo ---
const getReceiptData = (dataItem) => {
    // Recuperamos los objetos de desglose que vienen del Backend
    const breakdown = dataItem.breakdown || {};
    // totals_breakdown viene del nuevo servicio. Si no existe (versión vieja), fallback a objeto vacío.
    const moneyBreakdown = dataItem.totals_breakdown || {}; 
    const baseSalary = parseFloat(dataItem.employee.base_salary) || 0;
    
    // --- Lógica de Importes ---
    // Usamos los totales calculados por el servicio (source of truth) en lugar de recalcular visualmente.

    // 1. Días Normales
    const workedDays = { 
        count: breakdown.days_worked || 0, 
        // Si existe el desglose monetario, úsalo. Si no, cálculo simple fallback.
        amount: moneyBreakdown.salary_normal !== undefined 
            ? moneyBreakdown.salary_normal 
            : (breakdown.days_worked || 0) * baseSalary, 
        label: 'Turnos trabajados' 
    };

    // 2. Festivos (Desglose Laborado vs No Laborado)
    // El servicio nos da el total de dinero de festivos en 'salary_holidays'.
    // Vamos a desglosarlo visualmente para el recibo.
    
    const holidaysRestCount = breakdown.holidays_rest || 0;
    const holidaysWorkedCount = breakdown.holidays_worked || 0;
    const totalHolidayMoney = moneyBreakdown.salary_holidays || 0;

    // A. Festivo No Laborado (Descanso pagado): Generalmente es sueldo base x 1
    const holidaysRestAmount = holidaysRestCount * baseSalary;

    // B. Festivo Laborado: Es el remanente del dinero total de festivos
    // (Esto asegura que la suma de A + B siempre cuadre con el total calculado por el servicio)
    let holidaysWorkedAmount = 0;
    if (holidaysWorkedCount > 0) {
        holidaysWorkedAmount = totalHolidayMoney - holidaysRestAmount;
        // Protección visual por si el cálculo da negativo (raro, solo si configuración cambió)
        if (holidaysWorkedAmount < 0) holidaysWorkedAmount = 0; 
    } else if (holidaysRestCount > 0 && totalHolidayMoney > 0) {
        // Si solo hay descansados, todo el dinero es de ahí (ajuste de precisión)
        // holidaysRestAmount = totalHolidayMoney; 
        // (Opcional, pero arriba ya asignamos baseSalary * count, que es lo estándar)
    }

    const holidaysRest = {
        count: holidaysRestCount,
        amount: holidaysRestAmount,
        label: 'Festivos (Descanso)'
    };

    const holidaysWorked = {
        count: holidaysWorkedCount,
        amount: holidaysWorkedAmount,
        label: 'Festivos Laborados'
    };

    // 3. Vacaciones
    const vacations = { 
        count: breakdown.vacations || 0, 
        amount: moneyBreakdown.salary_vacations !== undefined
            ? moneyBreakdown.salary_vacations
            : (breakdown.vacations || 0) * baseSalary, 
        label: 'Vacaciones' 
    };

    // 4. Otros Conceptos
    const bonusesList = breakdown.bonuses || []; // Bonos reales del servicio
    
    // Comisiones (Visual Frontend - Ejemplo)
    const commissions = { count: 0, amount: 250, label: 'Comisiones (ventas)' }; 
    
    let totalPay = parseFloat(dataItem.total_pay);

    // Sumar comisión default si trabajó (Lógica frontend existente)
    if (workedDays.count > 0) {
        totalPay += commissions.amount;
    } else {
        commissions.amount = 0;
    }

    return { 
        concepts: {
            worked_days: workedDays,
            holidays_rest: holidaysRest,
            holidays_worked: holidaysWorked,
            vacations: vacations,
            bonuses_list: bonusesList,
            commissions: commissions
        }, 
        totalPay 
    };
};

const print = () => window.print();
</script>

<template>
    <div class="bg-gray-100 min-h-screen p-6 print:p-0 print:bg-white text-gray-900 font-sans">
        <Head title="Imprimir Recibos" />

        <!-- Barra de herramientas (No se imprime) -->
        <div class="max-w-4xl mx-auto mb-6 print:hidden sticky top-4 z-50">
            <div class="bg-white p-4 rounded-xl shadow-lg border border-gray-200 flex justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-100" @click="toggleSelectAll">
                        <Checkbox :modelValue="allSelected" binary readonly />
                        <span class="text-base font-bold text-gray-700 select-none">
                            {{ allSelected ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                        </span>
                    </div>
                    <div class="h-6 w-px bg-gray-200"></div>
                    <p class="text-base text-gray-500">
                        <span class="font-bold text-gray-900">{{ printableEmployees.length }}</span> recibos seleccionados
                    </p>
                </div>
                
                <button 
                    @click="print"
                    :disabled="printableEmployees.length === 0"
                    class="bg-gray-900 hover:bg-black disabled:bg-gray-400 text-white px-5 py-2 rounded-lg text-base font-bold flex items-center gap-2 transition-all shadow-md hover:shadow-xl transform active:scale-95"
                >
                    <i class="pi pi-print"></i> Imprimir Selección
                </button>
            </div>
        </div>

        <!-- Área de Recibos -->
        <div class="max-w-3xl mx-auto bg-white shadow-xl print:shadow-none print:max-w-none print:mx-0 print:w-full">
            
            <div v-if="printableEmployees.length === 0" class="p-12 text-center text-gray-400 print:hidden">
                <i class="pi pi-inbox !text-4xl mb-2 opacity-50"></i>
                <p>No hay empleados seleccionados para imprimir.</p>
            </div>

            <!-- Iteramos SOLO los empleados seleccionados -->
            <div class="print:block">
                <div v-for="(empData, index) in printableEmployees" :key="empData.employee.id" 
                     class="receipt-container relative border-b border-dashed border-gray-300 print:border-gray-400 group">
                    
                    <!-- Checkbox de selección individual (Solo pantalla) -->
                    <div class="absolute top-0 right-0 print:hidden z-10">
                         <Checkbox v-model="selectedIds" :value="empData.employee.id" />
                    </div>

                    <!-- Data procesada -->
                    <div :set="receipt = getReceiptData(empData)" 
                         class="p-4 print:p-2 h-full flex flex-col justify-between transition-colors group-hover:bg-gray-50 print:group-hover:bg-transparent">
                        
                        <!-- Encabezado Compacto -->
                        <div class="flex justify-between items-start border-b border-gray-800 pb-1 mb-1">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-100 rounded flex items-center justify-center text-gray-400 print:bg-transparent print:border print:border-gray-200">
                                    <i class="pi pi-building text-base"></i>
                                </div>
                                <div>
                                    <h1 class="text-sm font-black uppercase tracking-wide leading-none">Recibo de nómina</h1>
                                    <p class="text-xs text-gray-600 leading-none mt-0.5">{{ dateRangeLabel }}</p>
                                </div>
                            </div>
                            <div class="text-right leading-none">
                                <h2 class="text-sm font-bold">{{ empData.employee.first_name }} {{ empData.employee.last_name }}</h2>
                                <div class="text-xs text-gray-500 mt-0.5">ID: <span class="font-mono font-bold text-black">{{ empData.employee.id }}</span> | Sueldo Turno: <span class="font-mono font-bold text-black">{{ formatCurrency(empData.employee.base_salary) }}</span></div>
                            </div>
                        </div>

                        <!-- Tabla de Conceptos -->
                        <div class="flex-grow">
                            <table class="w-full text-xs leading-none">
                                <thead class="bg-gray-50 border-y border-gray-200 print:bg-gray-100">
                                    <tr>
                                        <th class="text-left py-0.5 px-1 font-bold text-gray-700">Concepto</th>
                                        <th class="text-center py-0.5 px-1 font-bold text-gray-700 w-10">Cant.</th>
                                        <th class="text-right py-0.5 px-1 font-bold text-gray-700 w-20">Importe</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <!-- Días Trabajados -->
                                    <tr v-if="receipt.concepts.worked_days.count > 0">
                                        <td class="py-0.5 px-1 text-gray-800">Turnos trabajados</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">{{ receipt.concepts.worked_days.count }}</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(receipt.concepts.worked_days.amount) }}</td>
                                    </tr>
                                    
                                    <!-- Festivos NO Laborados (Descanso Pagado) -->
                                    <tr v-if="receipt.concepts.holidays_rest.count > 0">
                                        <td class="py-0.5 px-1 text-gray-800">Festivos (Descanso)</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">{{ receipt.concepts.holidays_rest.count }}</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(receipt.concepts.holidays_rest.amount) }}</td>
                                    </tr>

                                    <!-- Festivos Laborados (Pago Extra) -->
                                    <tr v-if="receipt.concepts.holidays_worked.count > 0">
                                        <td class="py-0.5 px-1 text-gray-800 font-bold">Festivos Laborados</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500 font-bold">{{ receipt.concepts.holidays_worked.count }}</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-bold text-gray-900">{{ formatCurrency(receipt.concepts.holidays_worked.amount) }}</td>
                                    </tr>

                                    <!-- Vacaciones -->
                                    <tr v-if="receipt.concepts.vacations.count > 0">
                                        <td class="py-0.5 px-1 text-gray-800">Vacaciones</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">{{ receipt.concepts.vacations.count }}</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(receipt.concepts.vacations.amount) }}</td>
                                    </tr>

                                    <!-- LISTA DE BONOS REALES -->
                                    <tr v-for="(bonus, idx) in receipt.concepts.bonuses_list" :key="'b'+idx">
                                        <td class="py-0.5 px-1 text-gray-800">{{ bonus.name }}</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">1</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(bonus.amount) }}</td>
                                    </tr>

                                    <!-- Comisiones Default -->
                                    <tr v-if="receipt.concepts.commissions.amount > 0">
                                        <td class="py-0.5 px-1 text-gray-800">Comisiones (Ventas)</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">-</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(receipt.concepts.commissions.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Total -->
                        <div class="flex justify-end mb-2 border-t border-gray-800 pt-0.5 mt-1">
                            <div class="flex gap-2 items-center">
                                <span class="font-bold text-gray-900 uppercase text-[10px]">Total Neto</span>
                                <span class="font-black font-mono text-base text-gray-900 bg-gray-50 px-1 rounded print:bg-transparent leading-none">
                                    {{ formatCurrency(receipt.totalPay) }}
                                </span>
                            </div>
                        </div>

                        <!-- Pie de página -->
                        <div class="mt-auto">
                            <!-- Legal -->
                            <p class="text-xs text-gray-400 italic text-justify mb-2 leading-tight tracking-tight">
                                Recibí a mi entera satisfacción la cantidad indicada, manifestando que no se me adeuda cantidad alguna por salarios, horas extras, séptimo día, festivos ni ninguna otra prestación hasta la fecha.
                            </p>

                            <!-- Firma -->
                            <div class="flex justify-center">
                                <div class="text-center w-40">
                                    <div class="h-px bg-gray-900 w-full mb-0.5"></div>
                                    <p class="font-bold text-xs text-gray-900 uppercase truncate">{{ empData.employee.first_name }} {{ empData.employee.last_name }}</p>
                                    <p class="text-[6px] text-gray-400 uppercase tracking-widest">Firma de conformidad</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 4mm; 
        size: letter portrait;
    }
    
    body {
        background: white;
        -webkit-print-color-adjust: exact;
    }

    .receipt-container {
        height: 88mm; 
        page-break-inside: avoid;
        overflow: hidden;
        border-bottom: 1px dashed #ccc;
    }
    
    .receipt-container:nth-child(3n) {
        border-bottom: none;
    }

    .receipt-container:nth-child(3n):not(:last-child) {
        page-break-after: always;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>