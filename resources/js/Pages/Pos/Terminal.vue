<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const props = defineProps({
    operation: Object,
    products: Array,
    locations: Array
});

const toast = useToast();

// Estados
const cart = ref([]);
const searchQuery = ref('');
const selectedLocation = ref(props.locations[0]?.id);
const showPaymentModal = ref(false);
const processingPayment = ref(false);
const keypadInput = ref('0');

const paymentForm = useForm({
    location_id: null,
    payment_method: 'cash',
    items: [],
    cash_received: 0,
});

// --- Lógica Carrito ---
const filteredProducts = computed(() => {
    if (!searchQuery.value) return props.products;
    const q = searchQuery.value.toLowerCase();
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || p.barcode?.includes(q)
    );
});

const addToCart = (product) => {
    const item = cart.value.find(i => i.product_id === product.id);
    if (item) {
        item.quantity++;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: parseFloat(product.price),
            image: product.image_url,
            quantity: 1
        });
    }
};

const updateQty = (index, delta) => {
    const item = cart.value[index];
    const newVal = item.quantity + delta;
    if (newVal > 0) item.quantity = newVal;
    else cart.value.splice(index, 1);
};

const clearCart = () => cart.value = [];

const cartTotal = computed(() => cart.value.reduce((sum, i) => sum + (i.price * i.quantity), 0));
const cartCount = computed(() => cart.value.reduce((sum, i) => sum + i.quantity, 0));

// --- Lógica Pago ---
const appendNumber = (n) => {
    if (keypadInput.value.includes('.') && n === '.') return;
    keypadInput.value = (keypadInput.value === '0' && n !== '.') ? n.toString() : keypadInput.value + n;
};
const clearKeypad = () => keypadInput.value = '0'; // Se usa al cerrar modal si se desea
const backspace = () => keypadInput.value = keypadInput.value.length > 1 ? keypadInput.value.slice(0, -1) : '0';

const changeAmount = computed(() => (parseFloat(keypadInput.value) || 0) - cartTotal.value);
const canPay = computed(() => (parseFloat(keypadInput.value) || 0) >= cartTotal.value && cart.value.length > 0);

const openPayment = () => {
    if (!cart.value.length) return toast.add({ severity: 'warn', summary: 'Carrito vacío', life: 2000 });
    keypadInput.value = '0';
    showPaymentModal.value = true;
};

const processSale = () => {
    if (!canPay.value) return;
    processingPayment.value = true;
    paymentForm.location_id = selectedLocation.value;
    paymentForm.items = cart.value.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price }));
    
    paymentForm.post(route('pos.store-sale'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Venta OK', detail: `Cambio: ${formatCurrency(changeAmount.value)}`, life: 5000 });
            showPaymentModal.value = false;
            clearCart();
            processingPayment.value = false;
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'No se procesó la venta.', life: 3000 });
            processingPayment.value = false;
        }
    });
};

const formatCurrency = (val) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);
</script>

