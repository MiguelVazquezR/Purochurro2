<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const props = defineProps({
    isAdmin: Boolean,
    employee: Object, 
    stats: Object,
});

// Modal de Horario
const showScheduleModal = ref(false);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2
    }).format(value || 0);
};

// --- Fix de Fecha para Nómina Actual ---
// Generamos la fecha localmente 'YYYY-MM-DD' para evitar problemas de UTC
const getCurrentLocalDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// --- Computed para Admin ---
const attendancePercentage = computed(() => {
    if (!props.isAdmin || !props.stats.active_employees) return 0;
    return Math.round((props.stats.attendance_today / props.stats.active_employees) * 100);
});
const presentCount = computed(() => props.stats.present_list?.length || 0);
const absentCount = computed(() => props.stats.absent_list?.length || 0);
const vacationCount = computed(() => props.stats.vacation_list?.length || 0);
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ isAdmin ? 'Panel de Control' : 'Mi Espacio' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- ========================== -->
                <!--    VISTA DE ADMINISTRADOR  -->
                <!-- ========================== -->
                <div v-if="isAdmin" class="space-y-6">
                    <!-- ... (Código de Admin IDÉNTICO, solo omitido para brevedad si no hubo cambios solicitados allí) ... -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-emerald-500 relative group">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Ventas Hoy</p>
                                    <h3 class="text-2xl font-black text-gray-800 mt-1">{{ formatCurrency(stats.sales_today) }}</h3>
                                </div>
                                <div class="p-2 bg-emerald-100 rounded-lg text-emerald-600"><i class="pi pi-dollar text-xl"></i></div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-xs">
                                <span class="text-gray-500">Gastos: <span class="font-semibold text-red-500">-{{ formatCurrency(stats.expenses_today) }}</span></span>
                                <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">Neto: {{ formatCurrency(stats.net_today) }}</span>
                            </div>
                        </div>

                        <Link :href="route('incident-requests.index')" class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-orange-500 group hover:shadow-md transition-shadow cursor-pointer relative">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider group-hover:text-orange-600 transition-colors">Solicitudes</p>
                                    <h3 class="text-2xl font-black text-gray-800 mt-1">{{ stats.pending_requests }}</h3>
                                </div>
                                <div class="p-2 bg-orange-100 rounded-lg text-orange-600 group-hover:bg-orange-200 transition-colors">
                                    <i class="pi pi-inbox text-xl" :class="{'animate-bounce': stats.pending_requests > 0}"></i>
                                </div>
                            </div>
                            <div v-if="stats.pending_requests > 0" class="absolute top-3 right-3 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500"></span>
                            </div>
                            <p class="text-xs text-gray-400 mt-2 group-hover:text-gray-600">Permisos por aprobar</p>
                        </Link>

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-blue-500 col-span-1 md:col-span-2">
                             <div class="flex justify-between items-center mb-4">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Resumen de Personal</p>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-bold">{{ stats.active_employees }} Activos</span>
                            </div>
                            <div class="flex gap-4 text-center">
                                <div class="flex-1 bg-blue-50 rounded-lg p-2">
                                    <span class="block text-xl font-black text-blue-700">{{ presentCount }}</span>
                                    <span class="text-xs text-blue-600 font-bold">Presentes</span>
                                </div>
                                <div class="flex-1 bg-red-50 rounded-lg p-2">
                                    <span class="block text-xl font-black text-red-700">{{ absentCount }}</span>
                                    <span class="text-xs text-red-600 font-bold">Faltan</span>
                                </div>
                                <div class="flex-1 bg-purple-50 rounded-lg p-2">
                                    <span class="block text-xl font-black text-purple-700">{{ vacationCount }}</span>
                                    <span class="text-xs text-purple-600 font-bold">Vacaciones</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paneles Detalle Admin -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white shadow-sm sm:rounded-xl border border-gray-100 flex flex-col h-64">
                            <div class="px-4 py-3 bg-blue-50 border-b border-blue-100 flex justify-between items-center rounded-t-xl">
                                <h3 class="font-bold text-blue-800 flex items-center gap-2 text-sm"><i class="pi pi-check-circle"></i> En turno ({{ presentCount }})</h3>
                            </div>
                            <div class="p-4 overflow-y-auto flex-grow space-y-3 custom-scrollbar">
                                <div v-for="emp in stats.present_list" :key="emp.id" class="flex items-center gap-3">
                                    <div class="relative">
                                        <img v-if="emp.photo" :src="emp.photo" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                                        <div v-else class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold">{{ emp.name[0] }}</div>
                                        <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></div>
                                    </div>
                                    <div class="leading-tight">
                                        <p class="text-sm font-bold text-gray-700">{{ emp.name }}</p>
                                        <p class="text-xs text-gray-400">Entrada: {{ emp.check_in }}</p>
                                    </div>
                                </div>
                                <div v-if="presentCount === 0" class="text-center py-10 text-gray-400 text-xs">No hay asistencias registradas aún.</div>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm sm:rounded-xl border border-gray-100 flex flex-col h-64">
                            <div class="px-4 py-3 bg-red-50 border-b border-red-100 flex justify-between items-center rounded-t-xl">
                                <h3 class="font-bold text-red-800 flex items-center gap-2 text-sm"><i class="pi pi-times-circle"></i> Ausentes / Por llegar ({{ absentCount }})</h3>
                            </div>
                            <div class="p-4 overflow-y-auto flex-grow space-y-3 custom-scrollbar">
                                <div v-for="emp in stats.absent_list" :key="emp.id" class="flex items-center gap-3 opacity-75 hover:opacity-100 transition-opacity">
                                    <div class="grayscale">
                                        <img v-if="emp.photo" :src="emp.photo" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                                        <div v-else class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold">{{ emp.name[0] }}</div>
                                    </div>
                                    <div class="leading-tight">
                                        <p class="text-sm font-bold text-gray-700">{{ emp.name }}</p>
                                        <p class="text-xs text-red-400 font-medium">Turno: {{ emp.start_time }}</p>
                                    </div>
                                </div>
                                <div v-if="absentCount === 0" class="text-center py-10 text-gray-400 text-xs"><i class="pi pi-thumbs-up text-2xl mb-2 text-gray-300"></i><br>Asistencia perfecta hoy.</div>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm sm:rounded-xl border border-gray-100 flex flex-col h-64">
                            <div class="px-4 py-3 bg-purple-50 border-b border-purple-100 flex justify-between items-center rounded-t-xl">
                                <h3 class="font-bold text-purple-800 flex items-center gap-2 text-sm"><i class="pi pi-sun"></i> De vacaciones ({{ vacationCount }})</h3>
                            </div>
                            <div class="p-4 overflow-y-auto flex-grow space-y-3 custom-scrollbar">
                                <div v-for="emp in stats.vacation_list" :key="emp.id" class="flex items-center gap-3">
                                    <div>
                                        <img v-if="emp.photo" :src="emp.photo" class="w-8 h-8 rounded-full object-cover border border-purple-200 ring-2 ring-purple-50">
                                        <div v-else class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs font-bold">{{ emp.name[0] }}</div>
                                    </div>
                                    <div class="leading-tight">
                                        <p class="text-sm font-bold text-gray-700">{{ emp.name }}</p>
                                        <p class="text-xs text-purple-400">Disfrutando descanso</p>
                                    </div>
                                </div>
                                <div v-if="vacationCount === 0" class="text-center py-10 text-gray-400 text-xs">Nadie está de vacaciones hoy.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Inferior Admin -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                                <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="pi pi-box text-red-500"></i> Inventario Crítico</h3>
                                <Link :href="route('products.index')" class="text-xs text-blue-600 hover:underline">Ver todo</Link>
                            </div>
                            <div class="p-0 overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                                        <tr>
                                            <th class="px-6 py-3 font-medium">Producto</th>
                                            <th class="px-6 py-3 font-medium text-right">Precio</th>
                                            <th class="px-6 py-3 font-medium text-center">Stock Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="product in stats.low_stock_products" :key="product.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-3 font-medium text-gray-900">{{ product.name }}</td>
                                            <td class="px-6 py-3 text-right text-gray-500">{{ formatCurrency(product.price) }}</td>
                                            <td class="px-6 py-3 text-center"><span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">{{ product.stock }}</span></td>
                                        </tr>
                                        <tr v-if="stats.low_stock_products.length === 0"><td colspan="3" class="px-6 py-8 text-center text-gray-400 italic"><i class="pi pi-check-circle text-2xl text-emerald-400 mb-2 block"></i>Inventario saludable</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="bg-white shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-100 bg-pink-50 flex justify-between items-center">
                                <h3 class="font-bold text-pink-800 flex items-center gap-2"><i class="pi pi-gift text-pink-500"></i> Cumpleaños</h3>
                            </div>
                            <div class="p-4 space-y-4">
                                <div v-for="(bday, idx) in stats.upcoming_birthdays" :key="idx" class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 overflow-hidden shrink-0 border border-gray-300">
                                        <img v-if="bday.photo" :src="bday.photo" class="w-full h-full object-cover">
                                        <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs font-bold">{{ bday.name[0] }}</div>
                                    </div>
                                    <div><p class="text-sm font-bold text-gray-800 leading-none">{{ bday.name }}</p><p class="text-xs text-pink-500 font-semibold mt-1"><i class="pi pi-calendar mr-1"></i> {{ bday.date }}</p></div>
                                </div>
                                <div v-if="stats.upcoming_birthdays.length === 0" class="text-center py-4 text-gray-400 text-xs italic">No hay cumpleaños cercanos.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================== -->
                <!--    VISTA DE EMPLEADO       -->
                <!-- ========================== -->
                <div v-else class="space-y-6">
                    
                    <!-- Bienvenida -->
                    <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 text-white shadow-lg flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h2 class="text-2xl font-bold">¡Hola, {{ employee.first_name }}! 👋</h2>
                            <p class="text-gray-300 text-sm mt-1">Aquí tienes el resumen de tu semana.</p>
                        </div>
                        <div v-if="stats.check_in_time" class="bg-green-500/20 border border-green-500/30 px-4 py-2 rounded-lg flex items-center gap-3">
                            <div class="h-2 w-2 rounded-full bg-green-50 animate-pulse"></div>
                            <span class="font-bold text-green-400">Entrada registrada: {{ stats.check_in_time }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Tarjeta 1: Próximo Turno -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
                            <div>
                                <div class="flex items-center gap-2 text-gray-400 mb-2">
                                    <i class="pi pi-calendar text-lg"></i>
                                    <span class="text-xs font-bold uppercase tracking-wider">Tu Próximo Turno</span>
                                </div>
                                <div v-if="stats.next_shift">
                                    <h3 class="text-3xl font-black text-gray-900 leading-tight capitalize">
                                        {{ stats.next_shift.is_today ? 'Hoy' : stats.next_shift.date }}
                                    </h3>
                                    <p class="text-lg text-gray-600 font-medium mt-1">
                                        {{ stats.next_shift.start_time }} - {{ stats.next_shift.end_time }}
                                    </p>
                                    <span class="inline-block mt-3 px-3 py-1 rounded-full text-xs font-bold text-white bg-blue-600" :style="{ backgroundColor: stats.next_shift.color }">
                                        {{ stats.next_shift.shift_name }}
                                    </span>
                                </div>
                                <div v-else class="text-gray-400 italic mt-4">
                                    No tienes turnos programados pronto.
                                </div>
                            </div>
                            <!-- Botón para abrir Modal de Horario -->
                            <button @click="showScheduleModal = true" class="mt-6 text-sm text-blue-600 font-bold hover:underline flex items-center gap-1">
                                Ver horario completo <i class="pi pi-arrow-right text-xs"></i>
                            </button>
                        </div>

                        <!-- Tarjeta 2: Nómina Estimada (Semanal) -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
                            <div>
                                <div class="flex items-center gap-2 text-gray-400 mb-2">
                                    <i class="pi pi-wallet text-lg"></i>
                                    <span class="text-xs font-bold uppercase tracking-wider">Estimado Semanal</span>
                                </div>
                                <h3 class="text-4xl font-black text-emerald-600 tracking-tight">
                                    {{ formatCurrency(stats.estimated_pay) }}
                                </h3>
                                <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
                                    <i class="pi pi-info-circle"></i> 
                                    <span>Calculado por <strong>{{ stats.worked_days }} días</strong> trab.</span>
                                </p>
                            </div>
                            <!-- FIX: Usamos getCurrentLocalDate() para evitar el salto de día por UTC -->
                            <Link :href="route('payroll.week', getCurrentLocalDate())" class="mt-6 text-sm text-emerald-600 font-bold hover:underline flex items-center gap-1">
                                Ver desglose <i class="pi pi-arrow-right text-xs"></i>
                            </Link>
                        </div>

                        <!-- Tarjeta 3: Vacaciones Disponibles -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
                            <div>
                                <div class="flex items-center gap-2 text-gray-400 mb-2">
                                    <i class="pi pi-briefcase text-lg"></i>
                                    <span class="text-xs font-bold uppercase tracking-wider">Vacaciones</span>
                                </div>
                                <h3 class="text-4xl font-black text-purple-600">
                                    {{ employee.vacation_balance }} <span class="text-lg text-gray-400 font-bold">días</span>
                                </h3>
                                <p class="text-sm text-gray-500 mt-2">Saldo disponible para usar.</p>
                            </div>
                            <!-- Link a Solicitar Días (Estilo corregido) -->
                            <Link :href="route('incident-requests.index')" class="mt-6 text-sm text-purple-600 font-bold hover:underline flex items-center gap-1">
                                Solicitar Días <i class="pi pi-arrow-right text-xs"></i>
                            </Link>
                        </div>

                    </div>

                    <!-- Sección Inferior: Cumpleaños -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-gift text-pink-500"></i> Próximos Cumpleaños
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div v-for="(bday, idx) in stats.upcoming_birthdays" :key="idx" class="flex items-center gap-3 bg-pink-50/50 p-3 rounded-xl border border-pink-100">
                                <div class="w-10 h-10 rounded-full bg-white overflow-hidden shrink-0 border border-gray-200 shadow-sm">
                                    <img v-if="bday.photo" :src="bday.photo" class="w-full h-full object-cover">
                                    <div v-else class="w-full h-full flex items-center justify-center text-pink-400 font-bold">{{ bday.name[0] }}</div>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">{{ bday.name }}</p>
                                    <p class="text-xs text-pink-500 font-semibold">{{ bday.date }}</p>
                                </div>
                            </div>
                            <div v-if="stats.upcoming_birthdays.length === 0" class="col-span-3 text-center text-gray-400 text-sm py-4 italic">
                                No hay cumpleaños cercanos en el equipo.
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- MODAL: Horario de la Semana -->
        <Dialog v-model:visible="showScheduleModal" modal header="Mi Horario de la Semana" :style="{ width: '30rem' }">
            <div v-if="stats.weekly_schedule && stats.weekly_schedule.length > 0" class="flex flex-col gap-3">
                <div v-for="day in stats.weekly_schedule" :key="day.id" 
                    class="flex justify-between items-center p-3 rounded-lg border border-gray-100"
                    :class="{'bg-blue-50 border-blue-200': day.is_today, 'bg-gray-50 opacity-75': day.is_rest}">
                    
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-8 rounded-full" :style="{ backgroundColor: day.color }"></div>
                        <div>
                            <p class="font-bold text-gray-800 capitalize">{{ day.date_label }} <span v-if="day.is_today" class="text-[10px] bg-blue-100 text-blue-700 px-1 rounded ml-1">HOY</span></p>
                            <p class="text-xs text-gray-500">{{ day.shift_name }}</p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <template v-if="!day.is_rest">
                            <p class="text-sm font-bold text-gray-900">{{ day.start_time }}</p>
                            <p class="text-xs text-gray-500">{{ day.end_time }}</p>
                        </template>
                        <template v-else>
                            <span class="text-xs font-bold bg-gray-200 text-gray-500 px-2 py-1 rounded">Descanso</span>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-6 text-gray-500">
                No hay horarios asignados para esta semana.
            </div>
            <template #footer>
                <Button label="Cerrar" icon="pi pi-check" @click="showScheduleModal = false" text />
            </template>
        </Dialog>

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
    background: #cbd5e1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; 
}
</style>