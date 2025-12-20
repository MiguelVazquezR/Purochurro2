<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    expenses: Array,
});

const confirm = useConfirm();
const toast = useToast();
const dt = ref();

// --- Filtros ---
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

// --- Helpers de Formato ---
const formatCurrency = (value) => {
    if(value === undefined || value === null) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// --- Acciones ---
const editExpense = (id) => {
    router.get(route('expenses.edit', id));
};

const deleteExpense = (expense) => {
    confirm.require({
        message: `¿Estás seguro de eliminar el gasto "${expense.concept}"?`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('expenses.destroy', expense.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Gasto eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Gastos">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Gastos</h1>
                    <p class="text-surface-500 text-sm mt-1">Control de salidas de dinero y compras.</p>
                </div>
                
                <div class="flex gap-3">
                    <Link :href="route('expenses.create')">
                        <Button 
                            label="Registrar gasto" 
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
                    :value="expenses" 
                    v-model:filters="filters"
                    dataKey="id" 
                    :paginator="true" 
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50]"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords} gastos"
                    :globalFilterFields="['concept', 'user.name', 'amount']"
                    class="p-datatable-sm"
                    :pt="{
                        root: { class: 'rounded-2xl overflow-hidden' },
                        header: { class: '!bg-transparent !border-0 !p-4' },
                        thead: { class: '!bg-surface-50' },
                        bodyRow: { class: 'hover:!bg-orange-50/50 transition-colors duration-200' }
                    }"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Historial</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar concepto..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-receipt !text-4xl mb-3 opacity-50"></i>
                            <p>No hay gastos registrados.</p>
                        </div>
                    </template>

                    <!-- Columna: Fecha -->
                    <Column field="date" header="Fecha" sortable class="w-[120px]">
                        <template #body="slotProps">
                            <span class="text-surface-600 font-medium text-sm whitespace-nowrap">
                                {{ formatDate(slotProps.data.date) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Columna: Concepto -->
                    <Column field="concept" header="Concepto" sortable class="min-w-[200px]">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-800">{{ slotProps.data.concept }}</span>
                                <span v-if="slotProps.data.notes" class="text-xs text-surface-500 truncate max-w-[200px]">
                                    {{ slotProps.data.notes }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Usuario -->
                    <Column field="user.name" header="Registrado por" sortable class="w-[150px] hidden md:table-cell">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <Avatar 
                                    :label="slotProps.data.user.name.charAt(0)" 
                                    shape="circle" 
                                    size="small"
                                    class="!bg-surface-200 !text-surface-600 !text-xs" 
                                />
                                <span class="text-surface-600 text-sm">{{ slotProps.data.user.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Monto -->
                    <Column field="amount" header="Monto" sortable class="w-[120px] text-right">
                        <template #body="slotProps">
                            <span class="text-surface-900 font-bold text-base">
                                {{ formatCurrency(slotProps.data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1">
                                <Button 
                                    icon="pi pi-pencil" 
                                    text 
                                    rounded 
                                    severity="secondary" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-orange-600 hover:!bg-orange-50"
                                    v-tooltip.top="'Editar'"
                                    @click="editExpense(slotProps.data.id)"
                                />
                                <Button 
                                    icon="pi pi-trash" 
                                    text 
                                    rounded 
                                    severity="danger" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-red-600 hover:!bg-red-50"
                                    v-tooltip.top="'Eliminar'"
                                    @click="deleteExpense(slotProps.data)"
                                />
                            </div>
                        </template>
                    </Column>

                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>