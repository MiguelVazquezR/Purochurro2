<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Image from 'primevue/image';

const props = defineProps({
    product: Object,
});

const confirm = useConfirm();
const toast = useToast();

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

// Acción para alternar estado (Activar/Desactivar)
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
</script>

<template>
    <AppLayout :title="product.name">
        <div class="w-full max-w-5xl mx-auto flex flex-col gap-8 pb-10">

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
                            <!-- Tag de Categoría -->
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
                    <!-- Botón de Activar/Desactivar (Escritorio) - Forzamos flex con !important -->
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
                    <!-- Botón Icono para móvil - Forzamos hidden en sm con !important -->
                    <Button 
                        :icon="product.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'" 
                        :severity="product.is_active ? 'secondary' : 'success'" 
                        text 
                        rounded 
                        class="sm:!hidden"
                        v-tooltip.bottom="product.is_active ? 'Desactivar' : 'Activar'"
                        @click="toggleActiveStatus"
                    />

                    <div class="w-px h-8 bg-surface-200 mx-1 self-center"></div>

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

            <!-- Contenido Principal -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Columna Izquierda: Imagen y Estado -->
                <div class="lg:col-span-1 flex flex-col gap-6">
                    
                    <!-- Tarjeta de Imagen (Con Preview) -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-2 relative overflow-hidden group">
                        <div class="aspect-square w-full rounded-2xl overflow-hidden bg-surface-50 relative border border-surface-100 flex items-center justify-center">
                            
                            <Image 
                                v-if="product.image_url" 
                                :src="product.image_url" 
                                :alt="product.name" 
                                preview
                                imageClass="w-full h-full object-cover"
                                class="w-full h-full flex items-center justify-center"
                            >
                                <template #indicator>
                                    <div class="flex gap-2 items-center text-white font-medium">
                                        <i class="pi pi-search-plus"></i>
                                        <span>Ver</span>
                                    </div>
                                </template>
                            </Image>

                            <div v-else class="w-full h-full flex flex-col items-center justify-center text-surface-300">
                                <i class="pi pi-image text-5xl mb-2"></i>
                                <span class="text-sm font-medium">Sin imagen</span>
                            </div>

                            <!-- Badges Flotantes -->
                            <div class="absolute top-3 left-3 flex flex-col gap-2 pointer-events-none">
                                <Tag :value="product.is_active ? 'Activo' : 'Inactivo'" :severity="product.is_active ? 'success' : 'danger'" class="!shadow-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Estado y Flags -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-4">
                        <h3 class="text-sm font-bold text-surface-400 uppercase tracking-wider">Configuración</h3>
                        
                        <div class="flex items-center justify-between py-2 border-b border-surface-50">
                            <span class="text-surface-600">Vendible en caja</span>
                            <i 
                                class="pi" 
                                :class="product.is_sellable ? 'pi-check-circle text-green-500' : 'pi-times-circle text-red-400'"
                                style="font-size: 1.2rem"
                            ></i>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-surface-600">Control de stock</span>
                            <i 
                                class="pi" 
                                :class="product.track_inventory ? 'pi-check-circle text-green-500' : 'pi-minus-circle text-orange-400'"
                                style="font-size: 1.2rem"
                            ></i>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Detalles -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    
                    <!-- Inventario -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex items-center justify-between relative overflow-hidden">
                        <!-- Fondo decorativo -->
                        <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l from-orange-50 to-transparent opacity-50"></div>

                        <div>
                            <h2 class="text-sm font-bold text-surface-400 uppercase tracking-wider mb-1">Existencias Totales</h2>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold text-surface-900">
                                    {{ product.track_inventory ? product.stock : '∞' }}
                                </span>
                                <span class="text-surface-500 font-medium">unidades</span>
                            </div>
                        </div>

                        <div v-if="product.track_inventory">
                            <Tag 
                                :severity="getStockSeverity(product.stock)" 
                                :value="product.stock === 0 ? 'Agotado' : (product.stock < 10 ? 'Bajo Stock' : 'Disponible')" 
                                class="!text-sm !px-3 !py-1"
                                rounded
                            />
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                        <h2 class="text-lg font-bold text-surface-900 mb-4 flex items-center gap-2">
                            <i class="pi pi-align-left text-orange-500 bg-orange-50 p-2 rounded-lg"></i>
                            Descripción
                        </h2>
                        <p v-if="product.description" class="text-surface-600 leading-relaxed whitespace-pre-line">
                            {{ product.description }}
                        </p>
                        <p v-else class="text-surface-400 italic">
                            No hay descripción disponible para este producto.
                        </p>
                    </div>

                    <!-- Precios -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        
                        <!-- Precio Público -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-5 flex flex-col gap-1">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Precio público</span>
                            <span class="text-2xl font-bold text-surface-900">{{ formatCurrency(product.price) }}</span>
                        </div>

                        <!-- Precio Empleado -->
                        <div class="bg-surface-50 rounded-3xl border border-surface-200 p-5 flex flex-col gap-1">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Precio empleado</span>
                            <span class="text-xl font-bold text-surface-700">{{ formatCurrency(product.employee_price) }}</span>
                        </div>

                        <!-- Costo (Admin) -->
                        <div class="bg-surface-50 rounded-3xl border border-surface-200 p-5 flex flex-col gap-1 opacity-75 hover:opacity-100 transition-opacity">
                            <span class="text-xs font-bold text-surface-400 uppercase tracking-wider">Costo</span>
                            <span class="text-xl font-bold text-surface-600">{{ formatCurrency(product.cost) }}</span>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>