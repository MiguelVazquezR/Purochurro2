<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Image from 'primevue/image';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';

const props = defineProps({
    product: Object,
    movements: {
        type: Array,
        default: () => []
    }
});

const confirm = useConfirm();
const toast = useToast();
const activeTab = ref('detail'); // 'detail' | 'history'

const formatCurrency = (value) => {
    if (value === undefined || value === null) return 'N/A';
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

// Acción para eliminar
const deleteProduct = () => {
    confirm.require({
        message: `¿Estás seguro de eliminar "${props.product.name}"?`,
        header: 'Confirmar eliminación',
        icon: 'pi pi-info-circle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('products.destroy', props.product.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Producto eliminado correctamente', life: 3000 });
                }
            });
        }
    });
};

const toggleActiveStatus = () => {
    const isCurrentlyActive = props.product.is_active;
    const actionVerb = isCurrentlyActive ? 'desactivar' : 'activar';
    const newStatus = !isCurrentlyActive;

    confirm.require({
        message: `¿Estás seguro de ${actionVerb} este producto? ${isCurrentlyActive ? 'Dejará de ser visible para ventas.' : 'Volverá a estar disponible.'}`,
        header: isCurrentlyActive ? 'Desactivar Producto' : 'Activar Producto',
        icon: isCurrentlyActive ? 'pi pi-eye-slash' : 'pi pi-eye',
        rejectLabel: 'Cancelar',
        acceptLabel: isCurrentlyActive ? 'Desactivar' : 'Activar',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: isCurrentlyActive ? 'p-button-warning' : 'p-button-success',
        accept: () => {
            router.put(route('products.update', props.product.id), {
                ...props.product,
                is_active: newStatus,
                image: null,
                _method: 'PUT'
            }, {
                onSuccess: () => {
                    toast.add({ 
                        severity: newStatus ? 'success' : 'info', 
                        summary: 'Estado Actualizado', 
                        detail: `Producto ${newStatus ? 'activado' : 'desactivado'} correctamente`, 
                        life: 3000 
                    });
                }
            });
        }
    });
};

const getStockSeverity = (stock) => {
    if (!props.product.track_inventory) return 'info';
    if (stock === 0) return 'danger';
    if (stock < 10) return 'warn';
    return 'success';
};

const getMovementSeverity = (type) => {
    switch (type) {
        case 'sale': return 'info'; // Venta
        case 'purchase': return 'success'; // Compra
        case 'adjustment_in': return 'success'; // Ajuste +
        case 'adjustment_out': return 'warning'; // Ajuste -
        case 'waste': return 'danger'; // Merma
        case 'transfer': return 'help'; // Traspaso
        default: return 'secondary';
    }
};
</script>

