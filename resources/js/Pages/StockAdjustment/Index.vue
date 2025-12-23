<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import InputText from 'primevue/inputtext';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

const props = defineProps({
    locations: Array,
    products: Array,
    types: Array // [{label, value, color, icon}]
});

const toast = useToast();

// Formulario Principal (Para el envío al servidor)
const form = useForm({
    type: props.types[0].value, // Default: Compra
    location_id: props.locations[0]?.id,
    notes: '',
    items: [] // Array de objetos { product_id, quantity, product_obj }
});

// Estados Locales para el "Carrito" de agregado
const currentProductId = ref(null);
const currentQuantity = ref(null);

// --- Lógica Reactiva ---

// Producto seleccionado actualmente
const currentProductObj = computed(() => {
    return props.products.find(p => p.id === currentProductId.value);
});

// Tipo de movimiento seleccionado
const selectedType = computed(() => {
    return props.types.find(t => t.value === form.type);
});

// Determinar si es una operación de Salida
const isExitOperation = computed(() => {
    return ['waste', 'adjustment_out'].includes(form.type);
});

// Stock actual del producto seleccionado en la ubicación elegida
const currentStock = computed(() => {
    if (!currentProductObj.value || !form.location_id) return 0;

    // Stock real
    const realStock = currentProductObj.value.inventories[form.location_id] || 0;

    // Si es salida, restamos lo que ya tenemos en el carrito para validar correctamente
    if (isExitOperation.value) {
        const inCartItem = form.items.find(item => item.product_id === currentProductId.value);
        const inCartQty = inCartItem ? inCartItem.quantity : 0;
        return Math.max(0, realStock - inCartQty);
    }

    return realStock;
});

