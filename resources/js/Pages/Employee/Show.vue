<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import Image from 'primevue/image';

const props = defineProps({
    employee: Object,
    vacation_stats: Object,
    severance_data: Object, // Solo vendrá si es admin (ID 1)
});

const confirm = useConfirm();
const toast = useToast();

// --- Helpers de Formato ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

// --- Lógica de Baja (Terminación) ---
const showTerminateDialog = ref(false);
const terminateForm = useForm({
    termination_date: new Date(),
    reason: 'resignation', // default
    notes: '',
});

const terminationReasons = [
    { label: 'Renuncia Voluntaria', value: 'resignation' },
    { label: 'Despido Justificado', value: 'justified' },
    { label: 'Despido Injustificado', value: 'unjustified' },
];

// Cálculo reactivo del monto estimado a pagar según la razón seleccionada
const estimatedPayout = computed(() => {
    if (!props.severance_data) return 0;
    
    const reason = terminateForm.reason;
    const baseFiniquito = props.severance_data.concepts.total_finiquito;
    
    if (reason === 'unjustified') {
        return props.severance_data.compensation_unjustified.total_liquidation;
    }
    // Para renuncia o despido justificado, solo toca finiquito (partes proporcionales)
    return baseFiniquito;
});

const confirmTermination = () => {
    terminateForm.transform((data) => ({
        ...data,
        termination_date: data.termination_date.toISOString().split('T')[0],
    })).put(route('employees.terminate', props.employee.id), {
        onSuccess: () => {
            showTerminateDialog.value = false;
            toast.add({ severity: 'success', summary: 'Baja Procesada', detail: 'El empleado ha sido dado de baja.', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Verifica los datos de la baja.', life: 3000 });
        }
    });
};

// --- Lógica de Reactivación ---
const reactivateEmployee = () => {
    confirm.require({
        message: '¿Deseas reactivar a este empleado?',
        header: 'Reactivar',
        icon: 'pi pi-refresh',
        acceptClass: 'p-button-success',
        accept: () => {
            router.put(route('employees.update', props.employee.id), {
                ...props.employee, // Enviamos datos actuales
                is_active: true,
                termination_date: null,
                termination_reason: null,
                _method: 'PUT'
            }, {
                onSuccess: () => toast.add({ severity: 'success', summary: 'Reactivado', detail: 'Empleado activo nuevamente', life: 3000 })
            });
        }
    });
};
</script>

<template>
    <AppLayout :title="employee.full_name">
        <div class="w-full max-w-6xl mx-auto flex flex-col gap-8 pb-10">

            <!-- Encabezado Sticky -->
            <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                <div class="flex items-center gap-4">
                    <Link :href="route('employees.index')">
                        <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900 truncate max-w-[200px] md:max-w-md">
                                {{ employee.full_name }}
                            </h1>
                            <Tag 
                                :value="employee.is_active ? 'Activo' : 'Baja'" 
                                :severity="employee.is_active ? 'success' : 'danger'" 
                                rounded
                            />
                        </div>
                        <p class="text-surface-500 text-sm mt-1 flex items-center gap-2">
                            <i class="pi pi-id-card text-xs"></i> 
                            <span>ID: {{ employee.id }}</span>
                            <span class="text-surface-300">|</span>
                            <span>{{ employee.email }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('employees.edit', employee.id)">
                        <Button 
                            label="Editar" 
                            icon="pi pi-pencil" 
                            class="!bg-surface-900 !border-surface-900 hover:!bg-surface-800 font-bold shadow-lg"
                            rounded 
                        />
                    </Link>
                </div>
            </div>

            <!-- Contenido Principal Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- COLUMNA IZQUIERDA: Perfil y Datos -->
                <div class="lg:col-span-1 flex flex-col gap-6">
                    
                    <!-- Tarjeta Foto -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-4 flex flex-col items-center">
                        <div class="w-40 h-40 rounded-full overflow-hidden border-4 border-surface-50 shadow-inner mb-4 relative group">
                            <Image 
                                v-if="employee.profile_photo_url" 
                                :src="employee.profile_photo_url" 
                                :alt="employee.full_name" 
                                preview
                                imageClass="w-full h-full object-cover"
                                class="w-full h-full"
                            />
                            <div v-else class="w-full h-full bg-orange-50 flex items-center justify-center text-orange-300">
                                <i class="pi pi-user text-6xl"></i>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-surface-900 text-center">{{ employee.full_name }}</h2>
                        <span class="text-sm text-surface-500">Contratado: {{ formatDate(employee.hired_at) }}</span>
                    </div>

                    <!-- Datos Personales -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-4">
                        <h3 class="text-xs font-bold text-surface-400 uppercase tracking-wider">Información Personal</h3>
                        
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-500"><i class="pi pi-phone"></i></div>
                            <div>
                                <p class="text-xs text-surface-500">Teléfono</p>
                                <p class="text-sm font-medium text-surface-900">{{ employee.phone }}</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-500"><i class="pi pi-gift"></i></div>
                            <div>
                                <p class="text-xs text-surface-500">Cumpleaños</p>
                                <p class="text-sm font-medium text-surface-900">{{ formatDate(employee.birth_date) }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="pi pi-money-bill"></i></div>
                            <div>
                                <p class="text-xs text-surface-500">Sueldo Base</p>
                                <p class="text-sm font-medium text-surface-900">{{ formatCurrency(employee.base_salary) }}</p>
                            </div>
                        </div>

                        <div class="border-t border-surface-100 my-1"></div>

                        <div>
                            <p class="text-xs text-surface-500 mb-1">Dirección</p>
                            <p class="text-sm text-surface-700 leading-relaxed">{{ employee.address }}</p>
                        </div>
                    </div>

                    <!-- Botón Reactivar (Si está inactivo) -->
                    <div v-if="!employee.is_active" class="bg-red-50 rounded-2xl border border-red-100 p-4 text-center">
                        <p class="text-red-800 font-medium mb-2">Este empleado está dado de baja.</p>
                        <p class="text-xs text-red-600 mb-3">Fecha: {{ formatDate(employee.termination_date) }}</p>
                        <Button 
                            label="Reactivar Empleado" 
                            icon="pi pi-refresh" 
                            severity="success" 
                            outlined 
                            class="w-full"
                            @click="reactivateEmployee"
                        />
                    </div>
                </div>

                <!-- COLUMNA DERECHA: Vacaciones y Gestión -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    
                    <!-- Tarjeta de Vacaciones -->
                    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 overflow-hidden">
                        <div class="p-6 border-b border-surface-100 bg-gradient-to-r from-orange-50 to-white flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold text-surface-900 flex items-center gap-2">
                                    <i class="pi pi-calendar text-orange-500"></i> Vacaciones
                                </h3>
                                <p class="text-xs text-surface-500 mt-1">Antigüedad: {{ vacation_stats.years_service }} años</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-3xl font-extrabold text-orange-600">{{ Number(vacation_stats.available_days).toFixed(2) }}</span>
                                <span class="text-xs font-bold text-orange-400 uppercase tracking-wider">Días Disponibles</span>
                            </div>
                        </div>

                        <div class="p-4">
                            <DataTable :value="employee.vacation_logs" paginator :rows="5" size="small" class="p-datatable-sm">
                                <template #empty>Sin historial de vacaciones.</template>
                                <Column field="created_at" header="Fecha">
                                    <template #body="slotProps">
                                        <span class="text-xs text-surface-500">{{ formatDate(slotProps.data.created_at) }}</span>
                                    </template>
                                </Column>
                                <Column field="type" header="Tipo">
                                    <template #body="slotProps">
                                        <Tag 
                                            :value="slotProps.data.type === 'usage' ? 'Uso' : (slotProps.data.type === 'accrual' ? 'Acumulación' : 'Ajuste')" 
                                            :severity="slotProps.data.type === 'usage' ? 'warning' : (slotProps.data.type === 'accrual' ? 'success' : 'info')"
                                            class="!text-[10px] !px-2"
                                        />
                                    </template>
                                </Column>
                                <Column field="days" header="Días" class="text-right">
                                    <template #body="slotProps">
                                        <span :class="slotProps.data.days > 0 ? 'text-green-600' : 'text-red-600'" class="font-bold text-xs">
                                            {{ slotProps.data.days > 0 ? '+' : '' }}{{ Number(slotProps.data.days).toFixed(2) }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="description" header="Detalle" class="w-1/3">
                                    <template #body="slotProps">
                                        <span class="text-xs text-surface-600 truncate block max-w-[150px]" :title="slotProps.data.description">
                                            {{ slotProps.data.description }}
                                        </span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>

                    <!-- Módulo de Baja (Solo visible para Admin y si está activo) -->
                    <div v-if="employee.is_active && severance_data" class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-surface-900 flex items-center gap-2">
                                <i class="pi pi-user-minus text-red-500"></i> Zona de Baja
                            </h3>
                            <Button 
                                label="Procesar Baja" 
                                severity="danger" 
                                icon="pi pi-exclamation-triangle" 
                                outlined
                                @click="showTerminateDialog = true"
                            />
                        </div>
                        <p class="text-sm text-surface-500">
                            Utiliza esta sección para terminar la relación laboral. El sistema calculará automáticamente el finiquito o liquidación correspondiente según la Ley Federal del Trabajo.
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <!-- DIALOGO DE TERMINACIÓN (BAJA) -->
        <Dialog 
            v-model:visible="showTerminateDialog" 
            modal 
            header="Procesar Baja de Empleado" 
            :style="{ width: '40rem' }"
            :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
        >
            <div class="flex flex-col gap-6">
                
                <!-- Alerta Informativa -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
                    <i class="pi pi-info-circle text-blue-500 text-xl mt-1"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-bold">Datos para el cálculo:</p>
                        <ul class="list-disc list-inside mt-1 space-y-1 text-blue-700">
                            <li>Antigüedad: {{ severance_data.years_worked }} años</li>
                            <li>Salario Diario: {{ formatCurrency(severance_data.daily_salary) }}</li>
                            <li>Vacaciones Pendientes: {{ severance_data.concepts.vacations_proportional > 0 ? 'Sí' : 'No' }}</li>
                        </ul>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-bold text-surface-700 text-sm">Fecha de Baja</label>
                        <DatePicker v-model="terminateForm.termination_date" showIcon dateFormat="dd/mm/yy" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-bold text-surface-700 text-sm">Motivo de Baja</label>
                        <Select 
                            v-model="terminateForm.reason" 
                            :options="terminationReasons" 
                            optionLabel="label" 
                            optionValue="value" 
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col gap-2 md:col-span-2">
                        <label class="font-bold text-surface-700 text-sm">Notas / Justificación</label>
                        <Textarea v-model="terminateForm.notes" rows="3" class="w-full" placeholder="Detalles de la baja..." />
                    </div>
                </div>

                <!-- Simulación de Pagos -->
                <div class="border-t border-surface-200 pt-4">
                    <h4 class="text-sm font-bold text-surface-900 mb-3 uppercase tracking-wider">Desglose Estimado</h4>
                    
                    <!-- Conceptos de Finiquito (Siempre aplican) -->
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-surface-600">Aguinaldo Proporcional</span>
                            <span class="font-medium">{{ formatCurrency(severance_data.concepts.aguinaldo_proportional) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-surface-600">Vacaciones Proporcionales</span>
                            <span class="font-medium">{{ formatCurrency(severance_data.concepts.vacations_proportional) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-surface-600">Prima Vacacional</span>
                            <span class="font-medium">{{ formatCurrency(severance_data.concepts.vacation_premium) }}</span>
                        </div>
                    </div>

                    <!-- Indemnización (Solo si es injustificado) -->
                    <div v-if="terminateForm.reason === 'unjustified'" class="bg-red-50 p-3 rounded-lg border border-red-100 space-y-2 mb-4 animate-fade-in">
                        <p class="text-xs font-bold text-red-600 uppercase mb-2">Indemnización Constitucional</p>
                        <div class="flex justify-between text-sm">
                            <span class="text-red-700">3 Meses de Salario</span>
                            <span class="font-bold text-red-700">{{ formatCurrency(severance_data.compensation_unjustified.months_3) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-red-700">20 Días por Año</span>
                            <span class="font-bold text-red-700">{{ formatCurrency(severance_data.compensation_unjustified.days_20_per_year) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-red-700">Prima de Antigüedad</span>
                            <span class="font-bold text-red-700">{{ formatCurrency(severance_data.compensation_unjustified.seniority_premium) }}</span>
                        </div>
                    </div>

                    <!-- Total Final -->
                    <div class="flex justify-between items-center bg-surface-900 text-white p-4 rounded-xl shadow-lg">
                        <span class="font-bold">Total a Pagar</span>
                        <span class="text-2xl font-extrabold">{{ formatCurrency(estimatedPayout) }}</span>
                    </div>
                    <p class="text-xs text-center text-surface-400 mt-2">
                        * Cálculo estimado según la LFT vigente. No incluye deducciones de impuestos (ISR).
                    </p>
                </div>

            </div>

            <template #footer>
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" icon="pi pi-times" text @click="showTerminateDialog = false" severity="secondary" />
                    <Button 
                        label="Confirmar Baja" 
                        icon="pi pi-check" 
                        @click="confirmTermination" 
                        severity="danger" 
                        :loading="terminateForm.processing"
                    />
                </div>
            </template>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
:deep(.p-inputtext), :deep(.p-textarea) {
    border-radius: 0.75rem;
}
.animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>