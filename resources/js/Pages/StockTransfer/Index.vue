<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    locations: Array,
    products: Array
});

const toast = useToast();

// Estados para el Tour
// OPTIMIZACIÓN: Iniciamos en false para que el usuario experto pueda interactuar de inmediato.
const isLoadingTour = ref(false); 
const isTourActive = ref(false);

const form = useForm({
    product_id: null,
    from_location_id: null,
    to_location_id: null,
    quantity: null,
    notes: ''
});

// --- Lógica Reactiva ---

// Obtener el objeto producto seleccionado
const selectedProduct = computed(() => {
    return props.products.find(p => p.id === form.product_id);
});

// Calcular stock disponible en el origen seleccionado
const availableStock = computed(() => {
    if (!selectedProduct.value || !form.from_location_id) return 0;
    // Accedemos al mapa de inventarios que enviamos desde el controlador
    return selectedProduct.value.inventories[form.from_location_id] || 0;
});

// Filtrar destinos posibles (que no sean el mismo que el origen)
const destinationOptions = computed(() => {
    if (!form.from_location_id) return [];
    return props.locations.filter(l => l.id !== form.from_location_id);
});

const submit = () => {
    if (form.quantity > availableStock.value) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'La cantidad excede el stock disponible en origen.', life: 3000 });
        return;
    }

    form.post(route('stock-transfers.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Traspaso Exitoso', detail: 'El inventario ha sido actualizado.', life: 3000 });
            form.reset('quantity', 'notes'); // Mantenemos selecciones para agilizar
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Verifica los datos.', life: 3000 });
        }
    });
};

// --- BLOQUEO DE INTERACCIÓN ROBUSTO ---
const blockInteraction = (e) => {
    // Si el tour no está activo, no hacemos nada
    if (!isTourActive.value) return;

    // Permitimos clics DENTRO de la ventana del tutorial (driver-popover)
    // Esto asegura que los botones "Siguiente", "Anterior", "Entendido" sigan funcionando
    if (e.target.closest && e.target.closest('.driver-popover')) {
        return;
    }

    // Bloqueamos cualquier otra interacción
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
};

const enableBlocking = () => {
    isTourActive.value = true;
    // Agregamos listeners en fase de CAPTURA (true) para interceptar antes que nadie
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

// --- CONFIGURACIÓN DEL TOUR (ONBOARDING) ---
const startTour = () => {
    enableBlocking(); // ACTIVAR BLOQUEO

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: [
            { 
                element: '#tour-header-title', 
                popover: { 
                    title: 'Traspasos de Inventario', 
                    description: 'Esta herramienta te permite mover productos entre distintas ubicaciones (ej. De Almacén a Cocina o a Carrito de Venta) para mantener el stock actualizado.',
                    side: "bottom",
                    align: 'start'
                } 
            },
            { 
                element: '#tour-product-selection', 
                popover: { 
                    title: 'Paso 1: Selección', 
                    description: 'Primero, busca y selecciona el producto que deseas mover. Puedes escribir el nombre para encontrarlo rápidamente.' 
                } 
            },
            { 
                element: '#tour-locations-panel', 
                popover: { 
                    title: 'Paso 2: Origen y Destino', 
                    description: 'Define desde DÓNDE sale la mercancía y hacia DÓNDE va. El sistema te mostrará automáticamente cuánto stock hay disponible en el origen seleccionado.' 
                } 
            },
            { 
                element: '#tour-quantity-notes', 
                popover: { 
                    title: 'Paso 3: Cantidad', 
                    description: 'Ingresa cuántas unidades vas a mover. El sistema no te permitirá mover más de lo que hay disponible. También puedes agregar una nota de referencia.' 
                } 
            },
            { 
                element: '#tour-confirm-btn', 
                popover: { 
                    title: 'Confirmación', 
                    description: 'Finalmente, haz clic aquí para procesar el movimiento. El inventario se actualizará al instante en ambas ubicaciones.' 
                } 
            }
        ],
        onDestroyStarted: () => {
            markTourAsCompleted();
            tourDriver.destroy();
            disableBlocking(); // DESACTIVAR BLOQUEO
        }
    });

    tourDriver.drive();
};

