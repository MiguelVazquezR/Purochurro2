<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import 'dayjs/locale/es-mx'; // Importar locale español

dayjs.locale('es-mx');

const props = defineProps({
    payrollData: Array, // Datos pre-calculados del controlador
    startDate: String,
    endDate: String,
    incidentTypes: Array, // Lista de valores del Enum
    holidays: { type: Array, default: () => [] } // Nueva prop para lista de festivos
});

const toast = useToast();
const search = ref('');

// --- Navegación de Fechas ---
const prevWeek = () => {
    const prev = dayjs(props.startDate).subtract(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', prev));
};

const nextWeek = () => {
    const next = dayjs(props.startDate).add(1, 'week').format('YYYY-MM-DD');
    router.visit(route('payroll.week', next));
};

const dateRangeLabel = computed(() => {
    const start = dayjs(props.startDate);
    const end = dayjs(props.endDate);
    // Ejemplo: 23 nov - 29 nov 2025
    if (start.month() === end.month()) {
        return `${start.format('D')} - ${end.format('D MMM YYYY')}`;
    }
    return `${start.format('D MMM')} - ${end.format('D MMM YYYY')}`;
});

// --- Filtrado Local ---
const filteredEmployees = computed(() => {
    if (!search.value) return props.payrollData;
    const term = search.value.toLowerCase();
    return props.payrollData.filter(item => 
        item.employee.full_name.toLowerCase().includes(term) ||
        item.employee.id.toString().includes(term)
    );
});

// --- Lógica de Festivos ---
// Busca si la fecha actual coincide con algún festivo (ignorando el año)
const getHolidayInfo = (dateStr) => {
    if (!props.holidays || props.holidays.length === 0) return null;
    const target = dayjs(dateStr).format('MM-DD');
    return props.holidays.find(h => dayjs(h.date).format('MM-DD') === target);
};

// --- Cálculo de Totales (Visual) ---
// Actualizado para soportar turnos nocturnos
const calculateTotalHours = (days) => {
    let totalMinutes = 0;
    days.forEach(day => {
        if (day.check_in && day.check_out) {
            const start = dayjs(`2000-01-01 ${day.check_in}`);
            let end = dayjs(`2000-01-01 ${day.check_out}`);
            
            if (end.isBefore(start)) {
                end = end.add(1, 'day');
            }

            const diff = end.diff(start, 'minute');
            if (diff > 0) totalMinutes += diff;
        }
    });
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${h}h ${m}m`;
};

// --- Gestión de Incidencias (Modal) ---
const editDialog = ref(false);
const selectedEmployee = ref(null);
const selectedDay = ref(null);

const form = useForm({
    employee_id: null,
    date: null,
    incident_type: 'asistencia',
    check_in: null,
    check_out: null,
    late_ignored: false,
    admin_notes: '',
});

// Mapa de etiquetas para el select (Replicando el Enum PHP)
const incidentOptions = [
    { label: 'Asistencia normal', value: 'asistencia' },
    { label: 'Falta injustificada', value: 'falta_injustificada' },
    { label: 'Falta justificada', value: 'falta_justificada' },
    { label: 'Retardo (marca auto)', value: 'retardo' }, 
    { label: 'Permiso con goce', value: 'permiso_con_goce' },
    { label: 'Permiso sin goce', value: 'permiso_sin_goce' },
    { label: 'Incapacidad general', value: 'incapacidad_general' },
    { label: 'Incapacidad por trabajo', value: 'incapacidad_trabajo' },
    { label: 'Vacaciones', value: 'vacaciones' },
    { label: 'Día festivo', value: 'dia_festivo' },
    { label: 'Día de descanso', value: 'descanso' },
    { label: 'No laboraba aún', value: 'no_laboraba' },
];

const openEdit = (employeeData, dayData) => {
    selectedEmployee.value = employeeData.employee;
    selectedDay.value = dayData;

    form.employee_id = employeeData.employee.id;
    form.date = dayData.date;
    form.incident_type = dayData.incident_type || 'asistencia';
    form.check_in = dayData.check_in ? dayjs(`2000-01-01 ${dayData.check_in}`).toDate() : null;
    form.check_out = dayData.check_out ? dayjs(`2000-01-01 ${dayData.check_out}`).toDate() : null;
    form.late_ignored = Boolean(dayData.late_ignored);
    form.admin_notes = ''; 

    editDialog.value = true;
};

const saveIncident = () => {
    form.transform((data) => ({
        ...data,
        check_in: data.check_in ? dayjs(data.check_in).format('HH:mm') : null,
        check_out: data.check_out ? dayjs(data.check_out).format('HH:mm') : null,
    })).post(route('payroll.update-day'), {
        preserveScroll: true,
        onSuccess: () => {
            editDialog.value = false;
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Registro de asistencia modificado', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Revisa los datos ingresados', life: 3000 });
        }
    });
};

// Helper para clases CSS de celdas
const getCellClass = (day) => {
    // 1. Prioridad: Verificar si es festivo
    const holiday = getHolidayInfo(day.date);
    
    if (holiday) {
        if (day.check_in) {
            // Festivo Laborado (Estilo especial)
            return 'bg-yellow-50 text-yellow-700 border-yellow-200 hover:bg-yellow-100 ring-1 ring-yellow-200';
        } else {
            // Festivo No Laborado (Estilo estándar de festivo)
            return 'bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100';
        }
    }

    // 2. Si no es festivo, usar lógica normal
    switch (day.incident_type) {
        case 'falta_injustificada': 
            return 'bg-red-50 text-red-700 border-red-100 hover:bg-red-100';
        case 'falta_justificada':
            return 'bg-orange-50 text-orange-700 border-orange-100 hover:bg-orange-100';
        case 'vacaciones': 
            return 'bg-blue-50 text-blue-700 border-blue-100 hover:bg-blue-100';
        case 'dia_festivo': 
            return 'bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100';
        case 'descanso': 
            return 'bg-gray-100 text-gray-500 border-gray-200';
        case 'incapacidad_general':
        case 'incapacidad_trabajo':
            return 'bg-purple-50 text-purple-700 border-purple-100 hover:bg-purple-100';
        case 'permiso_con_goce':
        case 'permiso_sin_goce':
            return 'bg-indigo-50 text-indigo-800 border-indigo-100 hover:bg-indigo-100';
        case 'no_laboraba':
            return 'bg-surface-100 text-surface-400 border-surface-200 opacity-50';
        case 'asistencia':
            if (day.check_in) return 'bg-white hover:bg-surface-50 border-surface-100 text-surface-900';
            return 'bg-surface-50 text-surface-400 border-surface-100'; 
        default:
            return 'bg-surface-50 text-surface-400 border-surface-100';
    }
};
</script>

<template>
    <AppLayout title="Detalle de Nómina">
        <Head title="Nómina Semanal" />
        
        <div class="w-full flex flex-col gap-6 h-full p-2 md:p-0">
            
            <!-- Encabezado de Navegación y Filtros -->
            <div class="flex flex-col xl:flex-row justify-between items-center gap-4 bg-white p-4 rounded-3xl shadow-sm border border-surface-200 sticky top-16 z-20">
                
                <!-- Navegación de Periodo -->
                <div class="flex items-center gap-3 rounded-full px-2 py-1 border border-surface-100 bg-surface-50">
                    <Button icon="pi pi-chevron-left" text rounded @click="prevWeek" aria-label="Semana anterior" />
                    <div class="text-center px-2">
                        <span class="block text-[10px] text-surface-500 uppercase tracking-wider font-bold">Periodo Semanal</span>
                        <span class="text-base md:text-lg font-bold text-surface-900 capitalize whitespace-nowrap">{{ dateRangeLabel }}</span>
                    </div>
                    <Button icon="pi pi-chevron-right" text rounded @click="nextWeek" aria-label="Semana siguiente" />
                </div>

                <!-- Buscador -->
                <div class="w-full max-w-md">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Buscar empleado..." class="w-full !rounded-full border-none bg-surface-100 focus:bg-white transition-colors focus:ring-0" />
                    </IconField>
                </div>

                <!-- Acciones Extra -->
                <div class="flex gap-2 w-full md:w-auto justify-end">
                    <Button label="Recibos" icon="pi pi-file-pdf" severity="secondary" outlined rounded disabled v-tooltip.top="'Descarga masiva próximamente'" />
                    <Link :href="route('payroll.settlement', startDate)">
                        <Button label="Pre-Nómina" icon="pi pi-calculator" class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 shadow-md shadow-orange-200" rounded />
                    </Link>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- VISTA DE ESCRITORIO (TABLA SÁBANA)             -->
            <!-- ============================================== -->
            <div class="hidden md:flex bg-white border border-surface-200 rounded-3xl shadow-xl overflow-hidden flex-1 flex-col relative">
                <div class="overflow-x-auto custom-scrollbar flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-50 text-surface-600 text-xs uppercase tracking-wider border-b border-surface-200">
                                <th class="p-4 font-bold sticky left-0 bg-surface-50 z-20 min-w-[250px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Empleado</th>
                                
                                <template v-if="payrollData.length > 0">
                                    <th v-for="day in payrollData[0].days" :key="day.date" class="p-3 text-center min-w-[140px]">
                                        <div class="flex flex-col items-center">
                                            <span class="font-bold">{{ day.day_name.substring(0,3) }}</span>
                                            <span class="text-[10px] text-surface-400 px-2 rounded-full">{{ dayjs(day.date).format('D MMM') }}</span>
                                        </div>
                                    </th>
                                </template>
                                
                                <th class="p-4 text-center font-bold min-w-[115px] bg-surface-50 sticky right-0 z-10 shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)]">Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-surface-100">
                            <tr v-for="row in filteredEmployees" :key="row.employee.id" class="group hover:bg-surface-50/50 transition-colors">
                                
                                <!-- Columna Empleado -->
                                <td class="p-4 sticky left-0 bg-white group-hover:bg-surface-50 transition-colors z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-shrink-0">
                                            <img 
                                                v-if="row.employee.profile_photo_url" 
                                                :src="row.employee.profile_photo_url" 
                                                class="w-10 h-10 rounded-full object-cover border border-surface-200"
                                            >
                                            <div v-else class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xs border border-orange-200">
                                                {{ row.employee.first_name[0] }}{{ row.employee.last_name[0] }}
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-surface-900 truncate max-w-[150px]" :title="row.employee.full_name">{{ row.employee.full_name }}</div>
                                            <div class="text-xs text-surface-500 ">ID: {{ row.employee.id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Columnas Días -->
                                <td v-for="day in row.days" :key="day.date" class="p-2">
                                    <div 
                                        class="h-20 rounded-xl border p-2 flex flex-col justify-center items-center text-center cursor-pointer transition-all hover:scale-[1.02] hover:shadow-md relative overflow-hidden"
                                        :class="getCellClass(day)"
                                        @click="openEdit(row, day)"
                                    >
                                        <!-- === INDICADORES (Badge Festivo Laborado) === -->
                                        <div v-if="getHolidayInfo(day.date) && day.check_in" class="absolute -top-1 -right-1 bg-yellow-400 text-yellow-900 text-xs font-black px-1.5 py-0.5 rounded-bl-lg rounded-tr-lg shadow-sm z-10 flex items-center gap-0.5">
                                            <i class="pi pi-star-fill !text-[9px]"></i> 3x
                                        </div>

                                        <!-- Indicadores de Estado (Retardos) -->
                                        <div class="absolute top-0 right-1.5 flex gap-1" v-else>
                                            <div v-if="day.is_late && !day.late_ignored" class="flex items-center space-x-1 text-red-600">
                                                <div class="w-2 h-2 rounded-full bg-red-500 ring-2 ring-white" title="Retardo"></div>
                                            </div>
                                            <div v-if="day.late_ignored" class="flex items-center space-x-1 text-green-600">
                                                <div class="w-2 h-2 rounded-full bg-green-500 ring-2 ring-white" title="Retardo justificado"></div>
                                            </div>
                                        </div>

                                        <!-- Turno -->
                                        <div class="text-[9px] uppercase font-bold opacity-60 mb-1 mt-2 w-full truncate">
                                            {{ day.schedule_shift || '' }}
                                        </div>

                                        <!-- === CONTENIDO DE LA CELDA === -->
                                        
                                        <!-- CASO 1: Festivo Laborado -->
                                        <template v-if="getHolidayInfo(day.date) && day.check_in">
                                             <div class="flex flex-col gap-0.5">
                                                <span class="text-xs font-bold text-yellow-900 bg-white/40 px-1 rounded">{{ day.check_in }}</span>
                                                <span class="text-xs text-yellow-800">{{ day.check_out || '--:--' }}</span>
                                                <span class="text-[9px] font-bold text-yellow-600 uppercase mt-0.5">{{ getHolidayInfo(day.date).name || 'Festivo' }}</span>
                                            </div>
                                        </template>

                                        <!-- CASO 2: Festivo NO Laborado (Automático) -->
                                        <template v-else-if="getHolidayInfo(day.date)">
                                            <div class="text-[11px] font-bold leading-tight px-1 break-words w-full text-emerald-800">
                                                {{ getHolidayInfo(day.date).name || 'Día Festivo' }}
                                            </div>
                                        </template>

                                        <!-- CASO 3: Asistencia Normal -->
                                        <template v-else-if="day.incident_type === 'asistencia' && day.check_in">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-xs font-bold text-surface-800 bg-white/50 px-1 rounded">{{ day.check_in }}</span>
                                                <span class="text-xs text-surface-600">{{ day.check_out || '--:--' }}</span>
                                            </div>
                                        </template>

                                        <!-- CASO 4: Otra Incidencia -->
                                        <template v-else-if="day.incident_type !== 'asistencia'">
                                            <div class="text-[11px] font-bold leading-tight px-1 break-words w-full">
                                                {{ incidentOptions.find(o => o.value === day.incident_type)?.label || day.incident_label }}
                                            </div>
                                        </template>

                                        <!-- CASO 5: Vacío -->
                                        <template v-else>
                                            <div class="text-surface-300">
                                                <i class="pi pi-minus-circle text-lg opacity-20"></i>
                                            </div>
                                        </template>
                                    </div>
                                </td>

                                <!-- Total Horas -->
                                <td class="p-4 text-center font-bold text-surface-700 bg-white sticky right-0 z-10 shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.05)] border-l border-surface-100">
                                    <span class="bg-surface-100 px-3 py-1 rounded-full text-sm">
                                        {{ calculateTotalHours(row.days) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Leyenda -->
                <div class="border-t border-surface-200 bg-surface-50 p-3 flex flex-wrap gap-4 text-xs text-surface-600 justify-center">
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-red-100 border border-red-300"></span> Falta Injustificada</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-orange-100 border border-orange-300"></span> Falta Justificada</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-blue-100 border border-blue-300"></span> Vacaciones</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-purple-100 border border-purple-300"></span> Incapacidad</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-emerald-100 border border-emerald-300"></span> Festivo</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-indigo-100 border border-indigo-300"></span> Permiso</div>
                    <div class="flex items-center gap-1"><span class="size-3 rounded-full bg-yellow-100 border border-yellow-300"></span> Día festivo laborado</div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- VISTA MÓVIL (GRID DE TARJETAS)                 -->
            <!-- ============================================== -->
            <div class="md:hidden flex flex-col gap-4 pb-20">
                <div v-for="row in filteredEmployees" :key="row.employee.id" class="bg-white rounded-2xl shadow-sm border border-surface-200 p-4">
                    
                    <!-- Cabecera de Empleado (Móvil) -->
                    <div class="flex items-center justify-between mb-4 border-b border-surface-100 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="relative flex-shrink-0">
                                <img v-if="row.employee.profile_photo_url" :src="row.employee.profile_photo_url" class="w-10 h-10 rounded-full object-cover">
                                <div v-else class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xs border border-orange-200">
                                    {{ row.employee.first_name[0] }}{{ row.employee.last_name[0] }}
                                </div>
                            </div>
                            <div>
                                <div class="font-bold text-surface-900 text-sm leading-tight">{{ row.employee.full_name }}</div>
                                <div class="text-xs text-surface-500">ID: {{ row.employee.id }}</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] text-surface-500 uppercase font-bold">Total</span>
                            <span class="bg-surface-100 px-2 py-0.5 rounded-full text-sm font-bold text-surface-700">
                                {{ calculateTotalHours(row.days) }}
                            </span>
                        </div>
                    </div>

                    <!-- Grid de Días (Móvil) -->
                    <div class="grid grid-cols-3 xs:grid-cols-4 gap-2">
                        <div v-for="day in row.days" :key="day.date" 
                            class="rounded-xl border p-2 flex flex-col items-center justify-center text-center cursor-pointer relative overflow-hidden h-20"
                            :class="getCellClass(day)"
                            @click="openEdit(row, day)"
                        >
                            <!-- Etiqueta de Día -->
                            <div class="text-[9px] uppercase font-bold opacity-70 mb-1">
                                {{ day.day_name.substring(0,3) }} {{ dayjs(day.date).format('D') }}
                            </div>

                             <!-- === INDICADORES (Badge Festivo Laborado Móvil) === -->
                             <div v-if="getHolidayInfo(day.date) && day.check_in" class="absolute top-0 right-0 bg-yellow-400 text-yellow-900 text-[10px] font-black px-1 rounded-bl-md z-10">
                                <i class="pi pi-star-fill !text-[9px]"></i> 3x
                            </div>

                            <!-- Contenido Célula -->
                            
                            <!-- Festivo Laborado -->
                            <template v-if="getHolidayInfo(day.date) && day.check_in">
                                <div class="flex flex-col gap-0 leading-none">
                                    <span class="text-xs font-bold text-yellow-900">{{ day.check_in }}</span>
                                    <span class="text-[10px] text-yellow-700">{{ day.check_out || '--:--' }}</span>
                                </div>
                            </template>
                             <!-- Festivo Automático -->
                            <template v-else-if="getHolidayInfo(day.date)">
                                <div class="text-[9px] font-bold leading-tight px-1 break-words w-full line-clamp-2 text-emerald-800">
                                    {{ getHolidayInfo(day.date).name || 'Festivo' }}
                                </div>
                            </template>
                            <!-- Asistencia Normal -->
                            <template v-else-if="day.incident_type === 'asistencia' && day.check_in">
                                <div class="flex flex-col gap-0 leading-none">
                                    <span class="text-xs font-bold text-surface-800">{{ day.check_in }}</span>
                                    <span class="text-[10px] text-surface-600">{{ day.check_out || '--:--' }}</span>
                                </div>
                            </template>
                            <!-- Otras incidencias -->
                            <template v-else-if="day.incident_type !== 'asistencia'">
                                <div class="text-[9px] font-bold leading-tight px-1 break-words w-full line-clamp-2">
                                    {{ incidentOptions.find(o => o.value === day.incident_type)?.label || day.incident_label }}
                                </div>
                            </template>
                            <template v-else>
                                <i class="pi pi-minus text-surface-300 text-xs"></i>
                            </template>

                            <!-- Indicador de Retardo (Punto simple) -->
                            <div v-if="day.is_late && !day.late_ignored && !getHolidayInfo(day.date)" class="absolute top-1 right-1 w-1.5 h-1.5 bg-red-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Empty State Móvil -->
                <div v-if="filteredEmployees.length === 0" class="text-center p-8 text-surface-500 bg-white rounded-2xl border border-dashed border-surface-300">
                    <i class="pi pi-search text-2xl mb-2 opacity-50"></i>
                    <p>No se encontraron empleados.</p>
                </div>
            </div>
        </div>

        <!-- MODAL DE EDICIÓN DE ASISTENCIA/INCIDENCIA -->
        <Dialog 
            v-model:visible="editDialog" 
            modal 
            :header="selectedEmployee ? `Editar registro` : 'Gestionar'" 
            :style="{ width: '32rem' }"
            :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
            class="p-fluid"
            :draggable="false"
        >
            <div class="flex flex-col gap-5 pt-2" v-if="selectedDay && selectedEmployee">
                
                <!-- Header del Modal -->
                <div class="flex items-center gap-3 pb-3 border-b border-surface-100">
                    <div class="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center text-orange-600 font-bold text-lg">
                        {{ selectedEmployee.first_name[0] }}
                    </div>
                    <div>
                        <div class="font-bold text-lg text-surface-900">{{ selectedEmployee.full_name }}</div>
                        <div class="text-sm text-surface-500 capitalize">
                            {{ dayjs(selectedDay.date).format('dddd, D [de] MMMM YYYY') }}
                        </div>
                        <!-- Alerta si es festivo -->
                        <div v-if="getHolidayInfo(selectedDay.date)" class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                            <i class="pi pi-calendar-plus"></i> {{ getHolidayInfo(selectedDay.date).name }}
                        </div>
                    </div>
                </div>

                <!-- Selector de Tipo -->
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-700 text-sm">Estado del registro</label>
                    <Select 
                        v-model="form.incident_type" 
                        :options="incidentOptions" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Selecciona estado"
                        class="w-full"
                        :class="{'p-invalid': form.errors.incident_type}"
                    />
                </div>

                <!-- Campos de Hora (Condicionales) -->
                <div v-if="form.incident_type === 'asistencia'" class="bg-surface-50 p-4 rounded-xl border border-surface-200 animate-fade-in space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="font-bold text-surface-700 text-sm">Entrada</label>
                            <DatePicker 
                                v-model="form.check_in" 
                                timeOnly 
                                hourFormat="24" 
                                showIcon 
                                iconDisplay="input"
                                placeholder="--:--"
                                fluid
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-bold text-surface-700 text-sm">Salida</label>
                            <DatePicker 
                                v-model="form.check_out" 
                                timeOnly 
                                hourFormat="24" 
                                showIcon 
                                iconDisplay="input"
                                placeholder="--:--"
                                fluid
                            />
                        </div>
                    </div>
                    
                    <!-- Switch Ignorar Retardo -->
                    <div class="flex items-center justify-between pt-2">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-surface-700">Perdonar retardo</span>
                            <span class="text-xs text-surface-500">Excluir del cálculo de bonos</span>
                        </div>
                        <ToggleSwitch v-model="form.late_ignored" />
                    </div>
                </div>

                <!-- Notas -->
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-700 text-sm">Observaciones / Notas</label>
                    <Textarea 
                        v-model="form.admin_notes" 
                        rows="3" 
                        placeholder="Escribe una justificación o detalle administrativo..." 
                        class="resize-none focus:!bg-white" 
                    />
                </div>

            </div>

            <template #footer>
                <div class="flex justify-end gap-2 pt-4 border-t border-surface-100">
                    <Button label="Cancelar" icon="pi pi-times" text @click="editDialog = false" severity="secondary" />
                    <Button label="Guardar Registro" icon="pi pi-check" @click="saveIncident" :loading="form.processing" class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700" />
                </div>
            </template>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
/* Scrollbar personalizado sutil */
.custom-scrollbar::-webkit-scrollbar {
    height: 10px;
    width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 20px;
    border: 2px solid #f8fafc;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

.animate-fade-in {
    animation: fadeIn 0.25s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

:deep(.p-datepicker-input) {
    text-align: center;
    /* font-family: monospace; */
    font-weight: 600;
}
</style>