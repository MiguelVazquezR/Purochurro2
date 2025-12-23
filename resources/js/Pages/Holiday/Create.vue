<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

const form = useForm({
    name: '',
    date: new Date(),
    mandatory_rest: true,
    pay_multiplier: 2.0,
});

const submit = () => {
    // Transformamos fechas al formato YYYY-MM-DD para MySQL
    form.transform((data) => ({
        ...data,
        date: data.date ? data.date.toISOString().split('T')[0] : null,
    })).post(route('holidays.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Día feriado registrado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Nuevo Feriado">
        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">
            
            <!-- Contenedor Principal -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                
                <!-- Encabezado Sticky -->
                <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('holidays.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Nuevo feriado</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Registra un día de descanso obligatorio o festivo.</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('holidays.index')">
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

                <!-- Formulario -->
                <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-6">
                    <h2 class="text-lg font-bold text-surface-900 mb-2 flex items-center gap-2">
                        <i class="pi pi-calendar-plus text-orange-500"></i> Detalles del día
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Nombre -->
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label for="name" class="font-medium text-surface-700">Nombre de la festividad</label>
                            <InputText 
                                id="name" 
                                v-model="form.name" 
                                class="w-full" 
                                placeholder="Ej. Año Nuevo, Día de la Independencia" 
                                :invalid="!!form.errors.name"
                                autofocus
                            />
                            <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <!-- Fecha -->
                        <div class="flex flex-col gap-2">
                            <label for="date" class="font-medium text-surface-700">Fecha</label>
                            <DatePicker 
                                id="date" 
                                v-model="form.date" 
                                showIcon 
                                dateFormat="dd/mm/yy"
                                class="w-full"
                                :invalid="!!form.errors.date"
                            />
                            <small v-if="form.errors.date" class="text-red-500">{{ form.errors.date }}</small>
                        </div>

                        <!-- Multiplicador -->
                        <div class="flex flex-col gap-2">
                            <label for="multiplier" class="font-medium text-surface-700">Pago multiplicador</label>
                            <InputNumber 
                                id="multiplier" 
                                v-model="form.pay_multiplier" 
                                mode="decimal" 
                                :minFractionDigits="1" 
                                :maxFractionDigits="1"
                                :min="1" 
                                :max="5"
                                showButtons
                                suffix="x"
                                :invalid="!!form.errors.pay_multiplier"
                                class="w-full"
                            />
                            <small class="text-surface-400 text-xs">Ej. 2.0 (Doble) para días oficiales trabajados.</small>
                            <small v-if="form.errors.pay_multiplier" class="text-red-500">{{ form.errors.pay_multiplier }}</small>
                        </div>

                        <!-- Switch Obligatorio -->
                        <div class="flex flex-col gap-2 md:col-span-2 pt-2">
                            <div class="flex items-center justify-between p-4 bg-surface-50 rounded-xl border border-surface-100">
                                <div>
                                    <span class="font-bold text-surface-900 block">Descanso obligatorio</span>
                                    <span class="text-xs text-surface-500">¿Es un día de descanso oficial por ley?</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium" :class="form.mandatory_rest ? 'text-orange-600' : 'text-surface-500'">
                                        {{ form.mandatory_rest ? 'Sí' : 'No' }}
                                    </span>
                                    <ToggleSwitch v-model="form.mandatory_rest" />
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
:deep(.p-inputtext:focus) {
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
    border-color: #f97316;
}
:deep(.p-datepicker-input) {
    border-radius: 0.75rem;
}
:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: #f97316 !important;
    border-color: #f97316 !important;
}
</style>