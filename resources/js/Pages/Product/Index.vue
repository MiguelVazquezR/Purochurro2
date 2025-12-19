<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';

const props = defineProps({
    products: Array,
});

const confirm = useConfirm();
const toast = useToast();
const dt = ref();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const formatCurrency = (value) => {
    if(value === undefined || value === null) return '$0.00';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const getStockSeverity = (product) => {
    if (!product.track_inventory) return 'info';
    const stock = product.stock || 0; 
    if (stock === 0) return 'danger';
    if (stock < 10) return 'warn';
    return 'success';
};

const getStockLabel = (product) => {
    if (!product.track_inventory) return 'N/A';
    const stock = product.stock || 0;
    return stock === 0 ? 'Agotado' : `${stock} u.`;
};

const onRowClick = (event) => {
    if (event.originalEvent.target.closest('.action-btn')) return;
    router.visit(route('products.show', event.data.id));
};

const deleteProduct = (product) => {
    confirm.require({
        message: `¿Estás seguro de eliminar "${product.name}"?`,
        header: 'Confirmar Eliminación',
        icon: 'pi pi-info-circle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('products.destroy', product.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Producto eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <AppLayout title="Productos">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado con Botones de Navegación -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Productos</h1>
                    <p class="text-surface-500 text-sm mt-1">Gestiona tu catálogo, precios e inventario.</p>
                </div>
                
                <div class="flex flex-wrap gap-3">
                    <!-- Botones de Acceso Rápido a Inventario -->
                    <Link :href="route('stock-transfers.index')">
                        <Button 
                            label="Traspasos" 
                            icon="pi pi-arrows-h" 
                            severity="help" 
                            outlined
                            class="!font-bold shadow-sm bg-white" 
                            rounded 
                        />
                    </Link>
                    <Link :href="route('stock-adjustments.index')">
                        <Button 
                            label="Ajustes / Compras" 
                            icon="pi pi-sliders-h" 
                            severity="info" 
                            outlined
                            class="!font-bold shadow-sm bg-white" 
                            rounded 
                        />
                    </Link>

                    <Link :href="route('products.create')">
                        <Button 
                            label="Nuevo Producto" 
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
                    :value="products" 
                    v-model:filters="filters"
                    dataKey="id" 
                    :paginator="true" 
                    :rows="10"
                    :rowsPerPageOptions="[10, 20, 50]"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords} productos"
                    :globalFilterFields="['name', 'barcode']"
                    selectionMode="single"
                    @row-click="onRowClick"
                    class="p-datatable-sm"
                    :pt="{
                        root: { class: 'rounded-2xl overflow-hidden' },
                        header: { class: '!bg-transparent !border-0 !p-4' },
                        thead: { class: '!bg-surface-50' },
                        bodyRow: { class: 'hover:!bg-orange-50/50 transition-colors duration-200 cursor-pointer' }
                    }"
                >
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Catálogo</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="filters['global'].value" 
                                    placeholder="Buscar producto..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-box !text-4xl mb-3 opacity-50"></i>
                            <p>No se encontraron productos.</p>
                        </div>
                    </template>

                    <!-- Imagen -->
                    <Column header="Imagen" class="w-[80px] md:w-[100px]">
                        <template #body="slotProps">
                            <div class="relative w-12 h-12 rounded-xl overflow-hidden shadow-sm border border-surface-100 bg-white group">
                                <img 
                                    v-if="slotProps.data.image_url" 
                                    :src="slotProps.data.image_url" 
                                    :alt="slotProps.data.name" 
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" 
                                />
                                <div v-else class="w-full h-full flex items-center justify-center bg-surface-50 text-surface-300">
                                    <i class="pi pi-image text-xl"></i>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Nombre y Código -->
                    <Column field="name" header="Producto" sortable class="min-w-[180px]">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-800 text-base truncate max-w-[200px] md:max-w-xs">{{ slotProps.data.name }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <span v-if="slotProps.data.barcode" class="text-xs bg-surface-100 text-surface-500 px-2 py-0.5 rounded-md border border-surface-200 font-mono">
                                        {{ slotProps.data.barcode }}
                                    </span>
                                    <span v-if="!slotProps.data.is_active" class="text-xs text-red-500 font-medium bg-red-50 px-2 py-0.5 rounded-full">
                                        Inactivo
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Stock -->
                    <Column header="Stock" sortable field="stock" class="w-[120px] hidden md:table-cell">
                        <template #body="slotProps">
                            <Tag 
                                :value="getStockLabel(slotProps.data)" 
                                :severity="getStockSeverity(slotProps.data)" 
                                rounded
                                class="!px-3 !py-1 !font-medium"
                            >
                                <template #icon>
                                    <i v-if="!slotProps.data.track_inventory" class="pi pi-infinity text-xs mr-1"></i>
                                    <i v-else-if="(slotProps.data.stock || 0) < 10" class="pi pi-exclamation-triangle text-xs mr-1"></i>
                                </template>
                            </Tag>
                        </template>
                    </Column>

                    <!-- Precios -->
                    <Column field="price" header="Precio" sortable class="w-[120px]">
                        <template #body="slotProps">
                            <span class="text-surface-900 font-semibold text-base">
                                {{ formatCurrency(slotProps.data.price) }}
                            </span>
                        </template>
                    </Column>

                    <Column field="employee_price" header="P. Emp." sortable class="w-[120px] hidden lg:table-cell">
                        <template #body="slotProps">
                            <span class="text-surface-500 font-medium">
                                {{ formatCurrency(slotProps.data.employee_price) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1 action-btn">
                                <Button 
                                    icon="pi pi-pencil" 
                                    text 
                                    rounded 
                                    severity="secondary" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-orange-600 hover:!bg-orange-50"
                                    v-tooltip.top="'Editar'"
                                    @click="router.get(route('products.edit', slotProps.data.id))"
                                />
                                <Button 
                                    icon="pi pi-trash" 
                                    text 
                                    rounded 
                                    severity="danger" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-red-600 hover:!bg-red-50"
                                    v-tooltip.top="'Eliminar'"
                                    @click="deleteProduct(slotProps.data)"
                                />
                            </div>
                        </template>
                    </Column>

                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>