<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';

const props = defineProps({
    weeks: Array,
});

const dt = ref();
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// --- Lógica de Estado Visual ---
const getStatusLabel = (week) => {
    if (week.is_current) return 'En curso';
    if (week.is_closed) return 'Cerrada';
    return 'Pendiente';
};

const getStatusSeverity = (week) => {
    if (week.is_current) return 'success'; // Verde
    if (week.is_closed) return 'secondary'; // Gris
    return 'warn'; // Naranja/Amarillo
};

// --- Navegación ---
const openWeek = (week) => {
    // Redirige a la vista de detalle de la semana (donde se gestionan incidencias)
    router.get(route('payroll.week', week.start_date));
};
</script>

<template>
    <AppLayout title="Periodos de Nómina">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado Simple (Sin botón de crear) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Periodos de Nómina</h1>
                    <p class="text-surface-500 text-sm mt-1">Historial de semanas y estatus de cierre.</p>
                </div>
            </div>

            <!-- Tabla Glassmorphism -->
            <div class="bg-white/80 backdrop-blur-xl border border-surface-200 rounded-3xl shadow-xl overflow-hidden p-1">
                <DataTable 
                    ref="dt"
                    :value="weeks" 
                    v-model:filters="filters"
                    :paginator="true" 
                    :rows="10"
                    class="p-datatable-sm"
                    :pt="{
                        root: { class: 'rounded-2xl overflow-hidden' },
                        header: { class: '!bg-transparent !border-0 !p-4' },
                        thead: { class: '!bg-surface-50' },
                        bodyRow: { class: 'hover:!bg-orange-50/50 transition-colors duration-200 cursor-pointer' }
                    }"
                    @row-click="(e) => openWeek(e.data)"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Semanas Recientes</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar periodo..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-calendar-times text-4xl mb-3 opacity-50"></i>
                            <p>No hay periodos registrados.</p>
                        </div>
                    </template>

                    <!-- Columna: Periodo -->
                    <Column field="label" header="Periodo" sortable class="min-w-[250px]">
                        <template #body="slotProps">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-surface-100 flex items-center justify-center text-surface-500 border border-surface-200">
                                    <i class="pi pi-calendar"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-surface-800">{{ slotProps.data.label }}</span>
                                    <span class="text-xs text-surface-500 font-mono">Inicio: {{ slotProps.data.start_date }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Estado -->
                    <Column header="Estado" field="is_current" sortable class="w-[150px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStatusLabel(slotProps.data)" 
                                :severity="getStatusSeverity(slotProps.data)" 
                                rounded
                                class="!px-3 !py-1 !font-medium"
                            >
                                <template #icon>
                                    <i v-if="slotProps.data.is_current" class="pi pi-spin pi-spinner text-xs mr-2"></i>
                                    <i v-else-if="slotProps.data.is_closed" class="pi pi-check-circle text-xs mr-2"></i>
                                    <i v-else class="pi pi-clock text-xs mr-2"></i>
                                </template>
                            </Tag>
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <Button 
                                icon="pi pi-chevron-right" 
                                text 
                                rounded 
                                severity="secondary" 
                                class="!w-8 !h-8 !text-surface-400 hover:!text-orange-600 hover:!bg-orange-50"
                                @click.stop="openWeek(slotProps.data)"
                            />
                        </template>
                    </Column>

                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-wrapper) {
    border-radius: 0; 
}
</style>