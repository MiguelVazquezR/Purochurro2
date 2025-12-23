<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';
import InputText from 'primevue/inputtext';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    locations: Array,
    products: Array
});

const toast = useToast();

// Estados para el Tour
const isLoadingTour = ref(false); 
const isTourActive = ref(false);

// Formulario Principal (Para el envío al servidor)
const form = useForm({
    from_location_id: null,
    to_location_id: null,
    notes: '',
    items: [] // Array de objetos { product_id, quantity, product_obj }
});

// Estados Locales para el "Carrito" de agregado
const currentProductId = ref(null);
const currentQuantity = ref(null);

// --- Lógica Reactiva ---

// Producto seleccionado actualmente en el dropdown
const currentProductObj = computed(() => {
    return props.products.find(p => p.id === currentProductId.value);
});

// Filtrar destinos posibles
const destinationOptions = computed(() => {
    if (!form.from_location_id) return [];
    return props.locations.filter(l => l.id !== form.from_location_id);
});

// Stock disponible del producto seleccionado en el origen
const availableStock = computed(() => {
    if (!currentProductObj.value || !form.from_location_id) return 0;
    // Stock real en base de datos
    const realStock = currentProductObj.value.inventories[form.from_location_id] || 0;
    
    // Restamos lo que YA está en la lista de transferencia para no dejar agregar de más
    const inCartItem = form.items.find(item => item.product_id === currentProductId.value);
    const inCartQty = inCartItem ? inCartItem.quantity : 0;

    return Math.max(0, realStock - inCartQty);
});

// Agregar item a la lista
const addItem = () => {
    if (!currentProductId.value || !currentQuantity.value || currentQuantity.value <= 0) return;

    if (currentQuantity.value > availableStock.value) {
        toast.add({ severity: 'error', summary: 'Stock Insuficiente', detail: 'No puedes agregar más de lo disponible.', life: 3000 });
        return;
    }

    // Verificar si ya existe en la lista para sumar cantidad
    const existingIndex = form.items.findIndex(i => i.product_id === currentProductId.value);

    if (existingIndex !== -1) {
        form.items[existingIndex].quantity += currentQuantity.value;
    } else {
        form.items.push({
            product_id: currentProductId.value,
            quantity: currentQuantity.value,
            product: currentProductObj.value // Guardamos referencia completa para mostrar nombre/img
        });
    }

    // Reset inputs temporales
    currentProductId.value = null;
    currentQuantity.value = null;
};

// Eliminar item de la lista
const removeItem = (index) => {
    form.items.splice(index, 1);
};

const submit = () => {
    if (form.items.length === 0) {
        toast.add({ severity: 'warn', summary: 'Lista Vacía', detail: 'Agrega al menos un producto.', life: 3000 });
        return;
    }

    form.post(route('stock-transfers.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Traspaso Exitoso', detail: 'Inventario actualizado.', life: 3000 });
            form.reset('items', 'notes'); 
            // Mantenemos origen/destino por si quiere seguir trabajando ahí
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Verifica los datos.', life: 3000 });
        }
    });
};

// --- BLOQUEO DE INTERACCIÓN ROBUSTO (TOUR) ---
const blockInteraction = (e) => {
    if (!isTourActive.value) return;
    if (e.target.closest && e.target.closest('.driver-popover')) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
};

const enableBlocking = () => {
    isTourActive.value = true;
    window.addEventListener('click', blockInteraction, true);
    window.addEventListener('mousedown', blockInteraction, true);
    window.addEventListener('touchstart', blockInteraction, true);
    window.addEventListener('keydown', blockInteraction, true);
};

const disableBlocking = () => {
    isTourActive.value = false;
    window.removeEventListener('click', blockInteraction, true);
    window.removeEventListener('mousedown', blockInteraction, true);
    window.removeEventListener('touchstart', blockInteraction, true);
    window.removeEventListener('keydown', blockInteraction, true);
};

