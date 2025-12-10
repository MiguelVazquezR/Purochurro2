<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    holidays: Array,
});

const confirm = useConfirm();
const toast = useToast();
const dt = ref();

// --- Filtros ---
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// --- Helpers de Formato ---
const formatDate = (dateString) => {
    if (!dateString) return '';
    // Forzamos la interpretación en zona horaria local para visualización
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// --- Eliminar ---
const deleteHoliday = (holiday) => {
    confirm.require({
        message: `¿Estás seguro de eliminar "${holiday.name}"?`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('holidays.destroy', holiday.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Día feriado eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Días Festivos">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Días festivos</h1>
                    <p class="text-surface-500 text-sm mt-1">Configura los días de descanso obligatorio y sus pagos.</p>
                </div>
                
                <div class="flex gap-3">
                    <Link :href="route('holidays.create')">
                        <Button 
                            label="Nuevo feriado" 
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
                    :value="holidays" 
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
                    @row-click="(e) => router.get(route('holidays.edit', e.data.id))"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Calendario Oficial</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar festividad..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-calendar-times !text-4xl mb-3 opacity-50"></i>
                            <p>No hay días feriados registrados.</p>
                        </div>
                    </template>

                    <!-- Columna: Fecha -->
                    <Column field="date" header="Fecha" sortable class="w-[180px]">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-calendar text-orange-500"></i>
                                <span class="text-surface-700 font-medium whitespace-nowrap">
                                    {{ formatDate(slotProps.data.date) }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Nombre -->
                    <Column field="name" header="Festividad" sortable class="min-w-[200px]">
                        <template #body="slotProps">
                            <span class="font-bold text-surface-800">{{ slotProps.data.name }}</span>
                        </template>
                    </Column>

                    <!-- Columna: Tipo (Obligatorio/Opcional) -->
                    <Column field="mandatory_rest" header="Tipo" sortable class="w-[150px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.mandatory_rest ? 'Obligatorio' : 'Opcional'" 
                                :severity="slotProps.data.mandatory_rest ? 'danger' : 'info'" 
                                rounded
                                class="!px-2 !py-0.5 !text-xs !font-medium"
                            >
                                <template #icon>
                                    <i :class="slotProps.data.mandatory_rest ? 'pi pi-lock' : 'pi pi-lock-open'" class="text-[10px] mr-1"></i>
                                </template>
                            </Tag>
                        </template>
                    </Column>

                    <!-- Columna: Multiplicador -->
                    <Column field="pay_multiplier" header="Pago" sortable class="w-[120px] text-right">
                        <template #body="slotProps">
                            <span class="text-surface-600 font-bold bg-surface-100 px-2 py-1 rounded-lg">
                                x{{ parseFloat(slotProps.data.pay_multiplier).toFixed(1) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1 action-btn">
                                <Link :href="route('holidays.edit', slotProps.data.id)">
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
                                    @click.stop="deleteHoliday(slotProps.data)"
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