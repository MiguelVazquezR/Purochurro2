<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx';
import Checkbox from 'primevue/checkbox'; 

dayjs.locale('es-mx');

const props = defineProps({
    payrollData: Array,
    startDate: String,
    endDate: String,
    holidays: { type: Array, default: () => [] }
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

const getHolidayInfo = (dateStr) => {
    if (!props.holidays || props.holidays.length === 0) return null;
    const target = dayjs(dateStr).format('MM-DD');
    return props.holidays.find(h => dayjs(h.date).format('MM-DD') === target);
};

// --- Lógica de Selección ---
const selectedIds = ref([]);

onMounted(() => {
    selectedIds.value = props.payrollData.map(d => d.employee.id);
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

// --- Lógica de Cálculo ---
const getReceiptData = (employeeData) => {
    const baseSalary = parseFloat(employeeData.employee.base_salary) || 0;
    const days = employeeData.days;
    
    let concepts = {
        worked_shifts: { count: 0, amount: 0, label: 'Turnos trabajados' },
        holidays_worked: { count: 0, amount: 0, label: 'Festivos laborados' },
        holidays_rest: { count: 0, amount: 0, label: 'Días festivos (descanso)' },
        vacations: { count: 0, amount: 0, label: 'Vacaciones' },
        bonuses: { count: 0, amount: 0, label: 'Bonos / incentivos' },
        commissions: { count: 0, amount: 250, label: 'Comisiones (ventas)' },
    };

    days.forEach(day => {
        const holiday = getHolidayInfo(day.date);
        let shiftUnits = 0;
        
        if (day.check_in && day.check_out) {
            const start = dayjs(`2000-01-01 ${day.check_in}`);
            let end = dayjs(`2000-01-01 ${day.check_out}`);
            if (end.isBefore(start)) end = end.add(1, 'day');
            const hours = end.diff(start, 'hour', true);
            
            if (hours >= 9) shiftUnits = 2;
            else if (hours > 0) shiftUnits = 1;
        } else if (day.incident_type === 'asistencia' && !day.check_in) {
             shiftUnits = 1;
        }

        if (holiday && shiftUnits > 0) {
            const multiplier = holiday.pay_multiplier || 3;
            concepts.holidays_worked.count += shiftUnits;
            concepts.holidays_worked.amount += (baseSalary * multiplier * shiftUnits);
        } else if (holiday) {
            concepts.holidays_rest.count++;
            concepts.holidays_rest.amount += baseSalary;
        } else if (day.incident_type === 'vacaciones') {
            concepts.vacations.count++;
            concepts.vacations.amount += baseSalary;
        } else if (shiftUnits > 0) {
            concepts.worked_shifts.count += shiftUnits;
            concepts.worked_shifts.amount += (baseSalary * shiftUnits);
        }
    });

    if (employeeData.employee.bonuses) {
        employeeData.employee.bonuses.forEach(bonus => {
            concepts.bonuses.amount += parseFloat(bonus.pivot?.amount || bonus.amount || 0);
        });
        concepts.bonuses.count = employeeData.employee.bonuses.length;
    }

    const totalPay = Object.values(concepts).reduce((acc, curr) => acc + curr.amount, 0);

    return { concepts, totalPay };
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

                    <!-- Data calculada -->
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
                                <h2 class="text-sm font-bold">{{ empData.employee.full_name }}</h2>
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
                                    <tr v-for="(concept, key) in receipt.concepts" :key="key" v-show="concept.amount > 0">
                                        <td class="py-0.5 px-1 text-gray-800">{{ concept.label }}</td>
                                        <td class="py-0.5 px-1 text-center text-gray-500">{{ concept.count > 0 ? concept.count : '-' }}</td>
                                        <td class="py-0.5 px-1 text-right font-mono font-medium text-gray-900">{{ formatCurrency(concept.amount) }}</td>
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
                                    <p class="font-bold text-xs text-gray-900 uppercase truncate">{{ empData.employee.full_name }}</p>
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
        /* Márgenes optimizados */
        margin: 4mm; 
        size: letter portrait;
    }
    
    body {
        background: white;
        -webkit-print-color-adjust: exact;
    }

    /* AJUSTE CLAVE: 88mm en lugar de 90mm.
       3 x 88mm = 264mm.
       Espacio disponible hoja carta: 279mm - 8mm (márgenes) = 271mm.
       Esto deja 7mm de holgura para evitar que se desborde a una nueva página.
    */
    .receipt-container {
        height: 88mm; 
        page-break-inside: avoid;
        overflow: hidden;
        border-bottom: 1px dashed #ccc;
    }
    
    /* Eliminar borde del último de la página */
    .receipt-container:nth-child(3n) {
        border-bottom: none;
    }

    /* CORRECCIÓN DE HOJA EN BLANCO:
       Solo forzar salto de página si NO es el último elemento de la lista.
       Así, si tienes 3, 6 o 9, el último no pedirá una hoja nueva.
    */
    .receipt-container:nth-child(3n):not(:last-child) {
        page-break-after: always;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>