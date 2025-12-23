<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

const form = useForm({
    concept: '',
    amount: null,
    date: new Date(), // Inicializamos con la fecha de hoy
    notes: '',
});

const submit = () => {
    // Transformamos la fecha al formato YYYY-MM-DD que espera MySQL
    form.transform((data) => ({
        ...data,
        date: data.date ? data.date.toISOString().split('T')[0] : null,
    })).post(route('expenses.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Gasto registrado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Registrar Gasto">
        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">
            
            <!-- Contenedor Principal -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                
                <!-- Encabezado con Botones (Estilo ProductCreate) -->
                <!-- sticky top-16: Se pega justo debajo del AppTopbar -->
                <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('expenses.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Registrar gasto</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Registra una salida de dinero o compra de insumos.</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('expenses.index')">
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
                <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 mb-10">
                    <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                        <i class="pi pi-receipt text-orange-500 bg-orange-50 p-2 rounded-lg"></i> 
                        Detalles del Movimiento
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Concepto (Ancho completo) -->
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label for="concept" class="font-medium text-surface-700">Concepto</label>
                            <InputText 
                                id="concept" 
                                v-model="form.concept" 
                                class="w-full" 
                                placeholder="Ej. Compra de verduras, Pago de luz..." 
                                :invalid="!!form.errors.concept"
                                autofocus
                            />
                            <small v-if="form.errors.concept" class="text-red-500">{{ form.errors.concept }}</small>
                        </div>

                        <!-- Monto -->
                        <div class="flex flex-col gap-2">
                            <label for="amount" class="font-medium text-surface-700">Monto Total</label>
                            <InputNumber 
                                id="amount" 
                                v-model="form.amount" 
                                mode="currency" 
                                currency="MXN" 
                                locale="es-MX" 
                                class="w-full" 
                                inputClass="!font-bold !text-lg !text-surface-900"
                                placeholder="$0.00"
                                :invalid="!!form.errors.amount"
                            />
                            <small v-if="form.errors.amount" class="text-red-500">{{ form.errors.amount }}</small>
                        </div>

                        <!-- Fecha -->
                        <div class="flex flex-col gap-2">
                            <label for="date" class="font-medium text-surface-700">Fecha del Gasto</label>
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

                        <!-- Notas (Ancho completo) -->
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label for="notes" class="font-medium text-surface-700">Notas adicionales (opcional)</label>
                            <Textarea 
                                id="notes" 
                                v-model="form.notes" 
                                rows="4" 
                                class="w-full !resize-none" 
                                placeholder="Detalles extra, proveedor, etc..." 
                            />
                            <small v-if="form.errors.notes" class="text-red-500">{{ form.errors.notes }}</small>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>