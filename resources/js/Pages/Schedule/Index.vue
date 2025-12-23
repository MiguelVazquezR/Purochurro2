<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { format, addDays, parseISO, isSameDay } from 'date-fns';
import { es } from 'date-fns/locale';
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import axios from 'axios';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    employees: {
        type: Array,
        required: true
    },
    shifts: {
        type: Array,
        required: true
    },
    weekStart: {
        type: String,
        required: true
    },
    weekEnd: {
        type: String,
        required: true
    }
});

const toast = useToast();
const confirm = useConfirm();
const loadingGenerate = ref(false);
const updatingCell = ref(null);

// Referencias para el Popover
const op = ref();
const selectedCellData = ref(null);

// --- ESTADOS PARA EL TOUR ---
const isLoadingTour = ref(false);
const isTourActive = ref(false);

// Días de la semana para las columnas
const weekDays = computed(() => {
    const start = parseISO(props.weekStart);
    return Array.from({ length: 7 }, (_, i) => addDays(start, i));
});

// Navegación
const navigateWeek = (direction) => {
    const currentStart = parseISO(props.weekStart);
    const newDate = addDays(currentStart, direction * 7);
    
    router.get(route('schedule.index'), { 
        start_date: format(newDate, 'yyyy-MM-dd') 
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['employees', 'weekStart', 'weekEnd']
    });
};

const goToToday = () => {
    router.get(route('schedule.index'), { 
        start_date: format(new Date(), 'yyyy-MM-dd') 
    });
};

// Acciones
const confirmGenerateWeek = (event) => {
    confirm.require({
        target: event.currentTarget,
        message: 'Esto aplicará las plantillas predeterminadas de cada empleado a esta semana. ¿Deseas continuar?',
        header: 'Generar Horarios',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Generar',
        accept: () => generateWeek()
    });
};

const generateWeek = () => {
    loadingGenerate.value = true;
    router.post(route('schedule.generate'), {
        start_date: props.weekStart
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Semana generada correctamente', life: 3000 });
        },
        onFinish: () => {
            loadingGenerate.value = false;
        }
    });
};

// Manejo del Popover de Selección
const toggleShiftSelector = (event, employee, day) => {
    selectedCellData.value = { employee, day };
    op.value.toggle(event);
};

const selectShift = (shiftId) => {
    if (!selectedCellData.value) return;
    
    const { employee, day } = selectedCellData.value;
    const dateStr = format(day, 'yyyy-MM-dd');
    
    updateSchedule(employee.id, dateStr, shiftId);
    op.value.hide();
};

const updateSchedule = (employeeId, date, shiftId) => {
    const key = `${employeeId}-${date}`;
    updatingCell.value = key;

    router.post(route('schedule.store'), {
        employee_id: employeeId,
        date: date,
        shift_id: shiftId || null
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingCell.value = null;
        },
        onError: () => {
             toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo actualizar el turno.', life: 3000 });
        }
    });
};

// Helpers Visuales
const getSchedule = (employee, date) => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return employee.week_schedules ? employee.week_schedules[dateStr] : null;
};

const formatTime = (timeStr) => {
    if (!timeStr) return '';
    const [hoursStr, minutesStr] = timeStr.split(':');
    let hours = parseInt(hoursStr);
    const minutes = minutesStr;
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    hours = hours % 12;
    hours = hours ? hours : 12; 
    
    return `${hours}:${minutes} ${ampm}`;
};

const getShiftBadgeStyle = (shift) => {
    if (!shift) return {};
    return {
        backgroundColor: shift.color ? `${shift.color}20` : '#e0f2fe',
        color: shift.color || '#0369a1',
        border: `1px solid ${shift.color ? `${shift.color}40` : '#bae6fd'}`
    };
};

// --- LÓGICA DEL TUTORIAL (ONBOARDING) ---

const blockInteraction = (e) => {
    if (!isTourActive.value) return;
    if (e.target.closest && e.target.closest('.driver-popover')) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
};

const enableBlocking = () => {
    isTourActive.value = true;
    window.addEventListener('click', blockInteraction, true);
    window.addEventListener('mousedown', blockInteraction, true);
    window.addEventListener('touchstart', blockInteraction, true);
    window.addEventListener('keydown', blockInteraction, true);
};

const disableBlocking = () => {
    isTourActive.value = false;
    window.removeEventListener('click', blockInteraction, true);
    window.removeEventListener('mousedown', blockInteraction, true);
    window.removeEventListener('touchstart', blockInteraction, true);
    window.removeEventListener('keydown', blockInteraction, true);
};

