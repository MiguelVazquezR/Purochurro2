<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    salesHistory: Object,
    filters: Object,
});

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
    // CORRECCIÓN: Si newDate es nulo, enviamos undefined para limpiar la URL
    const dateParam = newDate ? newDate.toLocaleDateString('en-CA') : undefined;

    router.get(route('sales.index'), { date: dateParam }, {
        preserveState: true,
        replace: true,
    });
});
</script>

<template>
    <AppLayout title="Historial de Operaciones">
        <div class="max-w-5xl mx-auto py-8 px-4">

            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Cierres diarios</h1>
                    <p class="text-gray-500 text-sm">Resumen de ventas y operaciones por día.</p>
                </div>

                <!-- Filtro con DatePicker -->
                <div class="flex items-center gap-2 bg-white p-1.5 rounded-xl shadow-sm border border-gray-200">
                    <DatePicker 
                        v-model="dateFilter" 
                        showIcon 
                        fluid 
                        iconDisplay="input" 
                        placeholder="Filtrar por fecha"
                        dateFormat="dd/mm/yy" 
                        :maxDate="new Date()" 
                        class="w-full sm:w-48 !border-0"
                        inputClass="!border-0 !shadow-none !text-sm !font-medium !text-gray-700 focus:!ring-0" 
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
                <div v-if="salesHistory.data.length === 0"
                    class="text-center py-16 bg-gray-50 rounded-3xl border-2 border-dashed border-gray-200">
                    <div class="flex justify-center mb-4">
                        <div class="p-4 bg-gray-50 rounded-full">
                            <i class="pi pi-calendar-times !text-3xl text-gray-400"></i>
                        </div>
                    </div>
                    <h3 class="text-gray-900 font-semibold text-lg">Sin resultados</h3>
                    <p class="text-gray-500 text-sm mt-1">No hay registros de operación para esta fecha.</p>
                    <Button v-if="dateFilter" label="Ver todos los días" text class="mt-4 !text-indigo-600"
                        @click="dateFilter = null" />
                </div>

                <!-- Tarjeta de Día -->
                <div v-for="day in salesHistory.data" :key="day.id"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group">

                    <!-- Header de la Tarjeta (Fecha y Estado) -->
                    <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b border-gray-100 group-hover:bg-indigo-50/30 transition-colors">
                        <div class="flex items-center gap-4">
                            <!-- Fecha visual -->
                            <div class="bg-white p-2.5 rounded-xl border border-gray-200 shadow-sm text-center min-w-[60px]">
                                <span class="block text-xs text-gray-400 uppercase font-bold tracking-wider">{{ getMonthShort(day.date) }}</span>
                                <span class="block text-2xl font-black text-gray-800 leading-none mt-1">{{ getDayNumber(day.date) }}</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 capitalize text-lg">{{ formatDateLong(day.date) }}</h3>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <Tag :value="day.is_closed ? 'Cerrado' : 'Abierto'"
                                        :severity="day.is_closed ? 'success' : 'warn'" class="!text-xs !py-0.5 !px-2"
                                        rounded />
                                    <span class="text-xs text-gray-400 font-medium tracking-wide">ID: #{{ day.id }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Ver Detalles -->
                        <Link :href="route('sales.show', day.id)">
                            <Button label="Ver Detalles" icon="pi pi-arrow-right" iconPos="right" text size="small"
                                class="!text-indigo-600 hover:!bg-white hover:!shadow-sm hidden sm:flex" rounded />
                        </Link>
                    </div>

                    <!-- Contenido de la Tarjeta -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8 items-center">

                        <!-- Columna 1: Staff (ACTUALIZADO CON FOTOS) -->
                        <div class="md:col-span-1 border-b md:border-b-0 md:border-r border-gray-100 pb-6 md:pb-0 md:pr-6">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 block">Equipo en turno</span>
                            <div class="flex items-center gap-2">
                                <AvatarGroup v-if="day.staff_list && day.staff_list.length > 0">
                                    <!-- 
                                        Mostramos foto si existe, si no, iniciales.
                                        Aplicamos estilos condicionales para el fondo solo si no hay foto.
                                    -->
                                    <Avatar 
                                        v-for="employee in day.staff_list" 
                                        :key="employee.id" 
                                        :image="employee.photo"
                                        :label="!employee.photo ? employee.initials : null"
                                        shape="circle"
                                        class="!w-9 !h-9 !border-2 !text-xs transition-transform hover:scale-110 cursor-help"
                                        :class="{'!bg-indigo-50 !text-indigo-700': !employee.photo, '!bg-white': employee.photo}"
                                        :style="{ borderColor: employee.shift_color }" 
                                        v-tooltip.top="`${employee.name} (${employee.shift_name})`"
                                    />
                                    
                                    <!-- Contador de extras -->
                                    <Avatar v-if="day.staff_count > 4" :label="`+${day.staff_count - 4}`" shape="circle"
                                        class="!bg-gray-100 !text-gray-600 !border-2 !border-white !w-9 !h-9 !text-xs" />
                                </AvatarGroup>
                                <span v-else class="text-sm text-gray-400 italic flex items-center gap-2">
                                    <i class="pi pi-exclamation-circle"></i> Sin personal
                                </span>
                            </div>
                        </div>

                        <!-- Columna 2: Desglose Financiero -->
                        <div class="md:col-span-2 flex flex-col sm:flex-row justify-between gap-6">

                            <!-- Venta Público -->
                            <div class="flex-1 space-y-1">
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Público</span>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-lg font-bold text-gray-700">{{ formatCurrency(day.total_public) }}</span>
                                </div>
                            </div>

                            <!-- Venta Empleados -->
                            <div class="flex-1 space-y-1">
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Empleados</span>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-lg font-bold text-gray-700">{{ formatCurrency(day.total_employee) }}</span>
                                </div>
                            </div>

                            <!-- Total del Día -->
                            <div class="flex-1 bg-indigo-50 rounded-xl p-4 border border-indigo-100 text-right min-w-[140px]">
                                <span class="text-[10px] text-indigo-500 font-black uppercase tracking-widest block mb-1">Total del Día</span>
                                <span class="text-2xl font-black text-indigo-700 block">{{ formatCurrency(day.grand_total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Acción Móvil -->
                    <div class="sm:hidden border-t border-gray-100 p-3 bg-gray-50 flex justify-end">
                        <Link :href="route('sales.show', day.id)" class="w-full">
                            <Button label="Ver Detalles" icon="pi pi-arrow-right" iconPos="right" text size="small" class="!text-indigo-600 w-full" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="salesHistory.data.length > 0" class="mt-8 flex justify-center gap-2">
                <Link v-for="link in salesHistory.links" :key="link.label" :href="link.url ?? '#'" v-html="link.label"
                    class="px-4 py-2 rounded-lg text-sm font-bold transition-all" :class="[
                        link.active ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700',
                        !link.url ? 'opacity-50 pointer-events-none' : ''
                    ]" />
            </div>

        </div>
    </AppLayout>
</template>