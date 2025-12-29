<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useForm, Link } from '@inertiajs/vue3'; 
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';
import Dialog from 'primevue/dialog';
import Timeline from 'primevue/timeline';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    operation: Object,
    products: Array,
    locations: Array,
    kitchenTransfers: { // <-- NUEVA PROP
        type: Array,
        default: () => []
    }
});

const toast = useToast();

// Estados
const cart = ref([]);
const selectedLocation = ref(props.locations[0]?.id);
const showPaymentModal = ref(false);
const processingPayment = ref(false);
const keypadInput = ref('0');
const isEmployeeSale = ref(false);

// --- ESTADO PARA HISTORIAL COCINA ---
const showHistoryModal = ref(false);

// --- ESTADO PARA CARRITO MÓVIL ---
const showMobileCart = ref(false); // Controla la visibilidad del Drawer/Sidebar

// Estado para controlar la carga inicial y la ejecución del tour
const isLoadingTour = ref(false); 
const isTourActive = ref(false); 

const paymentForm = useForm({
    location_id: null,
    payment_method: 'cash',
    items: [],
    cash_received: 0,
    is_employee_sale: false, 
});

const filteredProducts = computed(() => {
    return props.products;
});

const getPrice = (product) => {
    return isEmployeeSale.value ? parseFloat(product.employee_price) : parseFloat(product.price);
};

const getProductStock = (product) => {
    if (!product.track_inventory) return Infinity;
    return product.stocks[selectedLocation.value] || 0;
};

watch(isEmployeeSale, (newValue) => {
    cart.value.forEach(item => {
        const originalProduct = props.products.find(p => p.id === item.product_id);
        if (originalProduct) {
            item.price = getPrice(originalProduct);
        }
    });
    toast.add({ 
        severity: 'info', 
        summary: newValue ? 'Modo Empleado' : 'Modo Público', 
        detail: 'Precios actualizados', 
        life: 1000 
    });
});

const addToCart = (product) => {
    const stock = getProductStock(product);
    const item = cart.value.find(i => i.product_id === product.id);
    
    const currentQty = item ? item.quantity : 0;
    if (product.track_inventory && currentQty + 1 > stock) {
        toast.add({ severity: 'warn', summary: 'Sin stock', detail: 'No hay más unidades en esta ubicación.', life: 3000 });
        return;
    }

    if (item) {
        item.quantity++;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: getPrice(product), 
            image: product.image_url,
            quantity: 1
        });
    }
};

const updateQty = (index, delta) => {
    const item = cart.value[index];
    const product = props.products.find(p => p.id === item.product_id);
    const stock = getProductStock(product);
    
    const newVal = item.quantity + delta;
    
    if (delta > 0 && product.track_inventory && newVal > stock) {
        toast.add({ severity: 'warn', summary: 'Sin stock', detail: 'Límite de existencias alcanzado.', life: 3000 });
        return;
    }

    if (newVal > 0) item.quantity = newVal;
    else cart.value.splice(index, 1);
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const clearCart = () => cart.value = [];

const cartTotal = computed(() => cart.value.reduce((sum, i) => sum + (i.price * i.quantity), 0));
const cartCount = computed(() => cart.value.reduce((sum, i) => sum + i.quantity, 0));

// --- Lógica Pago ---
const appendNumber = (n) => {
    if (keypadInput.value.includes('.') && n === '.') return;
    keypadInput.value = (keypadInput.value === '0' && n !== '.') ? n.toString() : keypadInput.value + n;
};
const backspace = () => keypadInput.value = keypadInput.value.length > 1 ? keypadInput.value.slice(0, -1) : '0';

const changeAmount = computed(() => (parseFloat(keypadInput.value) || 0) - cartTotal.value);

const canPay = computed(() => cart.value.length > 0);

const openPayment = () => {
    if (!cart.value.length) return toast.add({ severity: 'warn', summary: 'Carrito vacío', life: 2000 });
    showMobileCart.value = false;
    keypadInput.value = '0';
    showPaymentModal.value = true;
};

const processSale = () => {
    if (!canPay.value) return;
    processingPayment.value = true;
    paymentForm.location_id = selectedLocation.value;
    
    paymentForm.is_employee_sale = isEmployeeSale.value;
    
    paymentForm.items = cart.value.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price }));
    
    const inputVal = parseFloat(keypadInput.value) || 0;
    paymentForm.cash_received = inputVal === 0 ? cartTotal.value : inputVal;
    
    paymentForm.post(route('pos.store-sale'), {
        preserveScroll: true,
        onSuccess: () => {
            const finalChange = inputVal === 0 ? 0 : changeAmount.value;
            toast.add({ severity: 'success', summary: 'Venta exitosa', detail: `Cambio: ${formatCurrency(finalChange)}`, life: 5000 });
            showPaymentModal.value = false;
            clearCart();
            processingPayment.value = false;
        },
        onError: (errors) => {
            const errorMsg = errors.items || errors[0] || 'No se procesó la venta.';
            toast.add({ severity: 'error', summary: 'Error en venta', detail: errorMsg, life: 5000 });
            processingPayment.value = false;
        }
    });
};