// Agregar item a la lista
const addItem = () => {
    if (!currentProductId.value || !currentQuantity.value || currentQuantity.value <= 0) return;

    // Validación de stock solo si es salida
    if (isExitOperation.value && currentQuantity.value > currentStock.value) {
        toast.add({ severity: 'error', summary: 'Stock Insuficiente', detail: 'No puedes restar más de lo disponible.', life: 3000 });
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
            product: currentProductObj.value
        });
    }

    // Reset inputs temporales
    currentProductId.value = null;
    currentQuantity.value = null;
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const submit = () => {
    if (form.items.length === 0) {
        toast.add({ severity: 'warn', summary: 'Lista Vacía', detail: 'Agrega al menos un producto.', life: 3000 });
        return;
    }

    form.post(route('stock-adjustments.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Movimiento Registrado', detail: 'Inventario actualizado.', life: 3000 });
            form.reset('items', 'notes');
            // Mantenemos type y location para facilitar cargas consecutivas
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Verifica los datos.', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Ajustes y Compras">
        <div class="max-w-6xl mx-auto py-8 px-4">

            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3">
                    <div class="bg-orange-100 p-3 rounded-2xl text-orange-600">
                        <i class="pi pi-sliders-h text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900">Ajustes múltiples</h1>
                        <p class="text-gray-500 text-sm">Registra compras, mermas o correcciones por lotes.</p>
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

                    <!-- 1. Configuración Global (Tipo y Ubicación) -->
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-surface-200">
                        <h2 class="font-bold text-lg text-gray-800 mb-4">1. Configuración</h2>

                        <!-- Selector de Tipo Visual -->
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button v-for="type in types" :key="type.value"
                                @click="{ form.type = type.value; form.items = []; }"
                                class="flex flex-col items-center justify-center p-3 rounded-xl transition-all border text-center gap-1"
                                :class="form.type === type.value
                                    ? `bg-${type.color}-50 border-${type.color}-200 ring-1 ring-${type.color}-500`
                                    : 'bg-white border-gray-100 hover:bg-gray-50'">
                                <i class="pi"
                                    :class="[type.icon, form.type === type.value ? `text-${type.color}-600` : 'text-gray-400']"></i>
                                <span class="font-bold text-xs leading-tight"
                                    :class="form.type === type.value ? `text-${type.color}-700` : 'text-gray-600'">
                                    {{ type.label }}
                                </span>
                            </button>
                        </div>

                        <!-- Selector de Ubicación -->
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1">Ubicación Afectada</label>
                            <Select v-model="form.location_id" :options="locations" optionLabel="name" optionValue="id"
                                placeholder="Selecciona ubicación..." class="w-full mt-1" @change="form.items = []" />
                        </div>
                    </div>

                    <!-- 2. Agregar Productos -->
                    <div class="bg-orange-100/50 p-6 rounded-3xl border border-orange-300 relative overflow-hidden">
                        <!-- Overlay si falta config -->
                        <div v-if="!form.location_id"
                            class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center text-center p-4">
                            <span class="text-sm font-semibold text-gray-500">Selecciona una ubicación primero</span>
                        </div>

                        <h2 class="font-bold text-lg text-gray-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-plus-circle text-orange-600"></i> Agregar item
                        </h2>

                        <div class="flex flex-col gap-4">
                            <!-- Producto -->
                            <Select v-model="currentProductId" :options="products" optionLabel="name" optionValue="id"
                                filter placeholder="Buscar producto..." class="w-full" resetFilterOnHide>
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <img v-if="slotProps.option.image_url" :src="slotProps.option.image_url"
                                            class="w-6 h-6 rounded object-contain" />
                                        <span class="text-sm">{{ slotProps.option.name }}</span>
                                    </div>
                                </template>
                            </Select>

                            <!-- Stock Info -->
                            <div v-if="currentProductId" class="flex justify-between items-center text-sm px-1">
                                <span class="text-gray-500">Stock actual:</span>
                                <span class="font-bold text-gray-800">{{ currentStock }} u.</span>
                            </div>

                            <!-- Cantidad y Botón -->
                            <div class="flex gap-2">
                                <InputNumber v-model="currentQuantity" placeholder="Cant." :min="1"
                                    :max="isExitOperation ? currentStock : 100000" showButtons buttonLayout="horizontal"
                                    inputClass="text-center font-bold !w-16" class="w-1/2"
                                    :disabled="!currentProductId || (isExitOperation && currentStock === 0)">
                                    <template #incrementbuttonicon><span class="pi pi-plus" /></template>
                                    <template #decrementbuttonicon><span class="pi pi-minus" /></template>
                                </InputNumber>

                                <Button label="Agregar" icon="pi pi-level-down"
                                    class="w-1/2 !bg-orange-600 !border-orange-600" @click="addItem"
                                    :disabled="!currentProductId || !currentQuantity || (isExitOperation && currentQuantity > currentStock)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: Lista y Confirmación -->
                <div class="lg:col-span-2 flex flex-col h-full">
                    <div
                        class="bg-white rounded-3xl shadow-xl border border-surface-200 flex-1 flex flex-col overflow-hidden">

                        <!-- Header Lista -->
                        <div class="p-4 border-b border-surface-100 flex justify-between items-center bg-gray-50">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-700">Lista de movimientos</h3>
                                <Tag :value="selectedType?.label" :severity="isExitOperation ? 'danger' : 'success'"
                                    rounded />
                            </div>
                            <span
                                class="text-xs font-medium px-2 py-1 bg-white rounded border border-gray-200 text-gray-500">
                                {{ form.items.length }} items
                            </span>
                        </div>

                        <!-- Lista Items -->
                        <div class="flex-1 overflow-y-auto min-h-[300px] bg-white p-0">
                            <div v-if="form.items.length === 0"
                                class="h-full flex flex-col items-center justify-center text-gray-300 p-8">
                                <i class="pi pi-list text-5xl mb-2 opacity-50"></i>
                                <p class="text-sm">No hay items en la lista</p>
                            </div>

                            <div v-else>
                                <!-- Tabla Desktop -->
                                <DataTable :value="form.items" class="!hidden sm:!block p-datatable-sm" stripedRows>
                                    <Column header="Producto">
                                        <template #body="slotProps">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                                    <img v-if="slotProps.data.product.image_url"
                                                        :src="slotProps.data.product.image_url"
                                                        class="w-full h-full object-contain">
                                                    <i v-else class="pi pi-box text-gray-400"></i>
                                                </div>
                                                <span class="font-medium text-gray-700">{{ slotProps.data.product.name
                                                    }}</span>
                                            </div>
                                        </template>
                                    </Column>
                                    <Column header="Cant." field="quantity" class="w-[100px] text-center font-bold">
                                        <template #body="slotProps">
                                            <span :class="isExitOperation ? 'text-red-600' : 'text-green-600'">
                                                {{ isExitOperation ? '-' : '+' }}{{ slotProps.data.quantity }}
                                            </span>
                                        </template>
                                    </Column>
                                    <Column class="w-[60px]">
                                        <template #body="slotProps">
                                            <Button icon="pi pi-trash" text rounded severity="danger"
                                                @click="removeItem(slotProps.index)" />
                                        </template>
                                    </Column>
                                </DataTable>

                                <!-- Lista Móvil -->
                                <div class="sm:!hidden !flex flex-col divide-y divide-gray-100">
                                    <div v-for="(item, index) in form.items" :key="index"
                                        class="p-4 flex justify-between items-center gap-3">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div
                                                class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                                <img v-if="item.product.image_url" :src="item.product.image_url"
                                                    class="w-full h-full object-contain">
                                                <i v-else class="pi pi-box text-gray-400"></i>
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span class="font-medium text-gray-800 truncate">{{ item.product.name
                                                    }}</span>
                                                <span class="text-xs font-bold"
                                                    :class="isExitOperation ? 'text-red-600' : 'text-green-600'">
                                                    {{ isExitOperation ? 'Salida' : 'Entrada' }}: {{ item.quantity }}
                                                </span>
                                            </div>
                                        </div>
                                        <Button icon="pi pi-trash" text rounded severity="danger"
                                            class="!w-8 !h-8 shrink-0" @click="removeItem(index)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-4 bg-gray-50 border-t border-surface-200">
                            <div class="mb-4">
                                <InputText v-model="form.notes" placeholder="Nota general (ej. Compra Factura #123)"
                                    class="w-full text-sm" />
                            </div>
                            <div class="flex justify-end">
                                <Button
                                    :label="isExitOperation ? 'Confirmar salida múltiple' : 'Confirmar entrada múltiple'"
                                    :icon="isExitOperation ? 'pi pi-minus-circle' : 'pi pi-check-circle'"
                                    @click="submit" :loading="form.processing"
                                    :severity="isExitOperation ? 'danger' : 'success'"
                                    :disabled="form.items.length === 0" class="w-full sm:w-auto !font-bold shadow-lg" />
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>