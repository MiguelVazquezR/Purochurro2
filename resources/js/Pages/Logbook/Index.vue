<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue'; 
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

// Importamos nuestros componentes modales
import CreateLogbookModal from './Partials/CreateLogbookModal.vue';
import ShowLogbookModal from './Partials/ShowLogbookModal.vue';
import EditLogbookModal from './Partials/EditLogbookModal.vue';

const props = defineProps({
    logbooks: {
        type: Array,
        default: () => []
    },
    currentUserId: Number,
    weekStart: String,
    weekEnd: String,
    weekOffset: {
        type: Number,
        default: 0
    }
});

const toast = useToast();
const confirm = useConfirm();
const page = usePage();

// Modales de estado
const isCreateModalOpen = ref(false);
const isShowModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedLogbook = ref(null);

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Éxito', detail: flash.success, life: 3000 });
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Error', detail: flash.error, life: 5000 });
    }
}, { deep: true, immediate: true });

const handleModalSuccess = (message) => {
    toast.add({ severity: 'success', summary: 'Éxito', detail: message, life: 3000 });
};

const changeWeek = (direction) => {
    router.get(route('logbooks.index'), { offset: props.weekOffset + direction }, {
        preserveState: true,
        preserveScroll: true
    });
};

const hasRead = (logbook) => {
    return logbook.readers.some(reader => reader.id === props.currentUserId);
};

// Acciones de UI
const openShowModal = (logbook) => {
    selectedLogbook.value = logbook;
    isShowModalOpen.value = true;
};

const openEditModal = (logbook) => {
    selectedLogbook.value = logbook;
    isEditModalOpen.value = true;
};

const confirmDelete = (logbookId) => {
    confirm.require({
        message: '¿Estás seguro de que deseas eliminar este reporte? Esta acción no se puede deshacer.',
        header: 'Confirmar eliminación',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Sí, eliminar',
        rejectLabel: 'Cancelar',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('logbooks.destroy', logbookId), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Éxito', detail: 'Reporte eliminado.', life: 3000 });
                }
            });
        }
    });
};

// Actualizamos el estado local (Vue) para que refleje instantáneamente que ya se leyó
const handleReadConfirmed = (logbookId, user) => {
    const log = props.logbooks.find(l => l.id === logbookId);
    if (log && !log.readers.some(r => r.id === user.id)) {
        log.readers.push({ id: user.id, name: user.name });
    }
};

// Agrupar bitácoras asegurando el orden de Domingo a Sábado
const logbooksByDay = computed(() => {
    const daysMap = [
        { name: 'Domingo', logs: [] },
        { name: 'Lunes', logs: [] },
        { name: 'Martes', logs: [] },
        { name: 'Miércoles', logs: [] },
        { name: 'Jueves', logs: [] },
        { name: 'Viernes', logs: [] },
        { name: 'Sábado', logs: [] }
    ];

    props.logbooks.forEach(logbook => {
        const date = new Date(logbook.created_at);
        const dayIndex = date.getDay();
        daysMap[dayIndex].logs.push(logbook);
    });

    return daysMap;
});

const formatTime = (dateString) => {
    const options = { hour: 'numeric', minute: 'numeric', hour12: true };
    return new Intl.DateTimeFormat('es-MX', options).format(new Date(dateString));
};

const totalLogs = computed(() => props.logbooks.length);
</script>

