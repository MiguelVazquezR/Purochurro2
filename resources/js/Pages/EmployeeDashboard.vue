<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

const props = defineProps({
    employee: Object,
    stats: Object,
});

const showScheduleModal = ref(false);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2
    }).format(value || 0);
};

const getCurrentLocalDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Bienvenida -->
        <!-- ID TOUR: Bienvenida Emp -->
        <div id="tour-emp-welcome" class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 text-white shadow-lg flex flex-col sm:flex-row justify-between items-center gap-4">
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
            <!-- ID TOUR: Turno -->
            <div id="tour-emp-shift" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
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
                <button @click="showScheduleModal = true" class="mt-6 text-sm text-blue-600 font-bold hover:underline flex items-center gap-1">
                    Ver horario completo <i class="pi pi-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- Tarjeta 2: Nómina Estimada (Semanal) -->
            <!-- ID TOUR: Nómina -->
            <div id="tour-emp-payroll" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
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
                        <span>Calculado por <strong>{{ stats.worked_days }} turnos</strong> trab.</span>
                    </p>
                </div>
                <Link :href="route('payroll.week', getCurrentLocalDate())" class="mt-6 text-sm text-emerald-600 font-bold hover:underline flex items-center gap-1">
                    Ver desglose <i class="pi pi-arrow-right text-xs"></i>
                </Link>
            </div>

            <!-- Tarjeta 3: Vacaciones Disponibles -->
            <!-- ID TOUR: Vacaciones -->
            <div id="tour-emp-vacation" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between h-full">
                <div>
                    <div class="flex items-center gap-2 text-gray-400 mb-2">
                        <i class="pi pi-briefcase text-lg"></i>
                        <span class="text-xs font-bold uppercase tracking-wider">Vacaciones</span>
                    </div>
                    <h3 class="text-4xl font-black text-purple-600">
                        {{ employee.vacation_balance }} <span class="text-lg text-gray-400 font-bold">días</span>
                    </h3>
                    <div class="text-sm text-gray-500 mt-2">
                        <p>Saldo disponible.</p>
                        <p class="text-xs text-gray-400 mt-1">
                            Meta anual: {{ employee.entitled_days }} días
                        </p>
                    </div>
                </div>
                <div v-if="Number(employee.vacation_balance) >= Number(employee.entitled_days)">
                    <Link :href="route('incident-requests.index')" class="mt-6 text-sm text-purple-600 font-bold hover:underline flex items-center gap-1">
                        Solicitar Días <i class="pi pi-arrow-right text-xs"></i>
                    </Link>
                </div>
                <div v-else class="mt-6 group relative">
                    <span class="text-sm text-gray-300 font-bold flex items-center gap-1 cursor-not-allowed">
                        Solicitar Días <i class="pi pi-lock text-xs"></i>
                    </span>
                    <div class="absolute bottom-full left-0 mb-2 hidden group-hover:block w-48 bg-gray-800 text-white text-xs rounded p-2 z-10">
                        Debes acumular tus {{ employee.entitled_days }} días completos para solicitar.
                    </div>
                </div>
            </div>

        </div>

        <!-- Sección Inferior: Novedades y Cumpleaños -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Bitácoras (Emp) -->
            <div class="bg-white rounded-2xl shadow-sm border border-orange-200 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-orange-100 bg-orange-50 flex justify-between items-center">
                    <h3 class="font-bold text-orange-800 flex items-center gap-2">
                        <i class="pi pi-book text-orange-500"></i> Avisos sin leer
                        <span v-if="stats.unread_logbooks_count > 0" class="bg-orange-500 text-white text-[10px] px-2 py-0.5 rounded-full">{{ stats.unread_logbooks_count }}</span>
                    </h3>
                    <Link :href="route('logbooks.index')" class="text-xs text-orange-600 hover:text-orange-800 font-bold hover:underline">Ver bitácora</Link>
                </div>
                <div class="p-4 space-y-3 flex-1">
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
                    <div v-if="stats.unread_logbooks && stats.unread_logbooks.length === 0" class="h-full flex flex-col items-center justify-center py-6 text-gray-400 text-sm italic">
                        <i class="pi pi-check-circle text-3xl text-green-400 mb-2 block"></i>
                        Estás al día.
                    </div>
                </div>
            </div>

            <!-- ID TOUR: Cumpleaños (Emp) -->
            <div id="tour-birthdays" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 bg-pink-50 flex justify-between items-center">
                    <h3 class="font-bold text-pink-800 flex items-center gap-2">
                        <i class="pi pi-gift text-pink-500"></i> Próximos Cumpleaños
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1 items-start">
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
                    <div v-if="stats.upcoming_birthdays.length === 0" class="col-span-full h-full flex flex-col items-center justify-center py-6 text-gray-400 text-sm italic">
                        <i class="pi pi-calendar text-3xl text-pink-200 mb-2 block"></i>
                        No hay cumpleaños cercanos.
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
    </div>
</template>