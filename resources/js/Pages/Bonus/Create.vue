<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

const form = useForm({
    name: '',
    description: '',
    amount: null,
    type: 'fixed',
    is_active: true,
});

const types = [
    { label: 'Monto Fijo ($)', value: 'fixed' },
    { label: 'Porcentaje (%)', value: 'percentage' }
];

const submit = () => {
    form.post(route('bonuses.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Bono registrado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Bono">
        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">
            
            <!-- Contenedor Principal -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                
                <!-- Encabezado Sticky -->
                <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('bonuses.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Nuevo bono</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Configura un incentivo para los empleados.</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('bonuses.index')">
                            <Button 
                                label="Cancelar" 
                                text 
                                severity="secondary" 
                                class="!font-medium"
                            />
                        </Link>
                        <Button 
                            label="Guardar" 
                            icon="pi pi-check" 
                            @click="submit" 
                            :loading="form.processing"
                            class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-bold shadow-lg shadow-orange-200/50"
                            rounded
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-10">
                    
                    <!-- Columna Izquierda: Configuración -->
                    <div class="lg:col-span-1 flex flex-col gap-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-5">
                            <span class="text-sm font-semibold text-surface-700">Configuración</span>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-surface-900">Estado activo</span>
                                    <span class="text-xs text-surface-500">Disponible para asignar</span>
                                </div>
                                <ToggleSwitch v-model="form.is_active" />
                            </div>
                            
                            <hr class="border-surface-100" />

                            <div class="flex flex-col gap-2">
                                <label class="font-medium text-surface-700 text-sm">Tipo de cálculo</label>
                                <SelectButton v-model="form.type" :options="types" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Detalles -->
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-tag text-orange-500"></i> Detalles del Concepto
                            </h2>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label for="name" class="font-medium text-surface-700">Nombre del bono</label>
                                    <InputText 
                                        id="name" 
                                        v-model="form.name" 
                                        class="w-full" 
                                        placeholder="Ej. Bono de Puntualidad" 
                                        :invalid="!!form.errors.name"
                                        autofocus
                                    />
                                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="amount" class="font-medium text-surface-700">Valor</label>
                                    <InputNumber 
                                        id="amount" 
                                        v-model="form.amount" 
                                        :mode="form.type === 'percentage' ? 'decimal' : 'currency'" 
                                        :currency="form.type === 'percentage' ? undefined : 'MXN'" 
                                        locale="es-MX" 
                                        :suffix="form.type === 'percentage' ? '%' : ''"
                                        :min="0"
                                        :max="form.type === 'percentage' ? 100 : 1000000"
                                        placeholder="0.00"
                                        :invalid="!!form.errors.amount"
                                        class="w-full"
                                        inputClass="!font-bold !text-lg !text-surface-900"
                                    />
                                    <small class="text-surface-400 text-xs">
                                        {{ form.type === 'percentage' ? 'Porcentaje sobre el sueldo base.' : 'Monto fijo en moneda nacional.' }}
                                    </small>
                                    <small v-if="form.errors.amount" class="text-red-500">{{ form.errors.amount }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="description" class="font-medium text-surface-700">Descripción (Opcional)</label>
                                    <Textarea 
                                        id="description" 
                                        v-model="form.description" 
                                        rows="4" 
                                        class="w-full !resize-none" 
                                        placeholder="Reglas de aplicación, condiciones, etc." 
                                    />
                                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
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
:deep(.p-inputtext), :deep(.p-textarea), :deep(.p-inputnumber-input) {
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}
:deep(.p-inputtext:focus), :deep(.p-textarea:focus) {
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
    border-color: #f97316;
}
:deep(.p-selectbutton .p-button.p-highlight) {
    background-color: #fff7ed;
    border-color: #ffedd5;
    color: #c2410c;
}
:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: #f97316 !important;
    border-color: #f97316 !important;
}
</style>