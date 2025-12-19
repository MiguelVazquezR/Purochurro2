<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";

const props = defineProps({
    locations: Array,
    products: Array
});

const toast = useToast();

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
</script>

<template>
    <AppLayout title="Traspasos de Inventario">
        <div class="max-w-4xl mx-auto py-8 px-4">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
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
                    <div class="flex flex-col gap-2">
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
                    <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 relative">
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                    <Button label="Confirmar Traspaso" icon="pi pi-check" @click="submit" :loading="form.processing"
                        :disabled="!form.product_id || !form.from_location_id || !form.to_location_id || !form.quantity"
                        class="!bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700 !font-bold !rounded-xl" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>