<template>
    <AppLayout title="Bitácoras">
            <div class="flex justify-between items-center w-full">
                <h2 class="font-semibold text-xl text-gray-800 tracking-tight">
                    Bitácora de operaciones
                </h2>
                <Button 
                    label="Nueva entrada" 
                    icon="pi pi-plus" 
                    rounded
                    size="small"
                    class="!px-4 !py-2 !text-sm !font-medium"
                    @click="isCreateModalOpen = true"
                />
            </div>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <!-- Leyenda Destacada (Estilo Apple Alert) -->
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-100/50 rounded-2xl p-4 mb-6 flex gap-4 items-start shadow-sm backdrop-blur-md">
                    <div class="bg-orange-100/80 text-orange-600 p-2.5 rounded-xl shrink-0 shadow-inner">
                        <i class="pi pi-exclamation-triangle text-xl"></i>
                    </div>
                    <div class="pt-0.5">
                        <span class="font-bold text-orange-900 text-xl block mb-0.5 tracking-tight">Finalidad de esta bitácora</span>
                        <span class="text-orange-800/80 text-lg leading-relaxed inline-block">
                            Este espacio es exclusivo para <strong>mejorar nuestro flujo de trabajo</strong>. Registra aquí puntos a mejorar, refuerzo de buenas acciones, prevenciones para los siguientes turnos y observaciones operativas.
                            <span class="font-bold underline decoration-orange-300/70 underline-offset-2">No es para conflictos personales.</span>
                            Toda la información es pública para el equipo con el fin de mantener a todos enterados y evitar que alguien trabaje de más o de forma incorrecta.
                        </span>
                    </div>
                </div>

                <!-- Navegación de Semanas (Estilo Segmented Control de iOS) -->
                <div class="flex items-center justify-between bg-white border border-gray-200/60 p-1.5 rounded-2xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] mb-8">
                    <Button 
                        icon="pi pi-chevron-left" 
                        text 
                        rounded 
                        severity="secondary" 
                        class="!w-10 !h-10"
                        @click="changeWeek(-1)" 
                    />
                    <div class="flex flex-col items-center justify-center cursor-default select-none px-4">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">
                            {{ weekOffset === 0 ? 'Semana Actual' : 'Semana' }}
                        </span>
                        <span class="text-sm font-semibold text-gray-800 tracking-tight">{{ weekStart }} - {{ weekEnd }}</span>
                    </div>
                    <Button 
                        icon="pi pi-chevron-right" 
                        text 
                        rounded 
                        severity="secondary" 
                        class="!w-10 !h-10"
                        :disabled="weekOffset >= 0" 
                        @click="changeWeek(1)" 
                    />
                </div>

                <!-- Feed de Bitácoras (Estilo Lista de Notificaciones/Mail) -->
                <div v-if="totalLogs > 0" class="space-y-6">
                    <template v-for="day in logbooksByDay" :key="day.name">
                        <!-- Solo mostramos los días que tienen registros para ser ultra compactos -->
                        <div v-if="day.logs.length > 0">
                            <!-- Cabecera del día -->
                            <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3 ml-2 flex items-center gap-2">
                                {{ day.name }}
                                <span class="bg-gray-100 text-gray-500 py-0.5 px-2 rounded-full text-[10px]">{{ day.logs.length }}</span>
                            </h3>

                            <!-- Lista de registros del día -->
                            <div class="flex flex-col gap-2">
                                <div v-for="log in day.logs" :key="log.id" 
                                     class="group flex flex-col sm:flex-row sm:items-center justify-between p-3.5 bg-white rounded-2xl border border-gray-100 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.04)] hover:shadow-[0_8px_16px_-4px_rgba(0,0,0,0.08)] hover:border-gray-200 transition-all duration-300 gap-4 sm:gap-0"
                                     :class="!hasRead(log) ? 'bg-blue-500/10' : ''">
                                    
                                    <!-- Información principal (Izquierda) -->
                                    <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4 cursor-pointer" @click="openShowModal(log)">
                                        <!-- Punto de "No leído" estilo Apple Mail -->
                                        <div class="w-2.5 h-2.5 rounded-full shrink-0 transition-colors" 
                                             :class="hasRead(log) ? 'bg-transparent' : 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]'">
                                        </div>
                                        
                                        <Avatar :image="log.author?.profile_photo_path || `https://ui-avatars.com/api/?name=${log.author?.name}&background=EBF4FF&color=7F9CF5`" 
                                                shape="circle" 
                                                class="w-10 h-10 shrink-0 border border-gray-100 shadow-sm" />
                                        
                                        <div class="flex flex-col min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <span class="font-semibold text-sm text-gray-900 truncate tracking-tight">{{ log.author?.name }}</span>
                                                <span class="text-[11px] text-gray-400 font-medium shrink-0">{{ formatTime(log.created_at) }}</span>
                                            </div>
                                            
                                            <!-- Indicadores de contenido -->
                                            <div class="flex items-center gap-2 text-xs truncate" :class="hasRead(log) ? 'text-gray-400' : 'text-blue-600/80'">
                                                <i class="pi" :class="hasRead(log) ? 'pi-check text-gray-400' : 'pi-lock'" style="font-size: 0.7rem"></i>
                                                <span class="truncate font-medium">{{ hasRead(log) ? 'Leído' : 'Toque para leer reporte' }}</span>
                                                
                                                <template v-if="log.media && log.media.length > 0">
                                                    <span class="text-gray-300 mx-0.5">•</span>
                                                    <i class="pi pi-images text-gray-400" style="font-size: 0.7rem"></i>
                                                    <span class="text-gray-500">{{ log.media.length }}</span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Acciones (Derecha) -->
                                    <div class="flex items-center justify-end gap-1.5 shrink-0 pl-11 sm:pl-0">
                                        <!-- Botones Editar/Eliminar (aparecen en hover en desktop, o sutiles en móvil) -->
                                        <div class="flex gap-1 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity duration-200 mr-2" 
                                             v-if="log.author.id === currentUserId || currentUserId === 1">
                                            <Button icon="pi pi-pencil" text rounded severity="secondary" size="small" class="!w-8 !h-8 !p-0 text-gray-400 hover:text-gray-700" aria-label="Editar" @click.stop="openEditModal(log)" />
                                            <Button icon="pi pi-trash" text rounded severity="danger" size="small" class="!w-8 !h-8 !p-0 text-gray-400 hover:text-red-500" aria-label="Eliminar" @click.stop="confirmDelete(log.id)" />
                                        </div>

                                        <!-- Botón principal de acción -->
                                        <Button 
                                            :label="hasRead(log) ? 'Abrir' : 'Leer'" 
                                            size="small" 
                                            :severity="hasRead(log) ? 'secondary' : 'info'" 
                                            :text="hasRead(log)"
                                            :rounded="!hasRead(log)"
                                            class="!text-xs !font-semibold transition-all"
                                            :class="hasRead(log) ? '!px-3 !py-1.5 hover:bg-gray-50' : '!px-4 !py-1.5 shadow-sm'"
                                            @click="openShowModal(log)" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- Estado Vacío -->
                <div v-else class="text-center py-16 px-4 bg-white/50 rounded-3xl border border-gray-100/50 border-dashed">
                    <div class="bg-gray-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="pi pi-inbox text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-gray-900 font-semibold text-base mb-1">Semana tranquila</h3>
                    <p class="text-gray-500 text-sm">No hay bitácoras registradas en este periodo.</p>
                </div>

                <!-- Modales -->
                <CreateLogbookModal 
                    v-model:visible="isCreateModalOpen" 
                    @success="handleModalSuccess"
                />

                <ShowLogbookModal 
                    v-model:visible="isShowModalOpen" 
                    :logbook="selectedLogbook"
                    :currentUserId="currentUserId"
                    @read-confirmed="handleReadConfirmed"
                />

                <EditLogbookModal 
                    v-model:visible="isEditModalOpen" 
                    :logbook="selectedLogbook"
                    @success="handleModalSuccess"
                />

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Transiciones suaves generales */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style>