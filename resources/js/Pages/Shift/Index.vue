<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    shifts: Array,
});

const confirm = useConfirm();
const toast = useToast();
const dt = ref();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// Formato de hora simple (09:00:00 -> 09:00 AM)
const formatTime = (time) => {
    if (!time) return '';
    const [hours, minutes] = time.split(':');
    const date = new Date();
    date.setHours(hours);
    date.setMinutes(minutes);
    return date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
};

const deleteShift = (shift) => {
    confirm.require({
        message: `¿Estás seguro de eliminar el turno "${shift.name}"?`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('shifts.destroy', shift.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Turno eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Turnos laborales">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Turnos</h1>
                    <p class="text-surface-500 text-sm mt-1">Configura los horarios de trabajo disponibles.</p>
                </div>
                
                <div class="flex gap-3">
                    <Link :href="route('shifts.create')">
                        <Button 
                            label="Nuevo turno" 
                            icon="pi pi-plus" 
                            class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-semibold shadow-lg shadow-orange-200/50" 
                            rounded 
                        />
                    </Link>
                </div>
            </div>

            <!-- Tabla Glassmorphism -->
            <div class="bg-white/80 backdrop-blur-xl border border-surface-200 rounded-3xl shadow-xl overflow-hidden p-1">
                
                <DataTable 
                    ref="dt"
                    :value="shifts" 
                    v-model:filters="filters"
                    dataKey="id" 
                    :paginator="true" 
                    :rows="10"
                    class="p-datatable-sm"
                    :pt="{
                        root: { class: 'rounded-2xl overflow-hidden' },
                        header: { class: '!bg-transparent !border-0 !p-4' },
                        thead: { class: '!bg-surface-50' },
                        bodyRow: { class: 'hover:!bg-orange-50/50 transition-colors duration-200 cursor-pointer' }
                    }"
                    @row-click="(e) => router.get(route('shifts.edit', e.data.id))"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Catálogo de horarios</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar turno..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-clock text-4xl mb-3 opacity-50"></i>
                            <p>No hay turnos registrados.</p>
                        </div>
                    </template>

                    <!-- Columna: Visual (Color + Nombre) -->
                    <Column field="name" header="Turno" sortable class="min-w-[200px]">
                        <template #body="slotProps">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded-full border border-surface-300 shadow-sm" :style="{ backgroundColor: slotProps.data.color || '#cccccc' }"></div>
                                <span class="font-bold text-surface-800">{{ slotProps.data.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Horario -->
                    <Column header="Horario" sortable field="start_time" class="w-[260px]">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2 text-surface-600 bg-surface-50 px-3 py-1 rounded-lg w-fit">
                                <i class="pi pi-clock text-orange-500"></i>
                                <span class="text-sm font-medium">
                                    {{ formatTime(slotProps.data.start_time) }} - {{ formatTime(slotProps.data.end_time) }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Estado -->
                    <Column field="is_active" header="Estado" sortable class="w-[100px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.is_active ? 'Activo' : 'Inactivo'" 
                                :severity="slotProps.data.is_active ? 'success' : 'secondary'" 
                                rounded
                                class="!px-2 !py-0.5 !text-xs !font-medium"
                            />
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1 action-btn">
                                <Link :href="route('shifts.edit', slotProps.data.id)">
                                    <Button 
                                        icon="pi pi-pencil" 
                                        text 
                                        rounded 
                                        severity="secondary" 
                                        class="!w-8 !h-8 !text-surface-400 hover:!text-orange-600 hover:!bg-orange-50"
                                        v-tooltip.top="'Editar'"
                                    />
                                </Link>
                                <Button 
                                    icon="pi pi-trash" 
                                    text 
                                    rounded 
                                    severity="danger" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-red-600 hover:!bg-red-50"
                                    v-tooltip.top="'Eliminar'"
                                    @click.stop="deleteShift(slotProps.data)"
                                />
                            </div>
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