const formatCurrency = (val) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);

// --- BLOQUEO DE INTERACCIÓN ROBUSTO ---
const blockInteraction = (e) => {
    if (!isTourActive.value) return;
    if (e.target.closest && e.target.closest('.driver-popover')) {
        return;
    }
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

// --- CONFIGURACIÓN DEL TOUR (ONBOARDING) ---
const startTour = () => {
    enableBlocking(); 

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!', 
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: [
            { 
                element: '#tour-full-layout', 
                popover: { 
                    title: 'Terminal de Venta', 
                    description: 'Bienvenido al punto de venta. Aquí podrás gestionar el catálogo de productos y procesar las órdenes de compra.',
                    side: "center",
                    align: 'center'
                } 
            },
            { 
                element: '#tour-transfers-btn', 
                popover: { 
                    title: '1. Solicitar Traspasos', 
                    description: 'Si te quedas sin stock, usa este botón para ir al módulo de traspasos y solicitar material.' 
                } 
            },
            { 
                // --- NUEVO PASO DEL TOUR ---
                element: '#tour-kitchen-history', 
                popover: { 
                    title: '2. Historial de Cocina', 
                    description: 'Aquí puedes verificar rápidamente si Cocina ya envió el material que solicitaste al Carrito 1. ¡Muy útil para no dar vueltas!' 
                } 
            },
            { 
                element: '#tour-employee-toggle', 
                popover: { 
                    title: '3. Precios de Empleado', 
                    description: 'Cuando un colaborador quiera consumir, activa este interruptor. Los precios se ajustarán automáticamente.' 
                } 
            },
            { 
                element: '#tour-products-grid', 
                popover: { 
                    title: 'Selección de Productos', 
                    description: 'Toca los productos para agregarlos a la orden actual. Puedes ver las existencias disponibles en la esquina de cada tarjeta.' 
                } 
            },
            { 
                element: '#tour-checkout-section', 
                popover: { 
                    title: '4. Cobrar y Finalizar', 
                    description: 'Aquí verás el resumen de la orden. Al dar clic en "Cobrar", se abrirá la ventana para ingresar el efectivo.' 
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
        await axios.post(route('tutorials.complete'), { module_name: 'pos_terminal' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'pos_terminal'));
        
        if (!response.data.completed) {
            isLoadingTour.value = true;
            setTimeout(() => {
                isLoadingTour.value = false;
                startTour();
            }, 800);
        } else {
            isLoadingTour.value = false;
        }
    } catch (error) {
        console.error('Error verificando tutorial', error);
        isLoadingTour.value = false;
    }
});

onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Terminal PV">
        
        <!-- Overlay de Carga (Spinner) -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa visual tour -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <!-- Contenedor Principal -->
        <div 
            id="tour-full-layout" 
            class="h-[calc(100vh-6.9rem)] p-3 gap-x-3 grid grid-cols-1 md:grid-cols-12 overflow-hidden font-sans transition-opacity duration-300"
            :class="{ '!pointer-events-none select-none': isTourActive }"
        >
            
            <!-- IZQUIERDA: Catálogo (Ocupa 12 en móvil, 7 en desktop) -->
            <div id="tour-products-section" class="col-span-1 md:col-span-7 flex flex-col bg-white border border-surface-200 rounded-[2rem] shadow-md overflow-hidden h-full relative">
                <div class="px-6 py-5 border-b border-surface-100 flex justify-between items-center bg-white/80 backdrop-blur-md sticky top-0 z-10">
                    
                    <div class="flex items-center gap-4">
                        <div>
                            <h2 class="text-xl font-black text-gray-800 tracking-tight">Productos</h2>
                            <p class="text-sm text-gray-400 font-medium">{{ products.length }} items disponibles</p>
                        </div>

                        <!-- Accesos Rápidos Inventario -->
                        <div class="flex gap-1 ml-2 border-l border-gray-200 pl-3">
                            <Link id="tour-transfers-btn" :href="route('stock-transfers.index')" v-tooltip.bottom="'Solicitar Traspasos'">
                                <Button icon="pi pi-arrows-h" text rounded severity="help" class="!w-10 !h-10 hover:bg-indigo-50" />
                            </Link>
                            
                            <!-- NUEVO BOTÓN: Historial Cocina -->
                            <Button 
                                id="tour-kitchen-history"
                                icon="pi pi-history" 
                                text 
                                rounded 
                                severity="warn" 
                                class="!w-10 !h-10 hover:bg-orange-50"
                                v-tooltip.bottom="'Entradas desde Cocina'"
                                @click="showHistoryModal = true"
                            />
                            <div v-if="kitchenTransfers.length > 0" class="absolute top-7 left-[14.8rem] flex size-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full size-2 bg-orange-500"></span>
                            </div>
                        </div>
                    </div>

                    <div v-if="locations.length > 1" class="w-48">
                        <Select v-model="selectedLocation" :options="locations" optionLabel="name" optionValue="id" class="w-full !rounded-xl" placeholder="Seleccionar Caja" />
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 scroll-smooth">
                    <div id="tour-products-grid" class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 gap-4 pb-24">
                        <button 
                            v-for="product in filteredProducts" 
                            :key="product.id"
                            @click="addToCart(product)"
                            :disabled="product.track_inventory && getProductStock(product) <= 0"
                            class="group bg-white border border-surface-100 rounded-2xl p-3 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] hover:shadow-[0_8px_16px_-4px_rgba(99,102,241,0.15)] hover:border-indigo-200 transition-all duration-300 text-left flex flex-col gap-3 h-full relative overflow-hidden disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <div class="aspect-square rounded-xl bg-gray-50 overflow-hidden relative w-full shadow-inner">
                                <img v-if="product.image_url" :src="product.image_url" class="h-full mx-auto object-contain group-hover:scale-110 transition-transform duration-500" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-300"><i class="pi pi-image !text-3xl opacity-50"></i></div>
                                
                                <span 
                                    class="absolute bottom-2 right-2 backdrop-blur px-2.5 py-1 rounded-lg text-xs font-black shadow-sm border"
                                    :class="isEmployeeSale ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-white/95 text-gray-800 border-gray-100'"
                                >
                                    {{ formatCurrency(getPrice(product)) }}
                                </span>
                            </div>

                            <div class="flex flex-col flex-1 justify-between gap-1">
                                <p class="font-bold text-gray-700 text-sm leading-snug group-hover:text-indigo-600 transition-colors line-clamp-2">
                                    {{ product.name }}
                                </p>
                                <div class="flex items-center justify-between mt-1">
                                    <Tag 
                                        v-if="product.track_inventory" 
                                        :severity="getProductStock(product) > 0 ? 'success' : 'danger'" 
                                        class="!text-[10px] !px-2 !py-0.5 !rounded-md font-bold"
                                    >
                                        {{ getProductStock(product) > 0 ? getProductStock(product) + ' u.' : 'Agotado' }}
                                    </Tag>
                                    <span v-else class="text-[10px] text-gray-400 font-medium">Servicio</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- DERECHA (Escritorio): Carrito (Oculto en Móvil) -->
            <div class="hidden md:flex md:mt-0 md:col-span-5 flex-col bg-white border border-surface-200 rounded-[2rem] shadow-md overflow-hidden h-full relative z-20">
                <!-- CABECERA CARRITO -->
                <div class="px-4 py-5 border-b border-surface-100 flex justify-between items-center bg-gray-50/80 backdrop-blur-sm">
                    <h3 class="font-black text-base text-gray-800 flex items-center gap-2">
                        <span class="size-7 flex items-center justify-center bg-indigo-100 text-indigo-600 rounded-full">
                            <i class="pi pi-shopping-bag !text-sm"></i>
                        </span>
                        Orden actual
                    </h3>

                    <div class="flex items-center gap-2">
                        <div id="tour-employee-toggle" class="flex items-center gap-2 mr-2" v-tooltip.bottom="'Activar precio empleado'">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider hidden xl:inline-block">Emp.</span>
                            <ToggleButton 
                                v-model="isEmployeeSale" 
                                onLabel="ON" 
                                offLabel="OFF" 
                                onIcon="pi pi-id-card" 
                                offIcon="pi pi-id-card"
                                class="w-20 h-8 !text-xs"
                            />
                        </div>

                        <Button 
                            v-if="cart.length"
                            icon="pi pi-trash" 
                            text 
                            rounded 
                            severity="danger" 
                            class="!w-8 !h-8"
                            @click="clearCart" 
                            v-tooltip.left="'Limpiar todo'" 
                        />
                    </div>
                </div>

                <!-- LISTA ITEMS -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white">
                    <div v-if="!cart.length" class="h-full flex flex-col items-center justify-center text-gray-300 gap-4">
                        <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-2">
                            <i class="pi pi-receipt !text-4xl text-gray-200"></i>
                        </div>
                        <div class="text-center">
                            <p class="font-bold text-gray-400 text-lg">Ticket vacío</p>
                            <p class="text-sm text-gray-300">Selecciona productos para comenzar</p>
                            <p v-if="isEmployeeSale" class="text-xs text-indigo-500 font-bold mt-2 bg-indigo-50 px-2 py-1 rounded inline-block">Modo Venta Empleado Activo</p>
                        </div>
                    </div>

                    <div 
                        v-for="(item, idx) in cart" 
                        :key="idx" 
                        class="flex gap-3 bg-white p-2.5 rounded-2xl border border-gray-100 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.03)] items-center"
                    >
                        <div class="size-12 rounded-xl bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-100 self-center">
                            <img v-if="item.image" :src="item.image" class="w-full h-full object-contain" />
                            <div v-else class="w-full h-full flex items-center justify-center"><i class="pi pi-image text-gray-300 text-lg"></i></div>
                        </div>

                        <div class="flex-1 flex flex-col min-w-0 self-stretch justify-between py-0.5">
                            <div class="flex justify-between items-start gap-2">
                                <p class="font-bold text-gray-800 text-sm leading-tight break-words">{{ item.name }}</p>
                                <button @click="removeFromCart(idx)" class="text-gray-300 hover:text-red-500 transition-colors p-1 -mt-1 -mr-1">
                                    <i class="pi pi-times text-xs"></i>
                                </button>
                            </div>

                            <div class="flex justify-between items-end mt-1">
                                <div class="flex items-center bg-gray-100 rounded-lg h-8">
                                    <button @click="updateQty(idx, -1)" class="w-7 h-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-white rounded-l-lg transition-all active:bg-gray-200"><i class="pi pi-minus !text-[11px] font-bold"></i></button>
                                    <span class="w-5 text-center text-xs font-bold text-gray-800">{{ item.quantity }}</span>
                                    <button @click="updateQty(idx, 1)" class="w-7 h-full flex items-center justify-center text-gray-500 hover:text-green-600 hover:bg-white rounded-r-lg transition-all active:bg-gray-200"><i class="pi pi-plus !text-[11px] font-bold"></i></button>
                                </div>

                                <div class="text-right flex-shrink-0 ml-2">
                                    <p class="text-[10px] text-gray-400 font-medium">
                                        <span v-if="isEmployeeSale" class="text-indigo-500 font-bold mr-1">Emp.</span>
                                        x {{ formatCurrency(item.price) }}
                                    </p>
                                    <p class="font-bold text-indigo-600 text-sm leading-none">{{ formatCurrency(item.price * item.quantity) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER TOTAL -->
                <div id="tour-checkout-section" class="p-5 bg-gray-50 border-t border-surface-200 z-20">
                    <div class="flex justify-between items-end mb-4">
                        <div class="text-gray-500 text-sm font-medium flex flex-col">
                            <span>Total a pagar</span>
                            <span class="text-xs text-gray-400">{{ cartCount }} artículos</span>
                        </div>
                        <span class="text-[33px] font-black text-gray-900 tracking-tight">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                    
                    <button 
                        @click="openPayment" 
                        class="w-full py-4 rounded-xl font-bold text-lg text-white shadow-lg shadow-indigo-200 transition-all flex justify-between items-center px-6 group active:scale-[0.98]"
                        :class="cart.length ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed shadow-none'"
                        :disabled="!cart.length"
                    >
                        <span>Cobrar</span>
                        <div class="bg-white/20 rounded-lg p-1.5 group-hover:translate-x-1 transition-transform">
                            <i class="pi pi-arrow-right text-sm"></i>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- === MÓVIL: BOTÓN FLOTANTE PARA VER CARRITO === -->
        <div class="fixed bottom-4 left-4 right-4 md:hidden z-50">
            <button 
                @click="showMobileCart = true"
                class="w-full bg-indigo-900 text-white rounded-full py-4 px-6 shadow-2xl flex items-center justify-between active:scale-95 transition-all border border-indigo-700"
            >
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-700 size-8 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ cartCount }}
                    </div>
                    <span class="font-bold text-lg">Ver Orden</span>
                </div>
                <span class="font-black text-xl">{{ formatCurrency(cartTotal) }}</span>
            </button>
        </div>

        <!-- === MÓVIL: SIDEBAR / DRAWER DEL CARRITO === -->
        <Drawer v-model:visible="showMobileCart" position="right" class="!w-full sm:!w-[28rem] !border-l !border-gray-200" :pt="{ header: { class: '!hidden' }, content: { class: '!p-0' } }">
            <div class="flex flex-col h-full bg-white">
                <!-- Header Móvil -->
                <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-10">
                    <h3 class="font-black text-xl text-gray-800">Tu Orden</h3>
                    <Button icon="pi pi-times" text rounded severity="secondary" @click="showMobileCart = false" />
                </div>

                <!-- Toggle Empleado Móvil -->
                <div class="px-5 py-2 bg-gray-50 flex justify-between items-center border-b border-gray-100">
                    <span class="text-sm font-bold text-gray-500">¿Venta a Empleado?</span>
                    <ToggleButton 
                        v-model="isEmployeeSale" 
                        onLabel="SÍ" 
                        offLabel="NO" 
                        onIcon="pi pi-check" 
                        offIcon="pi pi-times"
                        class="w-20 h-8 !text-xs"
                    />
                </div>

                <!-- Lista Items Móvil -->
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <div v-if="!cart.length" class="flex flex-col items-center justify-center h-64 text-gray-400">
                        <i class="pi pi-shopping-cart text-5xl mb-3 opacity-30"></i>
                        <p>Carrito vacío</p>
                    </div>

                    <div 
                        v-for="(item, idx) in cart" 
                        :key="idx" 
                        class="flex gap-4 items-start"
                    >
                        <div class="size-16 rounded-xl bg-gray-50 overflow-hidden flex-shrink-0 border border-gray-100">
                            <img v-if="item.image" :src="item.image" class="w-full h-full object-contain" />
                            <div v-else class="w-full h-full flex items-center justify-center"><i class="pi pi-image text-gray-300"></i></div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-1">
                                <p class="font-bold text-gray-800 text-base leading-tight">{{ item.name }}</p>
                                <p class="font-bold text-indigo-600">{{ formatCurrency(item.price * item.quantity) }}</p>
                            </div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center bg-gray-100 rounded-lg h-9">
                                    <button @click="updateQty(idx, -1)" class="w-9 h-full flex items-center justify-center text-gray-500 hover:text-red-500 rounded-l-lg active:bg-gray-200"><i class="pi pi-minus text-xs"></i></button>
                                    <span class="w-6 text-center text-sm font-bold text-gray-800">{{ item.quantity }}</span>
                                    <button @click="updateQty(idx, 1)" class="w-9 h-full flex items-center justify-center text-gray-500 hover:text-green-600 rounded-r-lg active:bg-gray-200"><i class="pi pi-plus text-xs"></i></button>
                                </div>
                                
                                <button @click="removeFromCart(idx)" class="text-red-500 hover:bg-red-50 p-2 rounded-full transition-colors">
                                    <i class="pi pi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Cobro Móvil -->
                <div class="p-5 border-t border-gray-100 bg-gray-50 sticky bottom-0 z-10">
                    <div class="flex justify-between items-end mb-4">
                        <span class="text-gray-500 font-medium">Total a pagar</span>
                        <span class="text-3xl font-black text-gray-900">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                    <button 
                        @click="openPayment" 
                        class="w-full py-4 rounded-xl font-bold text-xl text-white shadow-lg shadow-indigo-200 transition-all flex justify-center items-center gap-3 active:scale-[0.98]"
                        :class="cart.length ? 'bg-indigo-600' : 'bg-gray-300 cursor-not-allowed'"
                        :disabled="!cart.length"
                    >
                        <span>Ir a Pagar</span>
                        <i class="pi pi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </Drawer>

        <!-- MODAL COBRO -->
        <Dialog v-model:visible="showPaymentModal" modal :style="{ width: '700px' }" class="!rounded-[2rem] overflow-hidden" :pt="{ header: { class: '!hidden' }, content: { class: '!p-0' } }">
            <div class="flex flex-col md:flex-row h-[500px] md:h-auto">
                <!-- Columna Izquierda: Información de la Venta -->
                <div class="w-full md:w-1/2 p-6 bg-gray-50 border-r border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-6">
                            <div class="bg-indigo-100 size-7 rounded-xl text-indigo-600 flex items-center justify-center"><i class="pi pi-wallet"></i></div>
                            <span class="font-bold text-xl text-gray-800">Cobro</span>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <span class="text-gray-400 text-xs font-bold uppercase tracking-widest">Total a Pagar</span>
                                <p class="text-5xl font-black text-gray-900 tracking-tighter mt-1">{{ formatCurrency(cartTotal) }}</p>
                                <Tag v-if="isEmployeeSale" severity="info" value="Precio Empleado Aplicado" class="mt-2" />
                            </div>

                            <div class="bg-white p-4 rounded-2xl border border-indigo-100 shadow-sm">
                                <span class="text-indigo-900 font-bold text-xs uppercase tracking-wider block mb-1">Efectivo Recibido</span>
                                <span class="text-3xl font-mono font-bold text-indigo-600 block truncate">{{ keypadInput === '0' || keypadInput === '' ? '$0.00' : '$' + keypadInput }}</span>
                            </div>

                            <div class="flex justify-between items-end border-b border-dashed border-gray-300 pb-2">
                                <span class="font-bold text-gray-500 text-lg">Cambio</span>
                                <span class="text-3xl font-bold" :class="changeAmount >= 0 ? 'text-emerald-500' : 'text-rose-500'">{{ formatCurrency(Math.max(0, changeAmount)) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <p class="text-xs text-center text-gray-400">Verifique el monto antes de confirmar.</p>
                    </div>
                </div>

                <!-- Columna Derecha: Teclado y Acciones -->
                <div class="w-full md:w-1/2 p-6 bg-white">
                    <div class="grid grid-cols-4 gap-3 h-full">
                        <button v-for="n in [7,8,9]" :key="n" @click="appendNumber(n)" class="aspect-square bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl text-xl font-bold text-gray-700 shadow-sm active:scale-95 transition-all">{{ n }}</button>
                        <button @click="showPaymentModal = false" class="aspect-square bg-rose-50 hover:bg-rose-100 border border-rose-100 rounded-2xl text-rose-600 shadow-sm active:scale-95 transition-all flex flex-col items-center justify-center gap-1" v-tooltip.top="'Cancelar'">
                            <i class="pi pi-times text-xl"></i>
                        </button>

                        <button v-for="n in [4,5,6]" :key="n" @click="appendNumber(n)" class="aspect-square bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl text-xl font-bold text-gray-700 shadow-sm active:scale-95 transition-all">{{ n }}</button>
                        <button @click="backspace" class="aspect-square bg-orange-50 hover:bg-orange-100 border border-orange-100 rounded-2xl text-orange-500 shadow-sm active:scale-95 transition-all flex items-center justify-center" v-tooltip.top="'Borrar'">
                            <i class="pi pi-delete-left text-xl"></i>
                        </button>

                        <button v-for="n in [1,2,3]" :key="n" @click="appendNumber(n)" class="aspect-square bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl text-xl font-bold text-gray-700 shadow-sm active:scale-95 transition-all">{{ n }}</button>
                        
                        <button 
                            @click="processSale" 
                            :disabled="!canPay || processingPayment"
                            class="row-span-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-2xl font-bold shadow-lg shadow-indigo-200 active:scale-95 transition-all flex flex-col items-center justify-center gap-2 p-2"
                        >
                            <i v-if="processingPayment" class="pi pi-spin pi-spinner text-2xl"></i>
                            <i v-else class="pi pi-check text-2xl"></i>
                            <span class="text-xs uppercase tracking-widest">Cobrar</span>
                        </button>

                        <button @click="appendNumber(0)" class="col-span-2 h-full bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl text-xl font-bold text-gray-700 shadow-sm active:scale-95 transition-all">0</button>
                        <button @click="appendNumber('.')" class="aspect-square bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl text-xl font-bold text-gray-700 shadow-sm active:scale-95 transition-all">.</button>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- === NUEVO MODAL: HISTORIAL DE TRASPASOS COCINA === -->
        <Dialog v-model:visible="showHistoryModal" modal :style="{ width: '600px' }" class="!rounded-2xl" header="Envíos recientes de cocina">
            <div class="py-2">
                <div v-if="kitchenTransfers.length > 0">
                    <Timeline :value="kitchenTransfers" class="w-full">
                        <template #content="slotProps">
                            <div class="flex flex-col mb-4 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                <div class="flex justify-between items-start">
                                    <span class="font-bold text-gray-800 text-lg">{{ slotProps.item.product_name }}</span>
                                    <span class="text-xs font-bold bg-orange-100 text-orange-700 px-2 py-1 rounded-md">{{ slotProps.item.time }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Cantidad recibida:</span>
                                    <span class="font-black text-xl text-indigo-600">+{{ slotProps.item.quantity }} u.</span>
                                </div>
                                <p v-if="slotProps.item.notes" class="text-xs text-gray-400 mt-2 italic">
                                    "{{ slotProps.item.notes }}"
                                </p>
                            </div>
                        </template>
                        <template #opposite="slotProps">
                            <span class="text-gray-400 text-xs hidden md:block">Cocina &rarr; Carrito</span>
                        </template>
                    </Timeline>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-10 text-gray-400 gap-3">
                    <i class="pi pi-inbox !text-5xl opacity-30"></i>
                    <p class="font-medium">No hay traspasos registrados hoy.</p>
                    <p class="text-xs">Los movimientos de "Cocina" a "Carrito" aparecerán aquí.</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cerrar" icon="pi pi-check" @click="showHistoryModal = false" text severity="secondary" />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
::-webkit-scrollbar {
    width: 5px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 20px;
}
::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>