<template>
    <AppLayout :title="product.name">
        <div class="w-full max-w-6xl mx-auto flex flex-col gap-6 pb-10">

            <!-- Encabezado Sticky -->
            <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                <div class="flex items-center gap-4">
                    <Link :href="route('products.index')">
                        <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900 truncate max-w-[200px] md:max-w-md">
                                {{ product.name }}
                            </h1>
                            <Tag 
                                v-if="product.category" 
                                :style="{ backgroundColor: product.category.color || '#ccc', color: '#fff' }" 
                                class="!font-medium !text-xs !px-2 !py-0.5"
                                rounded
                            >
                                {{ product.category.name }}
                            </Tag>
                            <i v-if="!product.is_active" class="pi pi-eye-slash text-surface-400 text-lg" v-tooltip="'Producto inactivo'"></i>
                        </div>
                        <p class="text-surface-500 text-sm mt-1 flex items-center gap-2">
                            <span v-if="product.barcode" class="font-mono bg-surface-200 px-1.5 rounded text-surface-700 text-xs">
                                {{ product.barcode }}
                            </span>
                            <span v-else>Sin código de barras</span>
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <Button 
                        :icon="product.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" 
                        :label="product.is_active ? 'Desactivar' : 'Activar'"
                        :severity="product.is_active ? 'secondary' : 'success'" 
                        :text="product.is_active" 
                        :outlined="!product.is_active"
                        rounded 
                        class="!font-medium !hidden sm:!flex"
                        @click="toggleActiveStatus"
                    />
                    <div class="w-px h-8 bg-surface-200 mx-1 self-center hidden sm:block"></div>
                    <Button 
                        icon="pi pi-trash" 
                        text 
                        severity="danger" 
                        rounded 
                        v-tooltip.bottom="'Eliminar'"
                        @click="deleteProduct"
                    />
                    <Link :href="route('products.edit', product.id)">
                        <Button 
                            label="Editar" 
                            icon="pi pi-pencil" 
                            class="!bg-surface-900 !border-surface-900 hover:!bg-surface-800 font-bold shadow-lg"
                            rounded 
                        />
                    </Link>
                </div>
            </div>

            <!-- Navegación de Pestañas (Segmented Control style) -->
            <div class="flex justify-center">
                <div class="bg-surface-200/50 p-1 rounded-xl inline-flex shadow-inner">
                    <button 
                        @click="activeTab = 'detail'"
                        class="px-6 py-2 rounded-lg text-sm font-bold transition-all duration-200"
                        :class="activeTab === 'detail' ? 'bg-white text-indigo-600 shadow-sm' : 'text-surface-500 hover:text-surface-700'"
                    >
                        Detalle general
                    </button>
                    <button 
                        @click="activeTab = 'history'"
                        class="px-6 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2"
                        :class="activeTab === 'history' ? 'bg-white text-indigo-600 shadow-sm' : 'text-surface-500 hover:text-surface-700'"
                    >
                        <span>Kardex / Movimientos</span>
                        <Tag v-if="movements.length" severity="secondary" class="!px-1.5 !py-0.5 !text-[10px]">{{ movements.length }}</Tag>
                    </button>
                </div>
            </div>

            <!-- CONTENIDO PESTAÑA DETALLE -->
            <div v-if="activeTab === 'detail'" class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in">
                <!-- Columna Izquierda: Imagen y Estado -->
                <div class="lg:col-span-1 flex flex-col gap-6">
                    
                    <!-- IMAGEN COMPACTA -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-4 relative overflow-hidden group flex flex-col items-center">
                        <div class="w-full max-h-64 rounded-xl overflow-hidden bg-surface-50 relative border border-surface-100 flex items-center justify-center">
                            <Image 
                                v-if="product.image_url" 
                                :src="product.image_url" 
                                :alt="product.name" 
                                preview
                                imageClass="w-full h-full object-contain max-h-64" 
                                class="w-full h-full flex items-center justify-center"
                            />
                            <div v-else class="w-full h-48 flex flex-col items-center justify-center text-surface-300">
                                <i class="pi pi-image !text-4xl mb-2"></i>
                                <span class="text-xs font-medium">Sin imagen</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-4">
                        <h3 class="text-sm font-bold text-surface-400 uppercase tracking-wider">Configuración</h3>
                        <div class="flex items-center justify-between py-2 border-b border-surface-50">
                            <span class="text-surface-600">Vendible en caja</span>
                            <i class="pi" :class="product.is_sellable ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-400'" style="font-size: 1.2rem"></i>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-surface-600">Control de stock</span>
                            <i class="pi" :class="product.track_inventory ? 'pi-check-circle text-green-500' : 'pi-minus-circle text-orange-400'" style="font-size: 1.2rem"></i>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Detalles -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 relative overflow-hidden">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h2 class="text-sm font-bold text-surface-400 uppercase tracking-wider mb-1">Existencias Totales</h2>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-4xl font-extrabold text-surface-900">{{ product.track_inventory ? product.stock : '∞' }}</span>
                                    <span class="text-surface-500 font-medium">unidades</span>
                                </div>
                            </div>
                            <div v-if="product.track_inventory">
                                <Tag :severity="getStockSeverity(product.stock)" :value="product.stock === 0 ? 'Agotado' : (product.stock < 10 ? 'Bajo Stock' : 'Disponible')" class="!text-sm !px-3 !py-1" rounded />
                            </div>
                        </div>

                        <!-- DESGLOSE DE STOCK POR UBICACIÓN -->
                        <div v-if="product.track_inventory && product.inventories?.length" class="bg-surface-50 rounded-xl p-4 border border-surface-100">
                            <h3 class="text-xs font-bold text-surface-500 uppercase mb-3">Desglose por Ubicación</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div v-for="inv in product.inventories" :key="inv.id" class="flex items-center justify-between bg-white p-2 rounded-lg border border-surface-200 shadow-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full" :class="inv.quantity > 0 ? 'bg-green-500' : 'bg-red-400'"></div>
                                        <span class="text-sm font-medium text-surface-700">{{ inv.location?.name }}</span>
                                    </div>
                                    <span class="font-bold text-surface-900">{{ inv.quantity }}</span>
                                </div>
                            </div>
                        </div>
                         <div v-else-if="product.track_inventory" class="text-sm text-surface-400 italic">
                            No hay existencias registradas en ninguna ubicación.
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                        <h2 class="text-lg font-bold text-surface-900 mb-4 flex items-center gap-2">
                            <i class="pi pi-align-left text-orange-500 bg-orange-50 p-2 rounded-lg"></i>
                            Descripción
                        </h2>
                        <p v-if="product.description" class="text-surface-600 leading-relaxed whitespace-pre-line">{{ product.description }}</p>
                        <p v-else class="text-surface-400 italic">No hay descripción disponible para este producto.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-5 flex flex-col gap-1">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Precio público</span>
                            <span class="text-2xl font-bold text-surface-900">{{ formatCurrency(product.price) }}</span>
                        </div>
                        <div class="bg-surface-50 rounded-3xl border border-surface-200 p-5 flex flex-col gap-1">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Precio empleado</span>
                            <span class="text-xl font-bold text-surface-700">{{ formatCurrency(product.employee_price) }}</span>
                        </div>
                        <div class="bg-surface-50 rounded-3xl border border-surface-200 p-5 flex flex-col gap-1 opacity-75 hover:opacity-100 transition-opacity">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Costo</span>
                            <span class="text-xl font-bold text-surface-600">{{ formatCurrency(product.cost) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PESTAÑA HISTORIAL (KARDEX) -->
            <div v-if="activeTab === 'history'" class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-surface-900">Historial de Movimientos</h2>
                    <span class="text-xs text-surface-500">Últimos 50 registros</span>
                </div>

                <DataTable :value="movements" size="small" stripedRows tableStyle="min-width: 50rem" class="text-sm">
                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-history text-4xl mb-2 opacity-50"></i>
                            <p>No hay movimientos registrados.</p>
                        </div>
                    </template>

                    <Column field="date" header="Fecha" sortable class="whitespace-nowrap font-medium text-surface-600"></Column>
                    
                    <Column field="type_label" header="Tipo">
                        <template #body="slotProps">
                            <Tag :severity="getMovementSeverity(slotProps.data.type_value)" :value="slotProps.data.type_label" class="!px-2 !py-0.5 !text-xs !font-bold" />
                        </template>
                    </Column>
                    
                    <Column field="quantity" header="Cant.">
                        <template #body="slotProps">
                            <span :class="['sale', 'waste', 'adjustment_out'].includes(slotProps.data.type_value) ? 'text-red-600' : 'text-green-600'" class="font-bold">
                                {{ ['sale', 'waste', 'adjustment_out'].includes(slotProps.data.type_value) ? '-' : '+' }}{{ slotProps.data.quantity }}
                            </span>
                        </template>
                    </Column>
                    
                    <Column field="from" header="Origen" class="text-surface-500"></Column>
                    <Column field="to" header="Destino" class="text-surface-500"></Column>
                    
                    <Column field="user" header="Usuario" class="text-surface-900 font-medium"></Column>
                    
                    <Column header="Notas">
                        <template #body="slotProps">
                            <span class="text-xs text-surface-400 italic">{{ slotProps.data.notes || '-' }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>