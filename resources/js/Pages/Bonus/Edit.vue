<script setup>
import { ref, watch, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    bonus: Object,
});

const toast = useToast();

const form = useForm({
    name: props.bonus.name,
    description: props.bonus.description,
    amount: parseFloat(props.bonus.amount),
    type: props.bonus.type,
    is_active: Boolean(props.bonus.is_active),
    rule_config: props.bonus.rule_config, // Cargar reglas existentes
});

// Inicializar estado del switch basado en si hay reglas guardadas
const hasRules = ref(!!props.bonus.rule_config);

const types = [
    { label: 'Monto Fijo ($)', value: 'fixed' },
    { label: 'Porcentaje (%)', value: 'percentage' }
];

const concepts = [
    { label: 'Minutos de Retardo', value: 'late_minutes' },
    { label: 'Minutos Extra Trabajados', value: 'extra_minutes' },
    { label: 'Faltas Injustificadas', value: 'unjustified_absences' },
    { label: 'Días de Asistencia', value: 'attendance' },
];

const operators = [
    { label: 'Menor o igual que (<=)', value: '<=' },
    { label: 'Mayor o igual que (>=)', value: '>=' },
    { label: 'Igual a (=)', value: '=' },
    { label: 'Mayor que (>)', value: '>' },
    { label: 'Menor que (<)', value: '<' },
];

const scopes = [
    { label: 'Por Día (Evaluar diario)', value: 'daily' },
    { label: 'Total del Periodo', value: 'period_total' },
    { label: 'Acumulado del Periodo', value: 'period_accumulated' },
];

// Actualizado: Agregada opción 'per_day_worked'
const behaviors = [
    { label: 'Otorgar Monto Único', value: 'fixed_amount' },
    { label: 'Pagar por Unidad (ej. por minuto)', value: 'pay_per_unit' },
    { label: 'Pagar por Día Trabajado', value: 'per_day_worked' },
];

watch(hasRules, (val) => {
    if (val && !form.rule_config) {
        // Valores por defecto al activar
        form.rule_config = {
            concept: 'late_minutes',
            operator: '<=',
            value: 0,
            scope: 'period_accumulated',
            behavior: 'fixed_amount'
        };
    } else if (!val) {
        form.rule_config = null;
    }
});

