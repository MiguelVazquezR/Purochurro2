<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';
import CategoryManager from '@/Components/CategoryManager.vue';

const props = defineProps({
    product: Object,
    categories: Array
});

const toast = useToast();
const fileInput = ref(null);

// Formulario de Producto (Inicializado con datos existentes)
const form = useForm({
    _method: 'PUT', // Necesario para enviar archivos en Laravel usando PUT
    category_id: props.product.category_id,
    name: props.product.name,
    barcode: props.product.barcode,
    description: props.product.description,
    price: parseFloat(props.product.price),
    employee_price: props.product.employee_price ? parseFloat(props.product.employee_price) : null,
    cost: props.product.cost ? parseFloat(props.product.cost) : null,
    is_sellable: Boolean(props.product.is_sellable),
    track_inventory: Boolean(props.product.track_inventory),
    is_active: Boolean(props.product.is_active),
    image: null,
});

// Control del diálogo de categorías
const manageCategoriesDialog = ref(false);

// --- Lógica de Imágenes ---
// Inicializamos el preview con la imagen existente del backend
const imagePreview = ref(props.product.image_url);

const onFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const removeImage = () => {
    form.image = null;
    imagePreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('products.update', props.product.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Producto actualizado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar Producto">

        <!-- Componente Gestor de Categorías -->
        <CategoryManager v-model:visible="manageCategoriesDialog" :categories="categories" />

        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">

            <!-- COLUMNA IZQUIERDA -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                <div
                    class="bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('products.index')">
                        <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Editar producto</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Actualiza la información del ítem.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button label="Cancelar" text severity="secondary" @click="form.reset()" class="!font-medium" />
                        <Button label="Guardar cambios" icon="pi pi-check" @click="submit" :loading="form.processing"
                            class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-bold shadow-lg shadow-orange-200/50"
                            rounded />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-10">
                    <!-- Config e Imagen -->
                    <div class="lg:col-span-1 flex flex-col gap-6">
                        <!-- Imagen -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col items-center text-center">
                            <span class="text-sm font-semibold text-surface-700 mb-4 self-start">Imagen del producto</span>
                            <div class="relative group cursor-pointer w-48 h-48">
                                <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                                <div 
                                    class="w-full h-full rounded-2xl overflow-hidden border-2 border-dashed border-surface-300 flex items-center justify-center bg-surface-50 transition-all duration-300 group-hover:border-orange-400 group-hover:bg-orange-50"
                                    @click="triggerFileInput"
                                >
                                    <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                                    <div v-else class="flex flex-col items-center gap-2 text-surface-400">
                                        <i class="pi pi-image text-3xl"></i>
                                        <span class="text-xs font-medium">Click para cambiar</span>
                                    </div>
                                </div>
                                <Button v-if="imagePreview" icon="pi pi-times" rounded severity="danger" class="absolute -top-4 -right-2 !size-6 shadow-md" @click.stop="removeImage" />
                            </div>
                            <small class="text-surface-400 text-xs mt-3">PNG, JPG hasta 2MB.</small>
                            <small v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</small>
                        </div>

                        <!-- Configuración -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-5">
                            <span class="text-sm font-semibold text-surface-700">Configuración</span>
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col"><span class="text-sm font-medium text-surface-900">Estado activo</span><span class="text-xs text-surface-500">Visible en el sistema</span></div>
                                <ToggleSwitch v-model="form.is_active" />
                            </div>
                            <hr class="border-surface-100" />
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col"><span class="text-sm font-medium text-surface-900">Vendible</span><span class="text-xs text-surface-500">Aparece en punto de venta</span></div>
                                <ToggleSwitch v-model="form.is_sellable" />
                            </div>
                            <hr class="border-surface-100" />
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col"><span class="text-sm font-medium text-surface-900">Controlar stock</span><span class="text-xs text-surface-500">Restar inventario al vender</span></div>
                                <ToggleSwitch v-model="form.track_inventory" />
                            </div>
                        </div>
                    </div>

                    <!-- Datos Generales -->
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-box text-orange-500"></i> Información general
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="name" class="font-medium text-surface-700">Nombre del producto</label>
                                    <InputText id="name" v-model="form.name" class="w-full" placeholder="Ej. Hamburguesa Doble Queso" :invalid="!!form.errors.name" />
                                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                                </div>

                                <!-- SELECTOR DE CATEGORÍA -->
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="category" class="font-medium text-surface-700">Categoría</label>
                                    <div class="flex gap-2">
                                        <Select 
                                            id="category"
                                            v-model="form.category_id" 
                                            :options="categories" 
                                            optionLabel="name" 
                                            optionValue="id" 
                                            placeholder="Seleccionar categoría" 
                                            class="w-full"
                                            :invalid="!!form.errors.category_id"
                                            filter
                                        >
                                            <template #option="slotProps">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: slotProps.option.color || '#ccc' }"></div>
                                                    <div>{{ slotProps.option.name }}</div>
                                                </div>
                                            </template>
                                        </Select>
                                        <Button 
                                            icon="pi pi-cog" 
                                            severity="secondary" 
                                            v-tooltip.top="'Gestionar Categorías'" 
                                            @click="manageCategoriesDialog = true"
                                        />
                                    </div>
                                    <small v-if="form.errors.category_id" class="text-red-500">{{ form.errors.category_id }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="barcode" class="font-medium text-surface-700">Código de barras (Opcional)</label>
                                    <IconField>
                                        <InputIcon class="pi pi-barcode" />
                                        <InputText id="barcode" v-model="form.barcode" class="w-full" placeholder="Escanea o escribe..." :invalid="!!form.errors.barcode" />
                                    </IconField>
                                    <small v-if="form.errors.barcode" class="text-red-500">{{ form.errors.barcode }}</small>
                                </div>
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label for="description" class="font-medium text-surface-700">Descripción</label>
                                    <Textarea id="description" v-model="form.description" rows="3" class="w-full !resize-none" placeholder="Detalles del producto..." />
                                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-tag text-green-500"></i> Precios y costos
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label for="price" class="font-medium text-surface-700">Precio público</label>
                                    <InputNumber id="price" v-model="form.price" mode="currency" currency="MXN" locale="es-MX" class="w-full" inputClass="!font-bold !text-surface-900" placeholder="$0.00" :invalid="!!form.errors.price" fluid />
                                    <small v-if="form.errors.price" class="text-red-500">{{ form.errors.price }}</small>
                                </div>
                                
                                <div class="flex flex-col gap-2">
                                    <label for="employee_price" class="font-medium text-surface-700">Precio empleado</label>
                                    <InputNumber id="employee_price" v-model="form.employee_price" mode="currency" currency="MXN" locale="es-MX" class="w-full" placeholder="$0.00" :invalid="!!form.errors.employee_price" />
                                    <small v-if="form.errors.employee_price" class="text-red-500">{{ form.errors.employee_price }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="cost" class="font-medium text-surface-700">Costo (insumos)</label>
                                    <InputNumber id="cost" v-model="form.cost" mode="currency" currency="MXN" locale="es-MX" class="w-full" placeholder="$0.00" :invalid="!!form.errors.cost" />
                                    <small v-if="form.errors.cost" class="text-red-500">{{ form.errors.cost }}</small>
                                    <small v-else class="text-surface-400 text-xs">Solo visible para admin.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-inputtext), :deep(.p-textarea) {
    border-radius: 0.75rem;
    padding: 0.5rem 1rem; 
}
:deep(.p-select) {
    border-radius: 0.75rem;
}
:deep(.p-inputnumber-input) {
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
}
:deep(.p-select .p-select-label) {
    padding: 0.5rem 0.5rem; 
}
:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: #f97316 !important;
    border-color: #f97316 !important;
}
</style>