<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// Inicializamos con un item vacío por defecto
const form = useForm({
    items: [
        {
            id: Date.now(), // ID temporal para keys de Vue
            concept: '',
            amount: null,
            date: new Date(), 
            notes: '',
        }
    ]
});

// Función para agregar nueva fila
const addItem = () => {
    // Tomamos la fecha del último item agregado para UX (usualmente registras varios del mismo día)
    const lastDate = form.items.length > 0 
        ? form.items[form.items.length - 1].date 
        : new Date();

    form.items.push({
        id: Date.now() + Math.random(),
        concept: '',
        amount: null,
        date: lastDate, // Copiamos la fecha anterior
        notes: '',
    });
};

// Función para eliminar fila
const removeItem = (index) => {
    if (form.items.length === 1) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Debe haber al menos un registro.', life: 2000 });
        return;
    }
    form.items.splice(index, 1);
};

// Cálculo del total en tiempo real
const totalAmount = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.amount || 0), 0);
});

const submit = () => {
    // Transformamos las fechas de cada item
    form.transform((data) => ({
        items: data.items.map(item => ({
            ...item,
            date: item.date ? item.date.toISOString().split('T')[0] : null,
        }))
    })).post(route('expenses.store'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Gastos registrados correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Revisa que todos los campos obligatorios estén llenos.', life: 3000 });
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};
</script>

<template>
    <AppLayout title="Registrar Gastos">
        <div class="w-full max-w-6xl mx-auto flex gap-8 items-start relative">
            
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                
                <!-- Encabezado Sticky -->
                <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex flex-col md:flex-row md:items-center justify-between transition-all duration-300 gap-4">
                    <div class="flex items-center gap-4">
                        <Link :href="route('expenses.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Registrar gastos</h1>
                            <p class="text-surface-500 text-sm mt-1">Captura múltiple de salidas de dinero.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 self-end md:self-auto">
                        <!-- Totalizador Flotante -->
                        <div class="bg-white border border-surface-200 px-4 py-2 rounded-xl shadow-sm text-right">
                            <span class="text-xs text-surface-500 font-bold uppercase block">Total a Registrar</span>
                            <span class="text-xl font-black text-orange-600">{{ formatCurrency(totalAmount) }}</span>
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
                                label="Guardar Todo" 
                                icon="pi pi-check" 
                                @click="submit" 
                                :loading="form.processing"
                                class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-bold shadow-lg shadow-orange-200/50"
                                rounded
                            />
                        </div>
                    </div>
                </div>

                <!-- Lista de Formularios -->
                <div class="flex flex-col gap-4 pb-20">
                    <div 
                        v-for="(item, index) in form.items" 
                        :key="item.id"
                        class="bg-white rounded-3xl shadow-sm border border-surface-200 p-5 transition-all hover:shadow-md group relative"
                    >
                        <!-- Número de fila y Botón Eliminar -->
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <span class="bg-surface-100 text-surface-600 font-bold text-xs px-2 py-1 rounded-md">#{{ index + 1 }}</span>
                                <span class="text-sm font-semibold text-surface-400">Detalle del gasto</span>
                            </div>
                            <Button 
                                icon="pi pi-trash" 
                                text 
                                rounded 
                                severity="danger" 
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                v-tooltip.left="'Quitar esta fila'"
                                class="!w-8 !h-8"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            
                            <!-- Concepto -->
                            <div class="md:col-span-5 flex flex-col gap-2">
                                <label :for="'concept-'+index" class="font-medium text-surface-700 text-sm">Concepto <span class="text-red-500">*</span></label>
                                <InputText 
                                    :id="'concept-'+index" 
                                    v-model="item.concept" 
                                    class="w-full" 
                                    placeholder="Ej. Compra de insumos" 
                                    :invalid="!!form.errors[`items.${index}.concept`]"
                                />
                                <small v-if="form.errors[`items.${index}.concept`]" class="text-red-500">{{ form.errors[`items.${index}.concept`] }}</small>
                            </div>

                            <!-- Monto -->
                            <div class="md:col-span-3 flex flex-col gap-2">
                                <label :for="'amount-'+index" class="font-medium text-surface-700 text-sm">Monto <span class="text-red-500">*</span></label>
                                <InputNumber 
                                    :id="'amount-'+index" 
                                    v-model="item.amount" 
                                    mode="currency" 
                                    currency="MXN" 
                                    locale="es-MX" 
                                    class="w-full" 
                                    inputClass="!font-bold !text-surface-900"
                                    placeholder="$0.00"
                                    :min="0"
                                    :invalid="!!form.errors[`items.${index}.amount`]"
                                />
                                <small v-if="form.errors[`items.${index}.amount`]" class="text-red-500">{{ form.errors[`items.${index}.amount`] }}</small>
                            </div>

                            <!-- Fecha -->
                            <div class="md:col-span-4 flex flex-col gap-2">
                                <label :for="'date-'+index" class="font-medium text-surface-700 text-sm">Fecha <span class="text-red-500">*</span></label>
                                <DatePicker 
                                    :id="'date-'+index" 
                                    v-model="item.date" 
                                    showIcon 
                                    dateFormat="dd/mm/yy"
                                    class="w-full"
                                    :invalid="!!form.errors[`items.${index}.date`]"
                                />
                                <small v-if="form.errors[`items.${index}.date`]" class="text-red-500">{{ form.errors[`items.${index}.date`] }}</small>
                            </div>

                            <!-- Notas (Fila completa abajo) -->
                            <div class="md:col-span-12 flex flex-col gap-2">
                                <label :for="'notes-'+index" class="font-medium text-surface-700 text-sm">Notas (Opcional)</label>
                                <InputText 
                                    :id="'notes-'+index" 
                                    v-model="item.notes" 
                                    class="w-full" 
                                    placeholder="Detalles adicionales..." 
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Botón Agregar Más -->
                    <button 
                        @click="addItem"
                        type="button"
                        class="w-full py-4 border-2 border-dashed border-surface-300 rounded-3xl text-surface-500 font-bold hover:border-orange-400 hover:text-orange-600 hover:bg-orange-50 transition-all flex items-center justify-center gap-2 group"
                    >
                        <div class="bg-surface-200 text-surface-500 rounded-full p-1 group-hover:bg-orange-200 group-hover:text-orange-700 transition-colors">
                            <i class="pi pi-plus"></i>
                        </div>
                        Agregar otra línea
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-inputtext), :deep(.p-inputnumber-input) {
    border-radius: 0.75rem;
    padding: 0.6rem 1rem;
}
:deep(.p-datepicker) {
    border-radius: 0.75rem;
}
</style>