const submit = () => {
    if (!hasRules.value) {
        form.rule_config = null;
    }

    form.put(route('bonuses.update', props.bonus.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Bono actualizado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};

// Computed para el texto dinámico del label de monto (Igual que en Create)
const amountLabel = computed(() => {
    if (!hasRules.value || !form.rule_config) return 'Valor del Bono';
    
    switch (form.rule_config.behavior) {
        case 'pay_per_unit': return 'Pago por Unidad (ej. cada minuto)';
        case 'per_day_worked': return 'Monto por Día Trabajado';
        default: return 'Monto Fijo Total';
    }
});
</script>

<template>
    <AppLayout title="Editar Bono">
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
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Editar bono</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Modifica las reglas del incentivo.</p>
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
                            label="Guardar cambios" 
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
                        
                        <!-- Panel de Estado y Tipo -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-5">
                            <span class="text-sm font-semibold text-surface-700">Configuración General</span>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-surface-900">Estado activo</span>
                                    <span class="text-xs text-surface-500">Disponible para cálculo</span>
                                </div>
                                <ToggleSwitch v-model="form.is_active" />
                            </div>
                            
                            <hr class="border-surface-100" />

                            <div class="flex flex-col gap-2">
                                <label class="font-medium text-surface-700 text-sm">Tipo de valor</label>
                                <SelectButton 
                                    v-model="form.type" 
                                    :options="types" 
                                    optionLabel="label" 
                                    optionValue="value" 
                                    class="w-full"
                                    :allowEmpty="false"
                                />
                            </div>
                        </div>

                        <!-- Panel de Activación de Reglas -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-4 transition-all duration-300" :class="hasRules ? 'ring-2 ring-orange-500 ring-offset-2' : ''">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-surface-900 flex items-center gap-2">
                                        <i class="pi pi-list-check" :class="hasRules ? 'text-orange-500' : 'text-surface-400'"></i>
                                        Aplicar reglas
                                    </span>
                                    <span class="text-xs text-surface-500 mt-1">
                                        {{ hasRules ? 'Condiciones activas' : 'Otorgación directa' }}
                                    </span>
                                </div>
                                <ToggleSwitch v-model="hasRules" />
                            </div>
                            
                            <div class="text-xs text-surface-500 bg-surface-50 p-3 rounded-lg border border-surface-100">
                                <p v-if="hasRules">Se evaluarán las condiciones definidas abajo para calcular el bono en la nómina.</p>
                                <p v-else>Este bono se sumará automáticamente a la nómina de los empleados asignados, sin condiciones (Fijo).</p>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Detalles -->
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <!-- Datos Básicos -->
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
                                        :invalid="!!form.errors.name"
                                    />
                                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="amount" class="font-medium text-surface-700 transition-all duration-300">
                                        {{ amountLabel }}
                                    </label>
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

                        <!-- Configuración de Reglas (Condicional) -->
                        <div v-if="hasRules && form.rule_config" class="bg-surface-50 rounded-3xl shadow-inner border border-surface-200 p-6 animate-fade-in">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-cog text-gray-500"></i> Lógica de Asignación
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Concepto a Evaluar -->
                                <div class="flex flex-col gap-2 md:col-span-2">
                                    <label class="font-bold text-surface-700 text-xs uppercase">Si el concepto...</label>
                                    <Select 
                                        v-model="form.rule_config.concept" 
                                        :options="concepts" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Selecciona una métrica"
                                        class="w-full"
                                    />
                                </div>

                                <!-- Operador -->
                                <div class="flex flex-col gap-2">
                                    <label class="font-bold text-surface-700 text-xs uppercase">Es...</label>
                                    <Select 
                                        v-model="form.rule_config.operator" 
                                        :options="operators" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        placeholder="Operador"
                                        class="w-full"
                                    />
                                    <small v-if="form.errors['rule_config.operator']" class="text-red-500">{{ form.errors['rule_config.operator'] }}</small>
                                </div>

                                <!-- Valor Objetivo -->
                                <div class="flex flex-col gap-2">
                                    <label class="font-bold text-surface-700 text-xs uppercase">Que este valor</label>
                                    <InputNumber 
                                        v-model="form.rule_config.value" 
                                        showButtons 
                                        :min="0"
                                        placeholder="0"
                                        class="w-full"
                                        :invalid="!!form.errors['rule_config.value']"
                                    />
                                    <small v-if="form.errors['rule_config.value']" class="text-red-500">{{ form.errors['rule_config.value'] }}</small>
                                </div>

                                <div class="md:col-span-2 border-t border-surface-200 my-2"></div>

                                <!-- Alcance -->
                                <div class="flex flex-col gap-2">
                                    <label class="font-bold text-surface-700 text-xs uppercase">Evaluación</label>
                                    <Select 
                                        v-model="form.rule_config.scope" 
                                        :options="scopes" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        class="w-full"
                                    />
                                </div>

                                <!-- Comportamiento -->
                                <div class="flex flex-col gap-2">
                                    <label class="font-bold text-surface-700 text-xs uppercase">Entonces pagar</label>
                                    <Select 
                                        v-model="form.rule_config.behavior" 
                                        :options="behaviors" 
                                        optionLabel="label" 
                                        optionValue="value" 
                                        class="w-full"
                                    />
                                </div>

                                <!-- Resumen de la Regla (Helper visual) -->
                                <div class="md:col-span-2 bg-blue-50 text-blue-800 p-4 rounded-xl text-sm border border-blue-100 flex gap-3 items-start">
                                    <i class="pi pi-info-circle mt-1"></i>
                                    <div>
                                        <span class="font-bold block mb-1">Resumen de la regla:</span>
                                        <p>
                                            Se pagará 
                                            <strong class="mr-1">${{ form.amount }}</strong>
                                            <strong class="text-blue-900" v-if="form.rule_config.behavior === 'fixed_amount'">en total (fijo)</strong>
                                            <strong class="text-blue-900" v-else-if="form.rule_config.behavior === 'pay_per_unit'">por cada unidad</strong>
                                            <strong class="text-blue-900" v-else-if="form.rule_config.behavior === 'per_day_worked'">por cada día trabajado</strong>
                                            
                                            cuando 
                                            <strong>{{ concepts.find(c => c.value === form.rule_config.concept)?.label }}</strong>
                                            sea 
                                            <strong>{{ operators.find(o => o.value === form.rule_config.operator)?.label }}</strong> 
                                            <strong>{{ form.rule_config.value }}</strong>,
                                            evaluado de forma 
                                            <strong>{{ scopes.find(s => s.value === form.rule_config.scope)?.label }}</strong>.
                                        </p>
                                    </div>
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
:deep(.p-select) {
    border-radius: 0.75rem;
    padding: 0.25rem 1rem;
}
:deep(.p-select .p-select-label) {
    padding: 0.5rem 0.5rem;
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
.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>