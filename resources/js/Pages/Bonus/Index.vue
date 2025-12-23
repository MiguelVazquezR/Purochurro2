<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

// Componentes PrimeVue
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';

const props = defineProps({
    bonuses: Array,
});

const confirm = useConfirm();
const toast = useToast();
const dt = ref();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// --- Mapas de Traducción para Reglas ---
const conceptMap = {
    'late_minutes': 'Min. retardo',
    'extra_minutes': 'Min. extra',
    'unjustified_absences': 'Faltas',
    'attendance': 'Días asistencia'
};

const formatAmount = (amount, type) => {
    if (type === 'percentage') {
        return `${parseFloat(amount).toFixed(2)}%`;
    }
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
};

// Helper para mostrar resumen de la regla
const getRuleSummary = (rule) => {
    if (!rule) return 'Se otorga siempre';
    
    const concept = conceptMap[rule.concept] || rule.concept;
    return `${concept} ${rule.operator} ${rule.value}`;
};

const deleteBonus = (bonus) => {
    confirm.require({
        message: `¿Estás seguro de eliminar el bono "${bonus.name}"?`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('bonuses.destroy', bonus.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Bono eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Catálogo de Bonos">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Bonos e incentivos</h1>
                    <p class="text-surface-500 text-sm mt-1">Administra los tipos de bonos y sus reglas de aplicación automática.</p>
                </div>
                
                <div class="flex gap-3">
                    <Link :href="route('bonuses.create')">
                        <Button 
                            label="Nuevo bono" 
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
                    :value="bonuses" 
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
                    @row-click="(e) => router.get(route('bonuses.edit', e.data.id))"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Listado de conceptos</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar bono..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-star text-4xl mb-3 opacity-50"></i>
                            <p>No hay bonos registrados.</p>
                        </div>
                    </template>

                    <!-- Columna: Nombre -->
                    <Column field="name" header="Concepto" sortable class="min-w-[200px]">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-800">{{ slotProps.data.name }}</span>
                                <span v-if="slotProps.data.description" class="text-xs text-surface-500 truncate max-w-[250px]">
                                    {{ slotProps.data.description }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Regla (Actualizada) -->
                    <Column header="Aplicación" class="w-[200px]">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" 
                                     :class="slotProps.data.rule_config ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600'">
                                    <i class="pi" :class="slotProps.data.rule_config ? 'pi-list-check' : 'pi-check-circle'"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold" 
                                          :class="slotProps.data.rule_config ? 'text-purple-700' : 'text-blue-700'">
                                        {{ slotProps.data.rule_config ? 'Condicionado' : 'Directo' }}
                                    </span>
                                    <span class="text-[10px] text-surface-500 leading-tight">
                                        {{ getRuleSummary(slotProps.data.rule_config) }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Tipo -->
                    <Column field="type" header="Tipo" sortable class="w-[100px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.type === 'fixed' ? 'Fijo' : '%'" 
                                :severity="slotProps.data.type === 'fixed' ? 'secondary' : 'info'" 
                                rounded
                                class="!px-2 !py-0.5 !text-xs !font-medium"
                            />
                        </template>
                    </Column>

                    <!-- Columna: Monto -->
                    <Column field="amount" header="Valor" sortable class="w-[120px] text-right">
                        <template #body="slotProps">
                            <div class="flex flex-col items-end">
                                <span class="text-surface-900 font-bold text-base">
                                    {{ formatAmount(slotProps.data.amount, slotProps.data.type) }}
                                </span>
                                <span v-if="slotProps.data.rule_config?.behavior === 'pay_per_unit'" class="text-[9px] font-bold text-orange-600 bg-orange-50 px-1 rounded">
                                    por unidad
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Estado -->
                    <Column field="is_active" header="Estado" sortable class="w-[100px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.is_active ? 'Activo' : 'Inactivo'" 
                                :severity="slotProps.data.is_active ? 'success' : 'danger'" 
                                rounded
                                class="!px-2 !py-0.5 !text-xs !font-medium"
                            />
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1 action-btn">
                                <Link :href="route('bonuses.edit', slotProps.data.id)">
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
                                    @click.stop="deleteBonus(slotProps.data)"
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