const startTour = () => {
    enableBlocking();

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: [
            { 
                element: '#tour-schedule-header', 
                popover: { 
                    title: 'Gestión de Horarios', 
                    description: 'En este módulo puedes planificar y asignar los turnos de trabajo de todos tus empleados para la semana.',
                    side: "bottom",
                    align: 'start'
                } 
            },
            { 
                element: '#tour-date-controls', 
                popover: { 
                    title: 'Navegación', 
                    description: 'Utiliza estas flechas para moverte entre semanas. Siempre puedes volver a la semana actual con el botón "Hoy".',
                } 
            },
            { 
                element: '#tour-generate-btn', 
                popover: { 
                    title: 'Generación Automática', 
                    description: '¡Ahorra tiempo! Si tienes plantillas de turnos configuradas en los perfiles de empleado, este botón llenará toda la semana automáticamente con un solo clic.',
                } 
            },
            { 
                element: '#tour-schedule-grid', 
                popover: { 
                    title: 'Cuadrícula de Turnos', 
                    description: 'Esta tabla muestra la asignación actual. Las filas son tus empleados y las columnas los días de la semana.',
                } 
            },
            { 
                element: '#tour-first-cell', 
                popover: { 
                    title: 'Asignar Turno', 
                    description: 'Haz clic en cualquier celda (día/empleado) para abrir el menú y seleccionar un turno o marcarlo como descanso.',
                } 
            }
        ],
        onDestroyStarted: () => {
            markTourAsCompleted();
            tourDriver.destroy();
            disableBlocking();
        }
    });

    tourDriver.drive();
};

const markTourAsCompleted = async () => {
    try {
        await axios.post(route('tutorials.complete'), { module_name: 'schedule_management' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'schedule_management'));
        if (!response.data.completed) {
            isLoadingTour.value = true;
            setTimeout(() => {
                isLoadingTour.value = false;
                startTour();
            }, 800);
        }
    } catch (error) {
        console.error('Error verificando tutorial', error);
        isLoadingTour.value = false;
    }
});

onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Asignación de horarios">
        
        <!-- Overlay de Carga -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa de Bloqueo -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="w-full flex flex-col gap-6 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">
            
            <!-- Encabezado y Controles -->
            <!-- ID TOUR: Header -->
            <div id="tour-schedule-header" class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Horarios</h1>
                    <p class="text-surface-500 text-sm mt-1">Gestión y asignación semanal de turnos.</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-white/50 backdrop-blur-md p-2 rounded-2xl border border-surface-200 shadow-sm">
                    <!-- Navegación de Fechas -->
                    <!-- ID TOUR: Date Controls -->
                    <div id="tour-date-controls" class="flex items-center bg-surface-50 rounded-xl p-1 border border-surface-200 w-full sm:w-auto justify-between sm:justify-start">
                        <Button 
                            icon="pi pi-chevron-left" 
                            text 
                            rounded 
                            severity="secondary" 
                            class="!w-8 !h-8 !text-surface-600"
                            @click="navigateWeek(-1)"
                            v-tooltip.top="'Semana anterior'"
                        />
                        <div class="px-4 flex flex-col items-center min-w-[140px] sm:min-w-[160px]">
                            <span class="text-xs text-surface-400 font-medium uppercase tracking-wider">Semana actual</span>
                            <span class="text-sm font-bold text-surface-700 whitespace-nowrap">
                                {{ format(parseISO(weekStart), 'd MMM', { locale: es }) }} - {{ format(parseISO(weekEnd), 'd MMM', { locale: es }) }}
                            </span>
                        </div>
                        <Button 
                            icon="pi pi-chevron-right" 
                            text 
                            rounded 
                            severity="secondary" 
                            class="!w-8 !h-8 !text-surface-600"
                            @click="navigateWeek(1)"
                            v-tooltip.top="'Semana siguiente'"
                        />
                    </div>

                    <div class="h-8 w-px bg-surface-300 hidden sm:block mx-1"></div>

                    <div class="flex gap-2 w-full sm:w-auto">
                        <Button 
                            label="Hoy" 
                            icon="pi pi-calendar-today" 
                            severity="secondary" 
                            text
                            class="!text-surface-600 hover:!bg-surface-100 hidden sm:flex"
                            @click="goToToday"
                        />
                        <!-- ID TOUR: Generate Btn -->
                        <div id="tour-generate-btn" class="flex-1 sm:flex-none">
                            <Button 
                                label="Generar Automático" 
                                icon="pi pi-bolt" 
                                :loading="loadingGenerate"
                                class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700 font-semibold shadow-lg shadow-indigo-200/50 w-full"
                                rounded
                                @click="confirmGenerateWeek"
                                v-tooltip.bottom="'Aplica las plantillas predeterminadas a toda la semana'"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenedor Principal Glassmorphism -->
            <!-- ID TOUR: Grid -->
            <div id="tour-schedule-grid" class="bg-white/80 backdrop-blur-xl border border-surface-200 rounded-3xl shadow-xl overflow-hidden p-1 relative">
                
                <!-- Tabla Customizada -->
                <div class="overflow-x-auto rounded-2xl pb-10 sm:pb-0"> <!-- Padding bottom para scroll en móvil -->
                    <table class="w-full text-left border-collapse">
                        <!-- Header -->
                        <thead class="bg-surface-50/80 backdrop-blur-sm text-surface-600 text-xs uppercase tracking-wider sticky top-0 z-20">
                            <tr>
                                <!-- Columna Sticky Responsive: Pequeña en móvil, Grande en Desktop -->
                                <th class="p-3 font-semibold border-b border-surface-200 sticky left-0 bg-surface-50 z-30 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.05)] min-w-[70px] sm:min-w-[220px] text-center sm:text-left">
                                    <span class="hidden sm:inline">Empleado</span>
                                    <i class="pi pi-users sm:!hidden !text-lg"></i>
                                </th>
                                <th v-for="day in weekDays" :key="day" class="p-3 text-center border-b border-l border-surface-200 min-w-[120px] sm:min-w-[140px]">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span 
                                            class="font-bold text-sm"
                                            :class="isSameDay(day, new Date()) ? 'text-indigo-600' : 'text-surface-700'"
                                        >
                                            {{ format(day, 'EEEE', { locale: es }) }}
                                        </span>
                                        <span class="text-[10px] font-medium text-surface-400 bg-surface-100 px-2 py-0.5 rounded-full">
                                            {{ format(day, 'd MMM', { locale: es }) }}
                                        </span>
                                    </div>
                                </th>
                            </tr>
                        </thead>

                        <!-- Body -->
                        <tbody class="divide-y divide-surface-100 bg-white/40">
                            <tr v-for="(employee, index) in employees" :key="employee.id" class="group hover:bg-surface-50/50 transition-colors duration-200">
                                
                                <!-- Columna Empleado (Sticky) -->
                                <td class="p-2 sm:p-4 sticky left-0 bg-white/95 backdrop-blur-md group-hover:bg-surface-50/95 z-10 border-r border-surface-200 shadow-[4px_0_10px_-4px_rgba(0,0,0,0.05)]">
                                    <div class="flex flex-col sm:flex-row items-center gap-1 sm:gap-3 justify-center sm:justify-start">
                                        <!-- Avatar -->
                                        <div class="relative flex-shrink-0">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-surface-100 border border-surface-200 flex items-center justify-center overflow-hidden text-surface-400">
                                                <img 
                                                    :src="employee.profile_photo_url" 
                                                    class="w-full h-full object-cover" 
                                                    alt="Avatar" 
                                                />
                                            </div>
                                            <!-- Indicador Activo -->
                                            <!-- <div class="absolute -bottom-1 -right-1 w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 bg-green-500 border-2 border-white rounded-full" title="Activo"></div> -->
                                        </div>
                                        
                                        <!-- Info Desktop (Nombre completo + Puesto) -->
                                        <div class="hidden sm:flex flex-col min-w-0">
                                            <span class="font-bold text-surface-800 text-sm truncate max-w-[140px] leading-tight">
                                                {{ employee.first_name }} {{ employee.last_name }}
                                            </span>
                                            <span class="text-xs text-surface-500 truncate max-w-[140px]">
                                                {{ employee.job_title || 'Colaborador' }}
                                            </span>
                                        </div>

                                        <!-- Info Móvil (Solo Primer Nombre) -->
                                        <div class="sm:hidden flex flex-col items-center">
                                            <span class="font-bold text-surface-700 text-[10px] leading-tight truncate max-w-[60px] text-center">
                                                {{ employee.first_name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Celdas de Días -->
                                <td 
                                    v-for="(day, dayIndex) in weekDays" 
                                    :key="day.toString()" 
                                    class="p-0 border-l border-surface-100 relative h-20 sm:h-24 align-top transition-all duration-200 hover:bg-surface-50 hover:shadow-inner cursor-pointer"
                                    :id="index === 0 && dayIndex === 0 ? 'tour-first-cell' : null" 
                                    @click="(e) => toggleShiftSelector(e, employee, day)"
                                >
                                    <div class="w-full h-full flex flex-col p-2">
                                        <!-- Indicador de carga -->
                                        <div v-if="updatingCell === `${employee.id}-${format(day, 'yyyy-MM-dd')}`" 
                                             class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-20 flex items-center justify-center">
                                            <i class="pi pi-spin pi-spinner text-indigo-500"></i>
                                        </div>

                                        <!-- Contenido del Turno -->
                                        <div class="flex-1 flex flex-col items-center justify-center gap-1 w-full pointer-events-none">
                                            <template v-if="getSchedule(employee, day)?.shift">
                                                <!-- Badge del Turno -->
                                                <div 
                                                    class="px-2 py-1 sm:px-2.5 sm:py-1.5 rounded-lg text-[10px] sm:text-xs font-bold shadow-sm w-full text-center truncate"
                                                    :style="getShiftBadgeStyle(getSchedule(employee, day).shift)"
                                                >
                                                    {{ getSchedule(employee, day).shift.name }}
                                                </div>
                                                <!-- Hora -->
                                                <div class="flex items-center gap-1 text-[9px] sm:text-[10px] text-surface-500 font-medium bg-surface-100/50 px-1.5 py-0.5 rounded-md">
                                                    <i class="pi pi-clock !text-[9px] sm:!text-[10px] text-surface-400"></i>
                                                    <span>
                                                        {{ formatTime(getSchedule(employee, day).shift.start_time) }} - 
                                                        {{ formatTime(getSchedule(employee, day).shift.end_time) }}
                                                    </span>
                                                </div>
                                            </template>

                                            <!-- Estado Descanso / Vacío -->
                                            <template v-else>
                                                <div class="w-full h-full flex flex-col items-center justify-center">
                                                    <!-- Texto visible siempre -->
                                                    <span class="text-[10px] font-medium text-surface-300 select-none">-- Descanso --</span>
                                                    
                                                    <!-- "Asignar Turno" visible solo al hacer hover -->
                                                    <div class="flex items-center gap-1 mt-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                        <i class="pi pi-plus text-[10px] text-indigo-500"></i>
                                                        <span class="text-[10px] font-bold text-indigo-600">Asignar</span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </td>

                            </tr>
                            
                            <!-- Estado Vacío -->
                            <tr v-if="employees.length === 0">
                                <td colspan="8" class="p-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-surface-400">
                                        <div class="w-16 h-16 bg-surface-50 rounded-full flex items-center justify-center mb-4 shadow-inner">
                                            <i class="pi pi-users text-2xl opacity-50"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-surface-600">No hay empleados activos</h3>
                                        <p class="text-sm">Agrega empleados para comenzar a gestionar sus horarios.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Popover selector de turnos -->
        <Popover ref="op" id="overlay_panel" class="!w-64 !p-0 !border-0 !shadow-xl !rounded-xl overflow-hidden">
            <div class="flex flex-col">
                <div class="p-3 flex items-center justify-center bg-surface-50 border-b border-surface-100">
                    <span class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Seleccionar Turno</span>
                </div>
                
                <div class="max-h-[300px] overflow-y-auto">
                    <!-- Opción Descanso -->
                    <button 
                        @click="selectShift(null)"
                        class="w-full text-left px-4 py-3 text-sm hover:bg-surface-50 transition-colors flex items-center gap-3 border-b border-surface-50 group"
                    >
                        <div class="w-2 h-8 rounded-full bg-surface-200 group-hover:bg-surface-300 transition-colors"></div>
                        <div class="flex flex-col">
                            <span class="font-medium text-surface-700">Descanso / Libre</span>
                            <span class="text-xs text-surface-400">Sin asignación</span>
                        </div>
                    </button>

                    <!-- Lista de Turnos -->
                    <button 
                        v-for="shift in shifts" 
                        :key="shift.id"
                        @click="selectShift(shift.id)"
                        class="w-full text-left px-4 py-3 text-sm hover:bg-surface-50 transition-colors flex items-center gap-3 border-b border-surface-50 group"
                    >
                        <div 
                            class="w-2 h-8 rounded-full shadow-sm transition-transform group-hover:scale-110" 
                            :style="{ backgroundColor: shift.color || '#3b82f6' }"
                        ></div>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-800">{{ shift.name }}</span>
                            <span class="text-xs text-surface-500 flex items-center gap-1">
                                {{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}
                            </span>
                        </div>
                        <i v-if="selectedCellData && getSchedule(selectedCellData.employee, selectedCellData.day)?.shift_id === shift.id" 
                           class="pi pi-check text-green-500 ml-auto font-bold"></i>
                    </button>
                </div>
            </div>
        </Popover>

    </AppLayout>
</template>

<style scoped>
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>