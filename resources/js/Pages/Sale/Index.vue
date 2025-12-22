<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';

// PrimeVue components
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import AvatarGroup from 'primevue/avatargroup';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    salesHistory: Object,
    filters: Object,
});

// --- ESTADOS PARA EL TOUR ---
const isLoadingTour = ref(false);
const isTourActive = ref(false);

// --- Helpers de Fecha Robustos ---
const parseDateSafe = (dateString) => {
    if (!dateString) return null;
    const cleanDate = dateString.substring(0, 10);
    const [year, month, day] = cleanDate.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDateLong = (dateString) => {
    const date = parseDateSafe(dateString);
    if (!date) return '-';

    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date);
};

const getDayNumber = (dateString) => {
    const date = parseDateSafe(dateString);
    return date ? date.getDate() : '';
};

const getMonthShort = (dateString) => {
    const date = parseDateSafe(dateString);
    if (!date) return '';
    return new Intl.DateTimeFormat('es-MX', { month: 'short' }).format(date);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

// --- Filtro ---
const dateFilter = ref(props.filters.date ? parseDateSafe(props.filters.date) : null);

watch(dateFilter, (newDate) => {
    const dateParam = newDate ? newDate.toLocaleDateString('en-CA') : undefined; // YYYY-MM-DD

    router.get(route('sales.index'), { date: dateParam }, {
        preserveState: true,
        replace: true,
        only: ['salesHistory', 'filters'] 
    });
});

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

    const steps = [
        { 
            element: '#tour-sales-header', 
            popover: { 
                title: 'Historial de Operaciones', 
                description: 'Aquí encontrarás el registro histórico de todos los cierres diarios de caja, permitiéndote auditar ventas pasadas.',
                side: "bottom",
                align: 'start'
            } 
        },
        { 
            element: '#tour-date-filter', 
            popover: { 
                title: 'Filtrado por Fecha', 
                description: '¿Buscas un día específico? Usa este calendario para filtrar el historial rápidamente.',
            } 
        }
    ];

    // Si hay datos, mostramos los pasos de detalle sobre la primera tarjeta
    if (props.salesHistory.data.length > 0) {
        steps.push(
            { 
                element: '#tour-day-card-0', 
                popover: { 
                    title: 'Resumen del Día', 
                    description: 'Cada tarjeta representa un día de operación (o turno). Aquí ves la fecha, el número de operación y si la caja ya fue cerrada.',
                } 
            },
            { 
                element: '#tour-staff-section-0', 
                popover: { 
                    title: 'Personal en Turno', 
                    description: 'Identifica rápidamente quiénes trabajaron ese día gracias a sus avatares.',
                } 
            },
            { 
                element: '#tour-financials-0', 
                popover: { 
                    title: 'Desglose Financiero', 
                    description: 'Un vistazo rápido a los totales: cuánto se vendió al público general, cuánto consumieron los empleados y el gran total ingresado.',
                } 
            },
            { 
                element: '#tour-details-btn-0', 
                popover: { 
                    title: 'Ver Detalles Completos', 
                    description: 'Haz clic aquí para entrar al detalle profundo: ver ticket por ticket, productos vendidos, cortes de caja y más.',
                } 
            }
        );
    }

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: steps,
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
        await axios.post(route('tutorials.complete'), { module_name: 'sales_history' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'sales_history'));
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
    <AppLayout title="Historial de Operaciones">
        
        <!-- Overlay de Carga -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa de Bloqueo -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">

            <!-- Header -->
            <!-- ID TOUR: Header -->
            <div id="tour-sales-header" class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight">Cierres diarios</h1>
                    <p class="text-gray-500 mt-1">Monitoreo de ventas y operaciones por turno.</p>
                </div>

                <!-- Filtro con DatePicker -->
                <!-- ID TOUR: Filter -->
                <div id="tour-date-filter" class="flex items-center gap-2 bg-white p-1.5 rounded-xl shadow-sm border border-gray-200">
                    <DatePicker 
                        v-model="dateFilter" 
                        showIcon 
                        fluid 
                        iconDisplay="input" 
                        placeholder="Filtrar por fecha"
                        dateFormat="dd/mm/yy" 
                        :maxDate="new Date()" 
                        class="w-full sm:w-56 !border-0"
                        :pt="{
                            input: { class: '!border-0 !shadow-none !font-medium !text-gray-700 focus:!ring-0' }
                        }"
                    />

                    <Button 
                        v-if="dateFilter" 
                        icon="pi pi-times" 
                        text 
                        rounded 
                        severity="secondary" 
                        size="small"
                        aria-label="Limpiar filtro" 
                        @click="dateFilter = null" 
                    />
                </div>
            </div>

            <!-- Lista de Días -->
            <div class="space-y-6">
                <!-- Estado Vacío -->
                <div v-if="salesHistory.data.length === 0"
                    class="flex flex-col items-center justify-center py-20 bg-gray-50 rounded-3xl border-2 border-dashed border-gray-200">
                    <div class="p-6 bg-white rounded-full shadow-sm mb-4">
                        <i class="pi pi-calendar-times !text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-gray-900 font-bold text-xl">Sin registros encontrados</h3>
                    <p class="text-gray-500 mt-2 max-w-sm text-center">No hay operaciones registradas para los criterios seleccionados.</p>
                    <Button v-if="dateFilter" label="Ver todo el historial" text class="mt-6 !text-indigo-600 font-bold"
                        @click="dateFilter = null" />
                </div>

                <!-- Tarjeta de Día -->
                <div v-for="(day, index) in salesHistory.data" :key="day.id"
                    :id="index === 0 ? 'tour-day-card-0' : null"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-xl transition-all duration-300 group">

                    <!-- Header de la Tarjeta -->
                    <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 gap-4 group-hover:bg-indigo-50/30 transition-colors">
                        <div class="flex items-center gap-5">
                            <!-- Fecha visual -->
                            <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm text-center min-w-[70px] flex flex-col justify-center">
                                <span class="text-[10px] text-gray-400 uppercase font-bold tracking-widest leading-tight">{{ getMonthShort(day.date) }}</span>
                                <span class="text-3xl font-black text-gray-800 leading-none mt-0.5">{{ getDayNumber(day.date) }}</span>
                            </div>
                            
                            <!-- Info Principal -->
                            <div>
                                <h3 class="font-bold text-gray-800 capitalize text-xl">{{ formatDateLong(day.date) }}</h3>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <Tag :value="day.is_closed ? 'Caja Cerrada' : 'Turno Abierto'"
                                        :severity="day.is_closed ? 'success' : 'warn'" 
                                        class="!text-xs !font-bold uppercase tracking-wide"
                                        rounded />
                                    <span class="text-xs text-gray-400 font-medium">Op. #{{ day.id }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Ver Detalles (Desktop) -->
                        <Link :href="route('sales.show', day.id)" class="hidden sm:block">
                            <!-- ID TOUR: Details Btn -->
                            <div :id="index === 0 ? 'tour-details-btn-0' : null">
                                <Button label="Ver Detalles" icon="pi pi-arrow-right" iconPos="right" text 
                                    class="!text-indigo-600 hover:!bg-indigo-50 !font-bold" rounded />
                            </div>
                        </Link>
                    </div>

                    <!-- Contenido de la Tarjeta -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-8 items-center">

                        <!-- Columna 1: Staff (3 cols) -->
                        <!-- ID TOUR: Staff -->
                        <div :id="index === 0 ? 'tour-staff-section-0' : null" class="md:col-span-4 lg:col-span-3 border-b md:border-b-0 md:border-r border-gray-100 pb-6 md:pb-0 md:pr-6">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <i class="pi pi-users text-xs"></i> Equipo en turno
                            </span>
                            
                            <div class="flex items-center h-10">
                                <AvatarGroup v-if="day.staff_list && day.staff_list.length > 0">
                                    <Avatar 
                                        v-for="employee in day.staff_list" 
                                        :key="employee.id" 
                                        :image="employee.photo"
                                        :label="!employee.photo ? employee.initials : null"
                                        shape="circle"
                                        class="!w-10 !h-10 !border-2 !text-xs transition-transform hover:z-10 hover:scale-110 cursor-help bg-white"
                                        :class="{'!bg-indigo-100 !text-indigo-700': !employee.photo}"
                                        :style="{ borderColor: employee.shift_color }" 
                                        v-tooltip.top="`${employee.name} (${employee.shift_name})`"
                                    />
                                    <Avatar v-if="day.staff_count > 4" :label="`+${day.staff_count - 4}`" shape="circle"
                                        class="!bg-gray-100 !text-gray-600 !border-2 !border-white !w-10 !h-10 !text-xs font-bold" />
                                </AvatarGroup>
                                
                                <div v-else class="flex items-center gap-2 text-amber-600 bg-amber-50 px-3 py-2 rounded-lg text-xs font-medium w-full">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <span>Sin asignación registrada</span>
                                </div>
                            </div>
                        </div>

                        <!-- Columna 2: Desglose Financiero (9 cols) -->
                        <!-- ID TOUR: Financials -->
                        <div :id="index === 0 ? 'tour-financials-0' : null" class="md:col-span-8 lg:col-span-9">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                
                                <!-- Venta Público -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-transparent hover:border-gray-200 transition-colors">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Público</span>
                                    </div>
                                    <span class="text-xl font-bold text-gray-800 block">{{ formatCurrency(day.total_public) }}</span>
                                </div>

                                <!-- Venta Empleados -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-transparent hover:border-gray-200 transition-colors">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Empleados</span>
                                    </div>
                                    <span class="text-xl font-bold text-gray-800 block">{{ formatCurrency(day.total_employee) }}</span>
                                </div>

                                <!-- Total del Día (Destacado) -->
                                <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100 text-right sm:text-left shadow-sm relative overflow-hidden group-hover:bg-indigo-100 transition-colors">
                                    <div class="absolute right-0 top-0 opacity-10 transform translate-x-2 -translate-y-2">
                                        <i class="pi pi-chart-line text-6xl text-indigo-900"></i>
                                    </div>
                                    <span class="text-[10px] text-indigo-600 font-black uppercase tracking-widest block mb-1">Total Ingresos</span>
                                    <span class="text-2xl font-black text-indigo-800 block">{{ formatCurrency(day.grand_total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Móvil -->
                    <div class="sm:hidden border-t border-gray-100 p-3 bg-gray-50">
                        <Link :href="route('sales.show', day.id)" class="block w-full">
                            <Button label="Ver Detalles Completos" icon="pi pi-arrow-right" iconPos="right" 
                                class="w-full !bg-white !text-indigo-600 !border-gray-200 shadow-sm" severity="secondary" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="salesHistory.data.length > 0" class="mt-10 flex justify-center pb-8">
                <div class="flex gap-1 bg-white p-1 rounded-xl shadow-sm border border-gray-100">
                    <Link v-for="(link, index) in salesHistory.links" :key="index" :href="link.url ?? '#'" 
                        class="px-4 py-2.5 rounded-lg text-sm font-bold transition-all flex items-center justify-center min-w-[40px]" 
                        :class="[
                            link.active ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900',
                            !link.url ? 'opacity-40 pointer-events-none' : ''
                        ]">
                        <span v-html="link.label"></span>
                    </Link>
                </div>
            </div>

        </div>
    </AppLayout>
</template>