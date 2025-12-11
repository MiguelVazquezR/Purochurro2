<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

dayjs.locale('es-mx');

const props = defineProps({
    settlements: Array, // Array con los cálculos del PayrollService
    grandTotal: Number,
    startDate: String,
    endDate: String,
    isClosed: Boolean
});

// Formateadores
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value || 0);
};

const formatDate = (date) => dayjs(date).format('D MMM YYYY');

// Formulario para cierre (acción POST)
const form = useForm({
    start_date: props.startDate,
    mark_as_paid: true
});

const closePayroll = () => {
    form.post(route('payroll.store-settlement'), {
        onSuccess: () => {
            // Opcional: Mostrar toast o redirigir
        }
    });
};
</script>

<template>
    <AppLayout title="Pre-Nómina">

        <Head title="Cálculo de Nómina" />

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">

            <!-- Encabezado con Botón de Regreso -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('payroll.week', startDate)"
                        class="size-9 flex items-center justify-center bg-white p-2 rounded-full shadow-sm hover:shadow-md transition-shadow border border-gray-200 text-gray-600 hover:text-orange-600">
                        <i class="pi pi-arrow-left"></i>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Pre-Nómina Semanal</h1>
                        <p class="text-sm text-gray-500">
                            Periodo: <span class="font-semibold">{{ formatDate(startDate) }}</span> al <span
                                class="font-semibold">{{ formatDate(endDate) }}</span>
                        </p>
                    </div>
                </div>

                <!-- Tarjeta de Total General -->
                <div
                    class="bg-gray-900 text-white px-6 py-3 rounded-xl shadow-lg flex flex-col items-end w-full md:w-auto">
                    <span class="text-xs uppercase tracking-wider opacity-80">Total General a Pagar</span>
                    <span class="text-2xl font-black font-mono">{{ formatCurrency(grandTotal) }}</span>
                </div>
            </div>

            <!-- Lista de Empleados (Tarjetas de Desglose) -->
            <div class="grid grid-cols-1 gap-6">
                <div v-for="item in settlements" :key="item.employee.id"
                    class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">

                    <!-- Cabecera de Empleado -->
                    <div
                        class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-4">
                            <!-- Avatar Placeholder -->
                            <div
                                class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg border border-orange-200 shadow-sm">
                                {{ item.employee.first_name[0] }}{{ item.employee.last_name[0] }}
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">{{ item.employee.first_name }} {{
                                    item.employee.last_name }}</h2>
                                <div class="flex gap-3 text-xs text-gray-500">
                                    <span>Sueldo Base: <strong class="text-gray-700">{{
                                            formatCurrency(item.employee.base_salary) }}</strong></span>
                                    <span class="text-gray-300">|</span>
                                    <span>ID: {{ item.employee.id }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Individual -->
                        <div
                            class="text-right w-full sm:w-auto flex flex-row sm:flex-col justify-between items-center sm:items-end">
                            <span class="text-xs text-gray-500 uppercase font-bold sm:mb-1">Neto a Pagar</span>
                            <span
                                class="text-xl font-black text-emerald-600 font-mono bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-100">{{
                                formatCurrency(item.total_pay) }}</span>
                        </div>
                    </div>

                    <!-- Cuerpo del Desglose -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8 text-sm">

                        <!-- Columna 1: Asistencia y Tiempos -->
                        <div class="space-y-3">
                            <h3
                                class="font-bold text-gray-900 border-b border-gray-200 pb-2 mb-3 flex items-center gap-2">
                                <i class="pi pi-calendar text-gray-400"></i> Asistencia
                            </h3>

                            <div class="flex justify-between items-center py-1">
                                <span class="text-gray-600">Días Trabajados</span>
                                <span class="font-mono font-bold bg-gray-100 px-2 py-0.5 rounded text-gray-800">{{
                                    item.breakdown?.days_worked || 0 }}</span>
                            </div>

                            <!-- Festivos Laborados -->
                            <div v-if="item.breakdown?.holidays_worked > 0"
                                class="flex justify-between items-center py-1">
                                <span class="text-yellow-700 font-medium">Festivos Laborados</span>
                                <span
                                    class="font-mono font-bold bg-yellow-50 text-yellow-800 px-2 py-0.5 rounded border border-yellow-100">+{{
                                    item.breakdown.holidays_worked }}</span>
                            </div>

                            <!-- Vacaciones -->
                            <div v-if="item.breakdown?.vacations > 0" class="flex justify-between items-center py-1">
                                <span class="text-blue-600 font-medium">Vacaciones</span>
                                <span
                                    class="font-mono font-bold bg-blue-50 text-blue-800 px-2 py-0.5 rounded border border-blue-100">{{
                                    item.breakdown.vacations }}</span>
                            </div>

                            <!-- Descansos Festivos -->
                            <div v-if="item.breakdown?.holidays_rest > 0"
                                class="flex justify-between items-center py-1">
                                <span class="text-emerald-600 font-medium">Festivos (Descanso)</span>
                                <span
                                    class="font-mono font-bold bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded border border-emerald-100">{{
                                    item.breakdown.holidays_rest }}</span>
                            </div>
                        </div>

                        <!-- Columna 2: Incidencias Negativas -->
                        <div class="space-y-3">
                            <h3
                                class="font-bold text-gray-900 border-b border-gray-200 pb-2 mb-3 flex items-center gap-2">
                                <i class="pi pi-exclamation-circle text-gray-400"></i> Incidencias
                            </h3>

                            <!-- Faltas -->
                            <div v-if="item.breakdown?.absences > 0" class="flex justify-between items-center py-1">
                                <span class="text-red-600 font-medium">Faltas Injustificadas</span>
                                <span
                                    class="font-mono font-bold bg-red-50 text-red-800 px-2 py-0.5 rounded border border-red-100">-{{
                                    item.breakdown.absences }}</span>
                            </div>

                            <!-- Retardos -->
                            <div v-if="item.breakdown?.lates > 0" class="flex justify-between items-center py-1">
                                <span class="text-orange-600 font-medium">Retardos</span>
                                <span
                                    class="font-mono font-bold bg-orange-50 text-orange-800 px-2 py-0.5 rounded border border-orange-100">{{
                                    item.breakdown.lates }}</span>
                            </div>

                            <!-- Permisos sin goce -->
                            <div v-if="item.breakdown?.permissions > 0" class="flex justify-between items-center py-1">
                                <span class="text-gray-500">Permisos s/goce</span>
                                <span class="font-mono font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{
                                    item.breakdown.permissions }}</span>
                            </div>

                            <!-- Incapacidades -->
                            <div v-if="item.breakdown?.incapacity > 0" class="flex justify-between items-center py-1">
                                <span class="text-purple-600 font-medium">Incapacidades</span>
                                <span
                                    class="font-mono font-bold bg-purple-50 text-purple-800 px-2 py-0.5 rounded border border-purple-100">{{
                                    item.breakdown.incapacity }}</span>
                            </div>

                            <div v-if="!item.breakdown?.absences && !item.breakdown?.lates && !item.breakdown?.permissions && !item.breakdown?.incapacity"
                                class="text-gray-400 italic text-xs py-2 bg-gray-50 rounded text-center">
                                Sin incidencias registradas
                            </div>
                        </div>

                        <!-- Columna 3: Percepciones Extra (Bonos) -->
                        <div class="space-y-3">
                            <h3
                                class="font-bold text-gray-900 border-b border-gray-200 pb-2 mb-3 flex items-center gap-2">
                                <i class="pi pi-wallet text-gray-400"></i> Bonos y Extras
                            </h3>

                            <div class="flex justify-between items-center py-1">
                                <span class="text-gray-600">Total Bonos</span>
                                <span class="font-mono font-bold text-emerald-600">{{ formatCurrency(item.total_bonuses)
                                    }}</span>
                            </div>

                            <!-- Desglose de bonos específicos -->
                            <div v-if="item.breakdown?.bonuses && item.breakdown.bonuses.length > 0"
                                class="mt-2 pl-3 border-l-2 border-emerald-100 space-y-2">
                                <div v-for="(bonus, idx) in item.breakdown.bonuses" :key="idx"
                                    class="flex justify-between items-center text-xs text-gray-600">
                                    <div class="flex items-center gap-1">
                                        <!-- Íconos indicadores de tipo de bono -->
                                        <i v-if="bonus.type === 'manual'" 
                                           class="pi pi-user-edit text-[10px] text-blue-500" 
                                           v-tooltip.top="'Manual'"></i>
                                        <i v-else-if="bonus.type === 'recurring_rule'" 
                                           class="pi pi-cog text-[10px] text-purple-500" 
                                           v-tooltip.top="'Calculado por Regla'"></i>
                                        <i v-else 
                                           class="pi pi-refresh text-[10px] text-orange-500" 
                                           v-tooltip.top="'Recurrente Fijo'"></i>
                                        
                                        <span>{{ bonus.name }}</span>
                                    </div>
                                    <span class="font-medium">{{ formatCurrency(bonus.amount) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-gray-400 italic text-xs py-2 bg-gray-50 rounded text-center">
                                Sin bonos asignados
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Acciones Finales (Sticky Bottom opcional o fijo al final) -->
            <div class="mt-8">
                <div v-if="!isClosed" class="flex justify-end">
                    <div
                        class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex flex-col sm:flex-row items-center gap-5 max-w-3xl shadow-sm">
                        <div class="bg-yellow-100 p-3 rounded-full shrink-0">
                            <i class="pi pi-exclamation-triangle text-yellow-600 text-2xl"></i>
                        </div>
                        <div class="text-center sm:text-left">
                            <h4 class="font-bold text-yellow-900 text-lg">Confirmación de Cierre</h4>
                            <p class="text-sm text-yellow-800 mb-4 sm:mb-0 max-w-lg">
                                Al cerrar la nómina se generarán los recibos oficiales y se acumularán las vacaciones
                                correspondientes.
                                <strong>Esta acción no se puede deshacer.</strong>
                            </p>
                        </div>
                        <Button label="Cerrar Nómina" icon="pi pi-check-circle" @click="closePayroll"
                            :loading="form.processing"
                            class="!bg-gray-900 !border-gray-900 hover:!bg-black whitespace-nowrap w-full sm:w-auto shadow-md" />
                    </div>
                </div>

                <div v-else
                    class="p-6 bg-green-50 border border-green-200 rounded-xl flex items-center justify-center gap-3 text-green-800 font-bold shadow-sm">
                    <i class="pi pi-check-circle text-2xl"></i>
                    <span>Esta nómina ya ha sido cerrada y procesada correctamente.</span>
                </div>
            </div>

        </div>
    </AppLayout>
</template>