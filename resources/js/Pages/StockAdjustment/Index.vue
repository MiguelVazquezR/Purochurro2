<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";

const props = defineProps({
    locations: Array,
    products: Array,
    types: Array // [{label, value, color}]
});

const toast = useToast();

const form = useForm({
    type: props.types[0].value, // Default: Compra
    location_id: props.locations[0]?.id,
    product_id: null,
    quantity: null,
    notes: ''
});

// --- Lógica Reactiva ---
const selectedProduct = computed(() => props.products.find(p => p.id === form.product_id));
const selectedType = computed(() => props.types.find(t => t.value === form.type));

// Determinar si es una operación de Salida (para validar stock visualmente)
const isExitOperation = computed(() => {
    return ['waste', 'adjustment_out'].includes(form.type);
});

const currentStock = computed(() => {
    if (!selectedProduct.value || !form.location_id) return 0;
    return selectedProduct.value.inventories[form.location_id] || 0;
});

const submit = () => {
    // Validación Frontend básica
    if (isExitOperation.value && form.quantity > currentStock.value) {
        toast.add({ severity: 'error', summary: 'Stock Insuficiente', detail: 'No puedes restar más de lo que existe.', life: 3000 });
        return;
    }

    form.post(route('stock-adjustments.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Movimiento Registrado', detail: 'El inventario ha sido actualizado.', life: 3000 });
            form.reset('quantity', 'notes', 'product_id');
        }
    });
};
</script>

<template>
    <AppLayout title="Ajustes y Compras">
        <div class="max-w-4xl mx-auto py-8 px-4">

            <!-- Encabezado con Botones de Navegación -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-3">
                    <div class="bg-orange-100 p-3 rounded-2xl text-orange-600">
                        <i class="pi pi-sliders-h text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900">Ajustes de inventario</h1>
                        <p class="text-gray-500 text-sm">Registra compras, mermas o correcciones manuales.</p>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex gap-2">
                    <Link :href="route('pos.index')">
                        <Button 
                            label="Ir a PV" 
                            icon="pi pi-shopping-bag" 
                            severity="secondary" 
                            outlined 
                            rounded 
                            class="!font-bold bg-white shadow-sm" 
                        />
                    </Link>
                    <Link :href="route('products.index')">
                        <Button 
                            label="Productos" 
                            icon="pi pi-box" 
                            severity="secondary" 
                            outlined 
                            rounded 
                            class="!font-bold bg-white shadow-sm" 
                        />
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- Columna Izquierda: Tipo de Movimiento -->
                <div class="md:col-span-1 flex flex-col gap-4">
                    <div class="bg-white rounded-3xl shadow-lg border border-surface-200 p-4">
                        <label class="block font-bold text-gray-700 mb-3 px-2">Tipo de movimiento</label>
                        <div class="flex flex-col gap-2">
                            <button v-for="type in types" :key="type.value" @click="form.type = type.value"
                                class="flex items-center justify-between p-3 rounded-xl transition-all border text-left relative"
                                :class="form.type === type.value
                                    ? `bg-${type.color}-50 border-${type.color}-200 ring-1 ring-${type.color}-500`
                                    : 'bg-white border-transparent hover:bg-gray-50'">
                                <span class="font-medium text-sm"
                                    :class="form.type === type.value ? `text-${type.color}-700` : 'text-gray-600'">
                                    {{ type.label }}
                                </span>
                                <i v-if="form.type === type.value" class="absolute top-1 right-1 pi pi-check-circle !text-sm"
                                    :class="`text-${type.color}-600`"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario -->
                <div class="md:col-span-2 bg-white rounded-3xl shadow-xl border border-surface-200 overflow-hidden">
                    <div class="p-8 grid gap-6">

                        <!-- Location & Product -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="font-bold text-gray-700">Ubicación</label>
                                <Select v-model="form.location_id" :options="locations" optionLabel="name"
                                    optionValue="id" placeholder="Selecciona..." class="w-full" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-bold text-gray-700">Producto</label>
                                <Select v-model="form.product_id" :options="products" optionLabel="name"
                                    optionValue="id" filter placeholder="Buscar..." class="w-full">
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <img v-if="slotProps.option.image_url" :src="slotProps.option.image_url"
                                                class="w-6 h-6 rounded object-cover" />
                                            <span>{{ slotProps.option.name }}</span>
                                        </div>
                                    </template>
                                </Select>
                            </div>
                        </div>

                        <!-- Indicador de Stock Actual -->
                        <div v-if="selectedProduct && form.location_id"
                            class="bg-gray-50 p-4 rounded-xl border border-gray-100 flex items-center justify-between animate-fade-in">
                            <div>
                                <span class="block text-xs text-gray-400 font-bold uppercase">Stock actual en {{
                                    locations.find(l => l.id ===
                                    form.location_id)?.name }}</span>
                                <span class="text-xl font-black text-gray-800">{{ currentStock }} unidades</span>
                            </div>
                            <Tag :severity="isExitOperation ? (currentStock > 0 ? 'success' : 'danger') : 'info'"
                                :value="isExitOperation ? 'Disponible para salida' : 'Stock actual'" />
                        </div>

                        <!-- Cantidad y Notas -->
                        <div class="flex flex-col gap-2">
                            <label class="font-bold text-gray-700">Cantidad</label>
                            <InputNumber v-model="form.quantity" showButtons buttonLayout="horizontal" :min="1"
                                :max="isExitOperation ? currentStock : 10000" inputClass="w-full text-center font-bold"
                                class="w-full" fluid>
                                <template #incrementbuttonicon><span class="pi pi-plus" /></template>
                                <template #decrementbuttonicon><span class="pi pi-minus" /></template>
                            </InputNumber>
                            <Message v-if="form.errors.quantity" severity="error" size="small" variant="simple">{{
                                form.errors.quantity }}
                            </Message>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-bold text-gray-700">Notas</label>
                            <Textarea v-model="form.notes" rows="1" placeholder="Razón del movimiento..." class="w-full"
                                autoResize />
                        </div>

                    </div>

                    <div class="bg-gray-50 p-6 flex justify-end">
                        <Button :label="isExitOperation ? 'Registrar salida' : 'Registrar entrada'"
                            :icon="isExitOperation ? 'pi pi-minus-circle' : 'pi pi-plus-circle'" @click="submit"
                            :loading="form.processing" :severity="isExitOperation ? 'danger' : 'success'"
                            :disabled="!form.product_id || !form.location_id || !form.quantity || form.processing"
                            class="!font-bold !rounded-xl shadow-lg" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>