const markTourAsCompleted = async () => {
    try {
        await axios.post(route('tutorials.complete'), { module_name: 'stock_transfers' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        // OPTIMIZACIÓN: Verificamos silenciosamente.
        const response = await axios.get(route('tutorials.check', 'stock_transfers'));
        
        if (!response.data.completed) {
            // Solo si NO está completado, activamos el loading y preparamos el tour
            isLoadingTour.value = true;
            setTimeout(() => {
                isLoadingTour.value = false;
                startTour();
            }, 800);
        }
        // Si ya está completado, no hacemos NADA (isLoadingTour sigue en false)
    } catch (error) {
        console.error('Error verificando tutorial', error);
        isLoadingTour.value = false;
    }
});

// Limpieza de eventos por si el componente se desmonta abruptamente
onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Traspasos de Inventario">
        
        <!-- Overlay de Carga (Spinner) - Solo se muestra si se va a lanzar el tour -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- CAPA TRANSPARENTE DE BLOQUEO DE INTERACCIÓN -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="max-w-4xl mx-auto py-8 px-4 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">

            <!-- ID AGREGADO PARA EL TOUR -->
            <div id="tour-header-title" class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-indigo-100 p-3 rounded-2xl text-indigo-600">
                        <i class="pi pi-arrows-h text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900">Traspasos de Mercancía</h1>
                        <p class="text-gray-500 text-sm">Mueve productos entre almacén, cocina y carrito.</p>
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

            <div class="bg-white rounded-3xl shadow-xl border border-surface-200 overflow-hidden">
                <div class="p-8 grid gap-8">

                    <!-- 1. Selección de Producto -->
                    <!-- ID AGREGADO PARA EL TOUR -->
                    <div id="tour-product-selection" class="flex flex-col gap-2">
                        <label class="font-bold text-gray-700">1. ¿Qué producto vas a mover?</label>
                        <Select v-model="form.product_id" :options="products" optionLabel="name" optionValue="id" filter
                            placeholder="Buscar producto..." class="w-full">
                            <template #option="slotProps">
                                <div class="flex items-center gap-2">
                                    <img v-if="slotProps.option.image_url" :src="slotProps.option.image_url"
                                        class="w-8 h-8 rounded-lg object-cover" />
                                    <i v-else class="pi pi-box text-gray-400 text-xl"></i>
                                    <span>{{ slotProps.option.name }}</span>
                                </div>
                            </template>
                        </Select>
                        <Message v-if="form.errors.product_id" severity="error" size="small" variant="simple">{{
                            form.errors.product_id }}
                        </Message>
                    </div>

                    <!-- 2. Origen y Destino (Visual) -->
                    <!-- ID AGREGADO PARA EL TOUR -->
                    <div id="tour-locations-panel" class="bg-gray-50 rounded-2xl p-6 border border-gray-100 relative">
                        <!-- Línea conectora visual en Desktop -->
                        <div class="hidden md:block absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-0">
                            <i class="pi pi-arrow-right text-4xl text-gray-300"></i>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 relative z-10">

                            <!-- ORIGEN -->
                            <div class="flex flex-col gap-2">
                                <label class="font-bold text-gray-600 text-sm uppercase tracking-wide">De
                                    (Origen)</label>
                                <Select v-model="form.from_location_id" :options="locations" optionLabel="name"
                                    optionValue="id" placeholder="Seleccionar ubicación" class="w-full"
                                    @change="form.to_location_id = null" />

                                <!-- Info Stock Origen -->
                                <div v-if="selectedProduct && form.from_location_id"
                                    class="mt-2 bg-white p-3 rounded-xl border border-indigo-100 flex justify-between items-center shadow-sm">
                                    <span class="text-sm text-gray-500">Disponible:</span>
                                    <span class="font-bold text-indigo-600 text-lg">{{ availableStock }} u.</span>
                                </div>
                                <Message v-if="form.errors.from_location_id" severity="error" size="small"
                                    variant="simple">{{
                                    form.errors.from_location_id }}</Message>
                            </div>

                            <!-- DESTINO -->
                            <div class="flex flex-col gap-2">
                                <label class="font-bold text-gray-600 text-sm uppercase tracking-wide">Para
                                    (Destino)</label>
                                <Select v-model="form.to_location_id" :options="destinationOptions" optionLabel="name"
                                    optionValue="id" placeholder="Seleccionar destino" class="w-full"
                                    :disabled="!form.from_location_id" />
                                <Message v-if="form.errors.to_location_id" severity="error" size="small"
                                    variant="simple">{{
                                    form.errors.to_location_id }}</Message>
                            </div>

                        </div>
                    </div>

                    <!-- 3. Cantidad y Notas -->
                    <!-- ID AGREGADO PARA EL TOUR -->
                    <div id="tour-quantity-notes" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1 flex flex-col gap-2">
                            <label class="font-bold text-gray-700">Cantidad</label>
                            <InputNumber v-model="form.quantity" showButtons buttonLayout="horizontal" :min="1"
                                :max="availableStock" placeholder="0" inputClass="w-full text-center font-bold"
                                class="w-full">
                                <template #incrementbuttonicon>
                                    <span class="pi pi-plus" />
                                </template>
                                <template #decrementbuttonicon>
                                    <span class="pi pi-minus" />
                                </template>
                            </InputNumber>
                            <Message v-if="form.errors.quantity" severity="error" size="small" variant="simple">{{
                                form.errors.quantity }}
                            </Message>
                        </div>

                        <div class="md:col-span-2 flex flex-col gap-2">
                            <label class="font-bold text-gray-700">Notas (Opcional)</label>
                            <Textarea v-model="form.notes" rows="1" placeholder="Ej. Reposición turno tarde"
                                class="w-full" autoResize />
                        </div>
                    </div>

                </div>

                <div class="bg-gray-50 p-6 flex justify-end">
                    <!-- ID AGREGADO PARA EL TOUR -->
                    <div id="tour-confirm-btn">
                        <Button label="Confirmar Traspaso" icon="pi pi-check" @click="submit" :loading="form.processing"
                            :disabled="!form.product_id || !form.from_location_id || !form.to_location_id || !form.quantity"
                            class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700 !font-bold !rounded-xl" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>