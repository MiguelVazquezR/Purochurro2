<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2
    }).format(value || 0);
};

const attendancePercentage = computed(() => {
    if (!props.stats.active_employees) return 0;
    return Math.round((props.stats.attendance_today / props.stats.active_employees) * 100);
});
const presentCount = computed(() => props.stats.present_list?.length || 0);
const absentCount = computed(() => props.stats.absent_list?.length || 0);
const vacationCount = computed(() => props.stats.vacation_list?.length || 0);
</script>

<template>
    <div class="space-y-6">
        <!-- ID TOUR: Stats Principales -->
        <div id="tour-admin-stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
        <!-- ID TOUR: Asistencia Detallada -->
        <div id="tour-admin-attendance" class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
            <!-- ID TOUR: Inventario -->
            <div id="tour-admin-inventory" class="lg:col-span-2 bg-white shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 flex flex-col">
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
            
            <div class="space-y-6 flex flex-col">
                <!-- Bitácoras (Admin) -->
                <div class="bg-white shadow-sm sm:rounded-xl overflow-hidden border border-orange-200 flex flex-col flex-1">
                    <div class="px-6 py-4 border-b border-orange-100 bg-orange-50 flex justify-between items-center">
                        <h3 class="font-bold text-orange-800 flex items-center gap-2">
                            <i class="pi pi-book text-orange-500"></i> Bitácoras sin leer
                            <span v-if="stats.unread_logbooks_count > 0" class="bg-orange-500 text-white text-[10px] px-2 py-0.5 rounded-full">{{ stats.unread_logbooks_count }}</span>
                        </h3>
                        <Link :href="route('logbooks.index')" class="text-xs text-orange-600 hover:text-orange-800 font-bold hover:underline">Ir a reportes</Link>
                    </div>
                    <div class="p-4 space-y-3">
                        <div v-for="log in stats.unread_logbooks" :key="log.id" class="flex gap-3 bg-white p-3 rounded-xl border border-gray-100 hover:border-orange-200 transition-colors group cursor-pointer shadow-sm" @click="router.get(route('logbooks.index'))">
                            <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 border border-gray-200">
                                <img :src="log.author_photo" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-0.5">
                                    <span class="font-bold text-gray-800 text-sm truncate">{{ log.author_name }}</span>
                                    <span class="text-[10px] font-semibold text-orange-500 whitespace-nowrap ml-2">{{ log.date }}</span>
                                </div>
                                <p class="text-xs text-gray-500 truncate">{{ log.preview }}</p>
                            </div>
                        </div>
                        <div v-if="stats.unread_logbooks && stats.unread_logbooks.length === 0" class="text-center py-6 text-gray-400 text-sm italic flex flex-col items-center">
                            <i class="pi pi-check-circle text-2xl text-green-400 mb-2 block"></i>
                            Estás al día.
                        </div>
                    </div>
                </div>

                <!-- ID TOUR: Cumpleaños (Admin) -->
                <div id="tour-birthdays" class="bg-white shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 flex flex-col">
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
    </div>
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