<template>
    <AppLayout title="POS">
        <!-- Layout Principal: Grid de 12 columnas para estabilidad -->
        <div class="h-[calc(100vh-5rem)] p-3 gap-4 grid grid-cols-1 md:grid-cols-12 overflow-hidden bg-gray-50/50">
            
            <!-- IZQUIERDA: Catálogo (8 columnas) -->
            <div class="md:col-span-8 flex flex-col bg-white border border-surface-200 rounded-3xl shadow-sm overflow-hidden h-full">
                <!-- Header Catálogo -->
                <div class="p-4 border-b border-surface-100 flex gap-4 items-center bg-white z-10">
                    <div class="relative flex-1">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input v-model="searchQuery" type="text" placeholder="Buscar productos..." class="w-full pl-10 pr-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div v-if="locations.length > 1" class="w-48">
                        <Select v-model="selectedLocation" :options="locations" optionLabel="name" optionValue="id" class="w-full" placeholder="Caja" />
                    </div>
                </div>

                <!-- Grid Productos (Scroll) -->
                <div class="flex-1 overflow-y-auto p-4 content-start">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 pb-20">
                        <button 
                            v-for="product in filteredProducts" 
                            :key="product.id"
                            @click="addToCart(product)"
                            class="group bg-white border border-surface-100 rounded-2xl p-3 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all text-left flex flex-col gap-2 h-full"
                        >
                            <div class="aspect-square rounded-xl bg-gray-100 overflow-hidden relative w-full">
                                <img v-if="product.image_url" :src="product.image_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-300"><i class="pi pi-image text-3xl"></i></div>
                                <span class="absolute bottom-1 right-1 bg-white/90 px-2 py-0.5 rounded-md text-xs font-bold shadow-sm">{{ formatCurrency(product.price) }}</span>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 text-sm leading-tight line-clamp-2">{{ product.name }}</p>
                                <Tag v-if="product.track_inventory" :severity="product.stock > 0 ? 'success' : 'danger'" class="!text-[10px] !px-1.5 mt-1">{{ product.stock }} disp.</Tag>
                            </div>
                        </button>
                    </div>
                    <!-- Empty State -->
                    <div v-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400">
                        <i class="pi pi-search text-4xl mb-2"></i>
                        <p>No se encontraron productos</p>
                    </div>
                </div>
            </div>

            <!-- DERECHA: Carrito (4 columnas) -->
            <div class="md:col-span-4 flex flex-col bg-white border border-surface-200 rounded-3xl shadow-xl overflow-hidden h-full">
                <!-- Header Carrito -->
                <div class="p-4 border-b border-surface-100 flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="pi pi-shopping-cart text-indigo-500"></i> Orden</h3>
                    <Button icon="pi pi-trash" text rounded severity="danger" @click="clearCart" :disabled="!cart.length" v-tooltip="'Limpiar'" />
                </div>

                <!-- Lista Items -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div v-if="!cart.length" class="h-full flex flex-col items-center justify-center text-gray-400 opacity-50">
                        <i class="pi pi-receipt text-6xl mb-2"></i>
                        <p class="text-sm">Ticket vacío</p>
                    </div>
                    <div v-for="(item, idx) in cart" :key="idx" class="flex gap-3 items-center bg-white p-2 rounded-xl border border-gray-100 shadow-sm">
                        <!-- Controles Cantidad Verticales -->
                        <div class="flex flex-col bg-gray-50 rounded-lg p-0.5">
                            <button @click="updateQty(idx, 1)" class="w-8 h-7 flex items-center justify-center text-green-600 hover:bg-white rounded shadow-sm"><i class="pi pi-plus text-xs"></i></button>
                            <span class="text-center text-sm font-bold py-0.5">{{ item.quantity }}</span>
                            <button @click="updateQty(idx, -1)" class="w-8 h-7 flex items-center justify-center text-red-500 hover:bg-white rounded shadow-sm"><i class="pi pi-minus text-xs"></i></button>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm text-gray-800 truncate">{{ item.name }}</p>
                            <p class="text-xs text-gray-500">{{ formatCurrency(item.price) }}</p>
                        </div>
                        <p class="font-bold text-indigo-600 text-sm">{{ formatCurrency(item.price * item.quantity) }}</p>
                    </div>
                </div>

                <!-- Footer Totales -->
                <div class="p-4 bg-gray-50 border-t border-surface-200 space-y-3">
                    <div class="flex justify-between items-end">
                        <span class="text-gray-500 text-sm">{{ cartCount }} artículos</span>
                        <span class="text-3xl font-black text-gray-900">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                    <button 
                        @click="openPayment" 
                        class="w-full py-4 rounded-xl font-bold text-lg text-white shadow-lg transition-all flex justify-center items-center gap-2"
                        :class="cart.length ? 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200' : 'bg-gray-300 cursor-not-allowed'"
                        :disabled="!cart.length"
                    >
                        <span>Cobrar</span>
                        <i class="pi pi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL COBRO -->
        <Dialog v-model:visible="showPaymentModal" modal header="Cobrar (Efectivo)" :style="{ width: '380px' }">
            <div class="flex flex-col gap-4">
                <div class="text-center py-2">
                    <span class="text-gray-500 text-xs uppercase tracking-wider">Total a Pagar</span>
                    <p class="text-4xl font-black text-gray-900">{{ formatCurrency(cartTotal) }}</p>
                </div>

                <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100 flex justify-between items-center">
                    <span class="text-indigo-800 font-bold text-sm">Recibido:</span>
                    <span class="text-2xl font-mono font-bold text-indigo-700">{{ keypadInput === '' ? '$0.00' : '$' + keypadInput }}</span>
                </div>

                <div class="flex justify-between items-center px-2">
                    <span class="font-bold text-gray-600">Cambio:</span>
                    <span class="text-xl font-bold" :class="changeAmount >= 0 ? 'text-green-600' : 'text-red-500'">{{ formatCurrency(Math.max(0, changeAmount)) }}</span>
                </div>

                <!-- Teclado Numérico -->
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="n in [1,2,3,4,5,6,7,8,9]" :key="n" @click="appendNumber(n)" class="h-12 bg-white border border-gray-200 rounded-lg text-xl font-bold shadow-sm active:bg-gray-100">{{ n }}</button>
                    <button @click="appendNumber('.')" class="h-12 bg-white border border-gray-200 rounded-lg text-xl font-bold shadow-sm active:bg-gray-100">.</button>
                    <button @click="appendNumber(0)" class="h-12 bg-white border border-gray-200 rounded-lg text-xl font-bold shadow-sm active:bg-gray-100">0</button>
                    <button @click="backspace" class="h-12 bg-red-50 border border-red-100 rounded-lg text-red-500 shadow-sm active:bg-red-100 flex items-center justify-center"><i class="pi pi-delete-left text-xl"></i></button>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-2">
                    <Button label="Cancelar" severity="secondary" outlined @click="showPaymentModal = false" />
                    <Button 
                        label="Confirmar" 
                        severity="success" 
                        icon="pi pi-check" 
                        @click="processSale" 
                        :loading="processingPayment" 
                        :disabled="!canPay" 
                    />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Ocultar scrollbar default pero permitir scroll */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>