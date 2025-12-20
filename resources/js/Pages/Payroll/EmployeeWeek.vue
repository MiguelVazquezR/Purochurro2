<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx';

dayjs.locale('es-mx');

const props = defineProps({
    days: Array,
    startDate: String,
    endDate: String,
    employee: Object,
    receipt: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};

const dateRangeLabel = computed(() => {
    const start = dayjs(props.startDate);
    const end = dayjs(props.endDate);
    if (start.month() === end.month()) {
        return `${start.format('D')} - ${end.format('D MMM YYYY')}`;
    }
    return `${start.format('D MMM')} - ${end.format('D MMM YYYY')}`;
});

const prevWeek = () => {
    const prev = dayjs(props.startDate).subtract(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', prev));
};

const nextWeek = () => {
    const next = dayjs(props.startDate).add(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', next));
};

const formatTime12h = (time24h) => {
    if (!time24h) return '--:--';
    return dayjs(`2000-01-01 ${time24h}`).format('hh:mm A');
};

// --- LOGICA DE COLORES DE INCIDENCIAS ---
// Coincide con la vista de Admin (PayrollAdminShow)
const getDayClass = (day) => {
    // 1. Prioridad: Día Festivo
    if (day.holiday_data) {
        if (day.check_in) {
            // Festivo Laborado: Amarillo
            return 'bg-yellow-50 border-yellow-200 ring-1 ring-yellow-200';
        } else {
            // Festivo Descansado: Esmeralda
            return 'bg-emerald-50 border-emerald-100';
        }
    }

    // 2. Prioridad: Incidencias Específicas
    switch (day.incident_type) {
        case 'falta_injustificada':
            return 'bg-red-50 border-red-100';
        case 'falta_justificada':
            return 'bg-orange-50 border-orange-100';
        case 'vacaciones':
            return 'bg-blue-50 border-blue-100';
        case 'descanso':
            return 'bg-gray-100 border-gray-200';
        case 'incapacidad_general':
        case 'incapacidad_trabajo':
            return 'bg-purple-50 border-purple-100';
        case 'permiso_con_goce':
        case 'permiso_sin_goce':
            return 'bg-indigo-50 border-indigo-100';
        case 'no_laboraba':
            return 'bg-surface-100 border-surface-200 opacity-60';
        case 'asistencia':
            if (day.check_in) return 'bg-white border-surface-200';
            return 'bg-surface-50 border-surface-100'; // Asistencia sin check_in (raro pero posible)
        default:
            // Por defecto (días normales sin registro o descansos programados)
            if (day.is_rest_day) return 'bg-gray-50 border-gray-200';
            return 'bg-white border-surface-200';
    }
};

// --- LÓGICA PARA MOSTRAR HORAS ---
// Regla: No mostrar horas si es incidencia (excepto festivo o asistencia normal)
const shouldShowTimes = (day) => {
    // Si no hay check_in, no mostramos horas (obvio)
    if (!day.check_in) return false;

    // Si es festivo, SIEMPRE mostramos horas si trabajó
    if (day.holiday_data) return true;

    // Si es asistencia normal, mostramos horas
    if (!day.incident_type || day.incident_type === 'asistencia') return true;

    // Para cualquier otra incidencia (vacaciones, incapacidad, falta), ocultamos horas
    // aunque existan datos "basura" en la DB.
    return false;
};
</script>

<template>
    <AppLayout title="Mi Nómina">
        <div class="max-w-3xl mx-auto py-6 px-4 flex flex-col gap-6">
            
            <!-- Header Navegación -->
            <div class="bg-white rounded-2xl shadow-sm border border-surface-200 p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-surface-900">Mi Semanal</h2>
                    <p class="text-sm text-surface-500 capitalize">{{ dateRangeLabel }}</p>
                </div>
                <div class="flex items-center gap-2 bg-surface-50 rounded-full p-1 border border-surface-200">
                    <Button icon="pi pi-chevron-left" text rounded @click="prevWeek" />
                    <span class="text-sm font-bold px-2">Semana</span>
                    <Button icon="pi pi-chevron-right" text rounded @click="nextWeek" />
                </div>
            </div>

            <!-- Resumen de Pago -->
            <div v-if="receipt" class="bg-gradient-to-r from-surface-900 to-surface-800 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute top-0 right-0 p-3 opacity-10">
                    <i class="pi pi-wallet !text-7xl mr-6"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-surface-300 text-sm font-medium uppercase tracking-wider mb-1">Total a recibir</p>
                    <div class="text-4xl font-black mb-4">{{ formatCurrency(receipt.total_pay) }}</div>
                    <div class="flex items-center gap-4 text-sm text-surface-300 border-t border-white/10 pt-4">
                        <div><span class="block text-xs uppercase opacity-70">Días Pagados</span><span class="font-bold text-white">{{ receipt.days_worked }}</span></div>
                        <div><span class="block text-xs uppercase opacity-70">Fecha Pago</span><span class="font-bold text-white">{{ receipt.paid_at ? dayjs(receipt.paid_at).format('DD MMM') : 'Pendiente' }}</span></div>
                    </div>
                </div>
            </div>
            <div v-else class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-center gap-3">
                <i class="pi pi-info-circle text-blue-500 text-xl"></i>
                <div>
                    <p class="font-bold text-blue-800">Periodo en curso</p>
                    <p class="text-sm text-blue-600">Los detalles finales de pago estarán disponibles al cierre.</p>
                </div>
            </div>

            <!-- Lista de Días -->
            <div class="grid grid-cols-1 gap-3">
                <div v-for="day in days" :key="day.date" 
                    class="rounded-xl border p-4 flex items-center justify-between transition-colors relative overflow-hidden"
                    :class="getDayClass(day)"
                >
                    <!-- Badge Festivo Laborado (Esquina Superior Derecha) -->
                    <div v-if="day.holiday_data && day.check_in"
                        class="absolute top-0 right-0 bg-yellow-400 text-yellow-900 text-[10px] font-black px-2 py-0.5 rounded-bl-lg shadow-sm z-10">
                        {{ day.holiday_data.multiplier || '3.0' }}x
                    </div>

                    <!-- Izquierda: Fecha e Info Principal -->
                    <div class="flex items-center gap-4">
                        <div class="text-center min-w-[50px]">
                            <span class="block text-xs uppercase font-bold opacity-60">{{ day.day_name.substring(0,3) }}</span>
                            <span class="block text-xl font-black">{{ dayjs(day.date).format('DD') }}</span>
                        </div>
                        <div class="border-l border-black/10 pl-4">
                            <!-- Etiqueta Principal -->
                            <div class="font-bold text-sm">
                                <!-- Caso Festivo -->
                                <span v-if="day.holiday_data" :class="day.check_in ? 'text-yellow-800' : 'text-emerald-700'">
                                    <i class="pi pi-star-fill text-xs mr-1"></i>{{ day.holiday_data.name }}
                                </span>
                                <!-- Caso Incidencia Especial -->
                                <span v-else-if="day.incident_type && day.incident_type !== 'asistencia'" class="capitalize">
                                    {{ day.incident_label }}
                                </span>
                                <!-- Caso Asistencia / Normal -->
                                <span v-else>
                                    {{ day.incident_label }}
                                </span>
                            </div>
                            
                            <!-- Subtítulo: Horario Programado -->
                            <div v-if="day.schedule_shift" class="flex items-center gap-1.5 mt-1">
                                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: day.shift_color || '#ccc' }"></div>
                                <span class="text-xs opacity-70">{{ day.schedule_shift }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Derecha: Tiempos (Gris Normal) -->
                    <div class="text-right pr-2" v-if="shouldShowTimes(day)">
                        <div class="flex flex-col text-gray-600"> <!-- Color Gris Neutro -->
                            <span class="text-xs font-bold mb-0.5">
                                {{ formatTime12h(day.check_in) }}
                            </span>
                            <span class="text-xs font-medium opacity-70">
                                {{ formatTime12h(day.check_out) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Derecha: Si es descanso o incidencia que oculta horas -->
                    <div v-else class="text-right pr-4">
                        <i v-if="day.is_rest_day && !day.incident_type" class="pi pi-home text-gray-400"></i>
                        <i v-else-if="day.incident_type === 'vacaciones'" class="pi pi-sun text-blue-400"></i>
                        <i v-else-if="day.incident_type && day.incident_type.includes('incapacidad')" class="pi pi-heart-fill text-purple-400"></i>
                        <i v-else class="pi pi-minus text-xs opacity-20"></i>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>