// --- CONFIGURACIÓN DEL TOUR ---
const startTour = () => {
    enableBlocking();

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        steps: [
            { 
                element: '#tour-config-panel', 
                popover: { 
                    title: '1. Configuración de Ruta', 
                    description: 'Selecciona desde dónde salen los productos y hacia dónde van. Esta ruta aplicará para todos los items que agregues.',
                    side: "bottom"
                } 
            },
            { 
                element: '#tour-add-panel', 
                popover: { 
                    title: '2. Agregar Productos', 
                    description: 'Busca un producto, define la cantidad y presiona "Agregar a la Lista". Puedes repetir esto para varios productos.' 
                } 
            },
            { 
                element: '#tour-list-panel', 
                popover: { 
                    title: '3. Lista de Traspaso', 
                    description: 'Aquí verás los productos pendientes de envío. Puedes eliminar items si te equivocaste.' 
                } 
            },
            { 
                element: '#tour-confirm-btn', 
                popover: { 
                    title: '4. Confirmar', 
                    description: 'Cuando termines, guarda el traspaso. Todos los movimientos se procesarán juntos.' 
                } 
            }
        ],
        onDestroyStarted: () => {
            markTourAsCompleted();
            tourDriver.destroy();
            disableBlocking();
        }
    });

    tourDriver.drive();
};

const markTourAsCompleted = async () => {
    try {
        await axios.post(route('tutorials.complete'), { module_name: 'stock_transfers' });
    } catch (error) {
        console.error('Error guardando tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'stock_transfers'));
        if (!response.data.completed) {
            isLoadingTour.value = true;
            setTimeout(() => {
                isLoadingTour.value = false;
                startTour();
            }, 800);
        }
    } catch (error) {
        isLoadingTour.value = false;
    }
});

onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Traspasos Múltiples">
        
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="max-w-5xl mx-auto py-8 px-4 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-100 p-3 rounded-2xl text-indigo-600">
                        <i class="pi pi-arrows-h text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900">Traspasos Múltiples</h1>
                        <p class="text-gray-500 text-sm">Mueve varios productos entre ubicaciones en una sola operación.</p>
                    </div>
                </div>
                 <!-- Botones de Acción -->
                <div class="flex gap-2">
                    <Link :href="route('pos.index')">
                    <Button label="Ir a PV" icon="pi pi-shopping-bag" severity="secondary" outlined rounded
                        class="!font-bold bg-white shadow-sm" />
                    </Link>
                    <Link :href="route('products.index')">
                    <Button label="Productos" icon="pi pi-box" severity="secondary" outlined rounded
                        class="!font-bold bg-white shadow-sm" />
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- COLUMNA IZQUIERDA: Configuración y Agregado -->
                <div class="lg:col-span-1 flex flex-col gap-6">
                    
                    <!-- 1. ORIGEN Y DESTINO -->
                    <div id="tour-config-panel" class="bg-white p-6 rounded-3xl shadow-sm border border-surface-200">
                        <h2 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-map-marker text-indigo-500"></i> Ruta
                        </h2>
                        
                        <div class="flex flex-col gap-4">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Origen</label>
                                <Select v-model="form.from_location_id" :options="locations" optionLabel="name"
                                    optionValue="id" placeholder="Seleccionar origen..." class="w-full mt-1"
                                    @change="{ form.to_location_id = null; form.items = []; }" />
                            </div>
                            
                            <div class="flex justify-center -my-2 z-10">
                                <div class="bg-surface-100 p-1.5 rounded-full text-surface-400">
                                    <i class="pi pi-arrow-down"></i>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Destino</label>
                                <Select v-model="form.to_location_id" :options="destinationOptions" optionLabel="name"
                                    optionValue="id" placeholder="Seleccionar destino..." class="w-full mt-1"
                                    :disabled="!form.from_location_id" />
                            </div>
                        </div>
                    </div>

                    <!-- 2. AGREGAR PRODUCTOS -->
                    <div id="tour-add-panel" class="bg-indigo-50/50 p-6 rounded-3xl border border-indigo-100 relative overflow-hidden">
                        <!-- Overlay si no hay ubicaciones -->
                        <div v-if="!form.from_location_id || !form.to_location_id" class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center text-center p-4">
                            <span class="text-sm font-semibold text-gray-500">Selecciona origen y destino para comenzar</span>
                        </div>

                        <h2 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-plus-circle text-indigo-600"></i> Agregar Item
                        </h2>

                        <div class="flex flex-col gap-4">
                            <!-- Selector de Producto -->
                            <div>
                                <Select v-model="currentProductId" :options="products" optionLabel="name" optionValue="id" filter
                                    placeholder="Buscar producto..." class="w-full" resetFilterOnHide>
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <img v-if="slotProps.option.image_url" :src="slotProps.option.image_url"
                                                class="w-6 h-6 rounded object-cover" />
                                            <span class="text-sm">{{ slotProps.option.name }}</span>
                                        </div>
                                    </template>
                                </Select>
                            </div>

                            <!-- Info Stock -->
                            <div v-if="currentProductId" class="flex justify-between items-center text-sm px-1">
                                <span class="text-gray-500">Stock actual:</span>
                                <span class="font-bold" :class="availableStock > 0 ? 'text-green-600' : 'text-red-500'">
                                    {{ availableStock }} u.
                                </span>
                            </div>

                            <!-- Cantidad y Botón -->
                            <div class="flex gap-2">
                                <InputNumber v-model="currentQuantity" placeholder="Cant." :min="1" :max="availableStock" 
                                    showButtons buttonLayout="horizontal" inputClass="text-center font-bold !w-16" class="w-1/2"
                                    :disabled="!currentProductId || availableStock === 0">
                                    <template #incrementbuttonicon><span class="pi pi-plus" /></template>
                                    <template #decrementbuttonicon><span class="pi pi-minus" /></template>
                                </InputNumber>
                                
                                <Button label="Agregar" icon="pi pi-level-down" class="w-1/2 !bg-indigo-600 !border-indigo-600" 
                                    @click="addItem" :disabled="!currentProductId || !currentQuantity || currentQuantity > availableStock" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: Lista y Confirmación -->
                <div class="lg:col-span-2 flex flex-col h-full">
                    <div id="tour-list-panel" class="bg-white rounded-3xl shadow-xl border border-surface-200 flex-1 flex flex-col overflow-hidden">
                        
                        <div class="p-4 border-b border-surface-100 flex justify-between items-center bg-gray-50">
                            <h3 class="font-bold text-gray-700">Lista de envío</h3>
                            <span class="text-xs font-medium px-2 py-1 bg-white rounded border border-gray-200 text-gray-500">
                                {{ form.items.length }} items
                            </span>
                        </div>

                        <!-- LISTA DE ITEMS (Responsive) -->
                        <div class="flex-1 overflow-y-auto min-h-[300px] bg-white p-0">
                            
                            <div v-if="form.items.length === 0" class="h-full flex flex-col items-center justify-center text-gray-300 p-8">
                                <i class="pi pi-shopping-cart text-5xl mb-2 opacity-50"></i>
                                <p class="text-sm">No hay productos agregados</p>
                            </div>

                            <div v-else>
                                <!-- Tabla Desktop -->
                                <DataTable :value="form.items" class="!hidden sm:!block p-datatable-sm" stripedRows>
                                    <Column header="Producto">
                                        <template #body="slotProps">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                                    <img v-if="slotProps.data.product.image_url" :src="slotProps.data.product.image_url" class="w-full h-full object-cover">
                                                    <i v-else class="pi pi-box text-gray-400"></i>
                                                </div>
                                                <span class="font-medium text-gray-700">{{ slotProps.data.product.name }}</span>
                                            </div>
                                        </template>
                                    </Column>
                                    <Column header="Cant." field="quantity" class="w-[100px] text-center font-bold text-indigo-600"></Column>
                                    <Column class="w-[60px]">
                                        <template #body="slotProps">
                                            <Button icon="pi pi-trash" text rounded severity="danger" @click="removeItem(slotProps.index)" />
                                        </template>
                                    </Column>
                                </DataTable>

                                <!-- Lista Móvil -->
                                <div class="sm:!hidden !flex flex-col divide-y divide-gray-100">
                                    <div v-for="(item, index) in form.items" :key="index" class="p-4 flex justify-between items-center gap-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                                <img v-if="item.product.image_url" :src="item.product.image_url" class="w-full h-full object-cover">
                                                <i v-else class="pi pi-box text-gray-400"></i>
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span class="font-medium text-gray-800 truncate">{{ item.product.name }}</span>
                                                <span class="text-xs text-indigo-600 font-bold">Cant: {{ item.quantity }}</span>
                                            </div>
                                        </div>
                                        <Button icon="pi pi-trash" text rounded severity="danger" class="!w-8 !h-8 shrink-0" @click="removeItem(index)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer acciones -->
                        <div class="p-4 bg-gray-50 border-t border-surface-200">
                            <div class="mb-4">
                                <InputText v-model="form.notes" placeholder="Nota general (ej. Reposición semanal)" class="w-full text-sm" />
                            </div>
                            <div id="tour-confirm-btn" class="flex justify-end">
                                <Button label="Confirmar Traspaso" icon="pi pi-check-circle" 
                                    @click="submit" 
                                    :loading="form.processing"
                                    :disabled="form.items.length === 0"
                                    class="w-full sm:w-auto !bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700 !font-bold shadow-lg shadow-indigo-200" />
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>