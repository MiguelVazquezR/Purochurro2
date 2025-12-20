<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx';

dayjs.locale('es-mx');

const props = defineProps({
    days: Array,
    startDate: String,
    endDate: String,
    employee: Object,
    payrollData: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};

const formatDateShort = (dateStr) => {
    return dayjs(dateStr).format('D MMM');
};

const dateRangeLabel = computed(() => {
    const start = dayjs(props.startDate);
    const end = dayjs(props.endDate);
    if (start.month() === end.month()) {
        return `${start.format('D')} - ${end.format('D [de] MMM YYYY')}`;
    }
    return `${start.format('D [de] MMM')} - ${end.format('D [de] MMM YYYY')}`;
});

// --- Detección de Periodo Futuro ---
// Si la fecha de inicio de la semana es posterior al día de hoy, consideramos que es un periodo futuro/no abierto.
const isFuturePeriod = computed(() => {
    return dayjs(props.startDate).isAfter(dayjs(), 'day');
});

const prevWeek = () => {
    const prev = dayjs(props.startDate).subtract(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', prev));
};

const nextWeek = () => {
    const next = dayjs(props.startDate).add(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', next));
};

// Navegar a la semana actual (Forzamos el inicio en Domingo para coincidir con Carbon::SUNDAY del backend)
const goToCurrentWeek = () => {
    const currentSunday = dayjs().day(0).format('YYYY-MM-DD');
    router.visit(route('payroll.week', currentSunday));
};

const formatTime12h = (time24h) => {
    if (!time24h) return '--:--';
    return dayjs(`2000-01-01 ${time24h}`).format('hh:mm A');
};

const getDayClass = (day) => {
    if (day.holiday_data) {
        if (day.check_in) return 'bg-yellow-50 text-yellow-700 border-yellow-200 ring-1 ring-yellow-200';
        return 'bg-emerald-50 text-emerald-700 border-emerald-100';
    }
    switch (day.incident_type) {
        case 'falta_injustificada': return 'bg-red-50 text-red-700 border-red-100';
        case 'vacaciones': return 'bg-blue-50 text-blue-700 border-blue-100';
        case 'descanso': return 'bg-gray-100 text-gray-500 border-gray-200';
        case 'incapacidad_general':
        case 'incapacidad_trabajo': return 'bg-purple-50 text-purple-700 border-purple-100';
        case 'permiso_con_goce':
        case 'permiso_sin_goce': return 'bg-indigo-50 text-indigo-800 border-indigo-100';
        case 'asistencia': return day.check_in ? 'bg-white border-surface-200' : 'bg-surface-50 border-surface-100';
        default: return day.is_rest_day ? 'bg-gray-50 border-gray-200' : 'bg-white border-surface-200';
    }
};

const shouldShowTimes = (day) => {
    if (!day.check_in) return false;
    if (day.holiday_data) return true;
    if (!day.incident_type || day.incident_type === 'asistencia') return true;
    return false;
};

// Acceso directo a datos
const bd = computed(() => props.payrollData?.breakdown || {});
const totals = computed(() => props.payrollData?.breakdown?.totals_breakdown || {});
const commissionsTotal = computed(() => props.payrollData?.breakdown?.commissions_total || props.payrollData?.totals_breakdown?.commissions || 0);
const commissionsList = computed(() => props.payrollData?.breakdown?.commissions || []);

const getHolidayWorkedAmount = () => {
    if (totals.value.salary_holidays_worked !== undefined) return totals.value.salary_holidays_worked;
    return totals.value.salary_holidays || (bd.value.holidays_worked * props.employee.base_salary * 3); 
};

const getHolidayRestAmount = () => {
    if (totals.value.salary_holidays_rest !== undefined) return totals.value.salary_holidays_rest;
    return bd.value.holidays_rest * props.employee.base_salary;
};
</script>

<template>
    <AppLayout title="Mi Nómina">
        <div class="max-w-3xl mx-auto py-6 px-4 flex flex-col gap-6 pb-20">
            
            <!-- Header Navegación -->
            <div class="bg-white rounded-2xl shadow-sm border border-surface-200 p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-surface-900">Mi semana</h2>
                    <p class="text-sm text-surface-500 capitalize">{{ dateRangeLabel }}</p>
                </div>
                <div class="flex items-center gap-2 bg-surface-50 rounded-full p-1 border border-surface-200">
                    <Button icon="pi pi-chevron-left" text rounded @click="prevWeek" />
                    <span class="text-sm font-bold px-2">Semana</span>
                    <Button icon="pi pi-chevron-right" text rounded @click="nextWeek" />
                </div>
            </div>

            <!-- VISTA: PERIODO FUTURO / NO ABIERTO -->
            <div v-if="isFuturePeriod" class="flex flex-col items-center justify-center py-16 text-center bg-surface-50 rounded-3xl border border-dashed border-surface-300">
                <div class="bg-orange-50 p-6 rounded-full shadow-sm mb-6 animate-pulse">
                    <i class="pi pi-calendar-plus !text-5xl text-orange-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-surface-800 mb-2">Periodo aún no disponible</h3>
                <p class="text-surface-500 max-w-md mx-auto mb-8 leading-relaxed">
                    Esta semana corresponde a un periodo futuro que aún no ha iniciado. 
                    La información de tu nómina se generará automáticamente en cuanto comience la semana.
                </p>
                <Button 
                    label="Volver a la nómina en curso" 
                    icon="pi pi-undo" 
                    severity="secondary" 
                    rounded 
                    @click="goToCurrentWeek"
                    class="!px-6"
                />
            </div>

            <!-- VISTA: CONTENIDO NORMAL (Si el periodo es actual o pasado) -->
            <div v-else class="flex flex-col gap-6 animate-fade-in">
                <!-- Resumen Financiero -->
                <div class="bg-gradient-to-r from-surface-900 to-surface-800 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-3 opacity-10"><i class="pi pi-wallet text-9xl"></i></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-surface-300 text-sm font-medium uppercase tracking-wider mb-1">
                                    {{ payrollData.is_closed ? 'Total Neto Recibido' : 'Estimado a Recibir' }}
                                </p>
                                <div class="text-4xl font-black mb-1">{{ formatCurrency(payrollData.total_pay) }}</div>
                                <Tag :value="payrollData.is_closed ? 'Pagado' : 'En Curso'" :severity="payrollData.is_closed ? 'success' : 'info'" class="!text-[10px] uppercase" />
                            </div>
                            <div class="text-right hidden sm:block">
                                <span class="block text-xs uppercase opacity-70">Sueldo Turno</span>
                                <span class="font-bold text-lg">{{ formatCurrency(employee.base_salary) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DESGLOSE DE CONCEPTOS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- Tarjeta 1: Percepciones Base -->
                    <div class="bg-white rounded-2xl shadow-sm border border-surface-200 p-5 space-y-4">
                        <h3 class="font-bold text-surface-900 border-b border-surface-100 pb-2 flex items-center gap-2">
                            <i class="pi pi-money-bill text-green-600"></i> Percepciones Base
                        </h3>
                        
                        <div class="space-y-2 text-sm">
                            <!-- Turnos Normales -->
                            <div class="flex justify-between items-center">
                                <span class="text-surface-600">Turnos Trabajados ({{ bd.days_worked || 0 }})</span>
                                <span class="font-bold text-surface-900">{{ formatCurrency(totals.salary_normal) }}</span>
                            </div>
                            
                            <!-- Festivos Laborados -->
                            <div v-if="bd.holidays_worked > 0" class="flex justify-between items-center">
                                <span class="text-yellow-700">Festivos Laborados ({{ bd.holidays_worked }})</span>
                                <span class="font-bold text-yellow-700">+{{ formatCurrency(getHolidayWorkedAmount()) }}</span>
                            </div>
                            
                            <!-- Festivos Descanso -->
                            <div v-if="bd.holidays_rest > 0" class="flex justify-between items-center">
                                <span class="text-emerald-700">Festivos Descanso ({{ bd.holidays_rest }})</span>
                                <span class="font-bold text-emerald-700">{{ formatCurrency(getHolidayRestAmount()) }}</span>
                            </div>

                            <!-- Incapacidades (Al 60%) -->
                            <div v-if="bd.incapacity > 0" class="flex justify-between items-center">
                                <span class="text-purple-600">Incapacidades (60%)</span>
                                <span class="font-bold text-purple-600">{{ formatCurrency(totals.salary_incapacity || totals.salary_other) }}</span>
                            </div>

                            <!-- Permisos con Goce -->
                            <div v-if="totals.salary_permissions > 0" class="flex justify-between items-center">
                                <span class="text-indigo-600">Permisos con Goce</span>
                                <span class="font-bold text-indigo-600">{{ formatCurrency(totals.salary_permissions) }}</span>
                            </div>

                            <!-- Vacaciones -->
                            <div v-if="bd.vacations > 0" class="flex justify-between items-center">
                                <span class="text-blue-600">Vacaciones ({{ bd.vacations }})</span>
                                <span class="font-bold text-blue-600">{{ formatCurrency(totals.salary_vacations) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Comisiones y Bonos -->
                    <div class="bg-white rounded-2xl shadow-sm border border-surface-200 p-5 space-y-4">
                        <h3 class="font-bold text-surface-900 border-b border-surface-100 pb-2 flex items-center gap-2">
                            <i class="pi pi-star text-orange-500"></i> Comisiones y Extras
                        </h3>

                        <div class="space-y-3 text-sm">
                            <!-- Comisiones Total -->
                            <div class="flex justify-between items-center bg-orange-50 p-2 rounded-lg border border-orange-100">
                                <span class="text-orange-800 font-bold">Total Comisiones</span>
                                <span class="font-black text-orange-600">{{ formatCurrency(commissionsTotal) }}</span>
                            </div>

                            <!-- Desglose Diario de Comisiones -->
                            <div v-if="commissionsList.length > 0" class="max-h-32 overflow-y-auto custom-scrollbar pr-2 space-y-1">
                                <div v-for="(comm, idx) in commissionsList" :key="idx" class="flex justify-between items-center text-xs">
                                    <span class="text-surface-500 flex items-center gap-1">
                                        {{ formatDateShort(comm.date) }}
                                        <Tag v-if="comm.is_double" value="2X" severity="warning" class="!text-[8px] !px-1 !py-0" />
                                    </span>
                                    <span class="text-surface-700 font-medium">{{ formatCurrency(comm.amount) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-surface-400 italic text-center">
                                Sin comisiones esta semana.
                            </div>

                            <!-- Bonos -->
                            <div v-if="bd.bonuses && bd.bonuses.length > 0" class="border-t border-dashed border-surface-200 pt-2 space-y-2">
                                <div v-for="(bonus, idx) in bd.bonuses" :key="idx" class="flex justify-between items-center">
                                    <span class="text-purple-600">{{ bonus.name }}</span>
                                    <span class="font-bold text-purple-600">{{ formatCurrency(bonus.amount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Incidencias Negativas -->
                <div v-if="bd.absences > 0 || bd.lates > 0" class="bg-red-50 rounded-2xl border border-red-100 p-4 flex flex-wrap gap-4 text-sm text-red-800">
                    <div v-if="bd.absences > 0" class="flex items-center gap-2 font-bold">
                        <i class="pi pi-times-circle"></i> {{ bd.absences }} Faltas
                    </div>
                    <div v-if="bd.lates > 0" class="flex items-center gap-2 font-bold">
                        <i class="pi pi-clock"></i> {{ bd.lates }} Retardos
                    </div>
                    <span class="text-xs font-normal ml-auto self-center">Estas incidencias pueden afectar tus bonos.</span>
                </div>

                <!-- Calendario -->
                <h3 class="text-lg font-bold text-surface-900 mt-2">Detalle Diario</h3>
                <div class="grid grid-cols-1 gap-3">
                    <div v-for="day in days" :key="day.date" 
                        class="rounded-xl border p-4 flex items-center justify-between transition-colors relative overflow-hidden"
                        :class="getDayClass(day)"
                    >
                        <div v-if="day.holiday_data && day.check_in"
                            class="absolute top-0 right-0 bg-yellow-400 text-yellow-900 text-[10px] font-black px-2 py-0.5 rounded-bl-lg shadow-sm z-10">
                            {{ day.holiday_data.multiplier || '3.0' }}x PAGO
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-center min-w-[50px]">
                                <span class="block text-xs uppercase font-bold opacity-60">{{ day.day_name.substring(0,3) }}</span>
                                <span class="block text-xl font-black">{{ dayjs(day.date).format('DD') }}</span>
                            </div>
                            <div class="border-l border-black/10 pl-4">
                                <div class="font-bold text-sm">
                                    <span v-if="day.holiday_data" :class="day.check_in ? 'text-yellow-800' : 'text-emerald-700'">
                                        <i class="pi pi-star-fill text-xs mr-1"></i>{{ day.holiday_data.name }}
                                    </span>
                                    <span v-else>{{ day.incident_label }}</span>
                                </div>
                                <!-- Información de Turno y Estado -->
                                <div class="flex items-center gap-2 mt-1">
                                    <div v-if="day.schedule_shift" class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: day.shift_color || '#ccc' }"></div>
                                        <span class="text-xs opacity-70">{{ day.schedule_shift }}</span>
                                    </div>
                                    
                                    <div v-if="day.is_late && day.late_ignored" class="flex items-center gap-1 bg-green-100 px-1.5 py-0.5 rounded text-[10px] text-green-700 font-bold border border-green-200">
                                        <i class="pi pi-check-circle text-[10px]"></i> Justificado
                                    </div>
                                    
                                    <div v-else-if="day.is_late" class="flex items-center gap-1 bg-red-100 px-1.5 py-0.5 rounded text-[10px] text-red-700 font-bold border border-red-200">
                                        <i class="pi pi-clock text-[10px]"></i> Retardo
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right pr-2" v-if="shouldShowTimes(day)">
                            <div class="flex flex-col text-gray-600">
                                <span class="text-xs font-bold mb-0.5">{{ formatTime12h(day.check_in) }}</span>
                                <span class="text-xs font-medium opacity-70">{{ formatTime12h(day.check_out) }}</span>
                            </div>
                        </div>
                        <div v-else class="text-right pr-4">
                            <i v-if="day.is_rest_day && !day.incident_type" class="pi pi-home text-gray-400"></i>
                            <i v-else-if="day.incident_type === 'vacaciones'" class="pi pi-sun text-blue-400"></i>
                            <i v-else class="pi pi-minus text-xs opacity-20"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1; 
}

/* Animación simple para suavizar la entrada del contenido */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
</style>