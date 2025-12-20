<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    operation: Object,
    sales: Array,
    totalSales: Number,
    // Nueva prop que recibe el staff calculado desde WorkSchedule
    scheduledStaff: Array
});

// --- Helpers de Formato ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(value);
};

const parseDateSafe = (dateString) => {
    if (!dateString) return null;
    const cleanDate = dateString.substring(0, 10);
    const [year, month, day] = cleanDate.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (dateString) => {
    const date = parseDateSafe(dateString);
    if (!date) return '-';
    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(date);
};

const formatTime = (isoString) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatShiftTime = (timeStr) => {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const date = new Date();
    date.setHours(hours);
    date.setMinutes(minutes);
    
    return date.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

const getPaymentSeverity = (method) => {
    switch (method?.toLowerCase()) {
        case 'cash': return 'success';
        case 'card': return 'info';
        case 'transfer': return 'warn';
        default: return 'secondary';
    }
};

const getPaymentLabel = (method) => {
    const labels = {
        'cash': 'Efectivo',
        'card': 'Tarjeta',
        'transfer': 'Transferencia'
    };
    return labels[method] || method || 'Otro';
};

// --- Lógica de Corte de Caja ---
const showCloseDialog = ref(false);
const expandedRows = ref({});

const form = useForm({
    cash_end: null,
    notes: '',
});

const commissionPerTurn = computed(() => {
    if (!props.totalSales) return 0;
    const division = props.totalSales / 320;
    return Math.floor(division / 10) * 10;
});

const expectedCash = computed(() => {
    const start = parseFloat(props.operation.cash_start) || 0;
    const sales = parseFloat(props.totalSales) || 0;
    return start + sales;
});

const cashDifference = computed(() => {
    const counted = form.cash_end || 0; 
    return counted - expectedCash.value;
});

const submitClose = () => {
    form.post(route('sales.close', props.operation.id), {
        onSuccess: () => {
            showCloseDialog.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <AppLayout :title="`Detalle - ${operation.date.substring(0, 10)}`">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            
            <div class="flex justify-between items-center mb-6">
                <Link :href="route('sales.index')" class="text-gray-500 hover:text-indigo-600 font-medium flex items-center gap-2 transition-colors">
                    <i class="pi pi-arrow-left text-sm"></i>
                    Volver al historial
                </Link>

                <Button 
                    v-if="!operation.is_closed" 
                    label="Realizar corte de caja" 
                    icon="pi pi-lock" 
                    severity="danger" 
                    @click="showCloseDialog = true" 
                />
            </div>

            <!-- Header Resumen -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Info Principal -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Operación diaria #{{ operation.id }}</span>
                            <h1 class="text-3xl font-black text-gray-900 capitalize mt-1">{{ formatDate(operation.date) }}</h1>
                        </div>
                        <Tag :value="operation.is_closed ? 'Cerrado' : 'Turno abierto'" :severity="operation.is_closed ? 'success' : 'warn'" class="!text-xs !px-3 !py-1" rounded />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">Fondo inicial (Caja)</span>
                            <span class="text-xl font-bold text-gray-700">{{ formatCurrency(operation.cash_start) }}</span>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">Ventas totales</span>
                            <span class="text-xl font-bold text-indigo-600">{{ formatCurrency(totalSales) }}</span>
                        </div>
                        
                        <div v-if="operation.is_closed">
                            <span class="block text-sm text-gray-500 mb-1">Efectivo final (cierre)</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatCurrency(operation.cash_end) }}</span>
                        </div>
                    </div>

                    <div v-if="operation.notes" class="mt-6 bg-yellow-50 p-4 rounded-xl border border-yellow-100">
                        <span class="text-xs font-bold text-yellow-600 uppercase mb-1 block">Notas del cierre</span>
                        <p class="text-sm text-yellow-800 whitespace-pre-line font-mono">{{ operation.notes }}</p>
                    </div>
                </div>

                <!-- Personal y Comisión -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800">Personal</h3>
                        <Tag v-if="operation.is_closed" severity="success" class="!bg-green-100 !text-green-700">
                            <i class="pi pi-money-bill mr-1"></i>
                            {{ formatCurrency(commissionPerTurn) }} / turno
                        </Tag>
                    </div>

                    <!-- Usamos scheduledStaff en lugar de operation.staff -->
                    <div v-if="scheduledStaff && scheduledStaff.length > 0" class="space-y-4 flex-1 overflow-y-auto max-h-48 pr-2 custom-scrollbar">
                        <div v-for="employee in scheduledStaff" :key="employee.id" class="flex items-center gap-3">
                            <Avatar :label="(employee.full_name || employee.first_name || 'EM').substring(0,2).toUpperCase()" shape="circle" class="bg-indigo-50 text-indigo-600 font-bold" />
                            <div class="flex-1">
                                <p class="text-sm font-bold text-gray-900">{{ employee.full_name }}</p>
                                
                                <!-- Información del Turno (Usando current_shift) -->
                                <div v-if="employee.current_shift" class="flex items-center gap-2 mt-0.5">
                                     <div 
                                        class="w-2.5 h-2.5 rounded-full border border-gray-100 shadow-sm" 
                                        :style="{ backgroundColor: employee.current_shift.color }"
                                        :title="employee.current_shift.name"
                                     ></div>
                                     <span class="text-xs text-gray-600 font-medium">
                                        {{ employee.current_shift.name }}
                                        <span class="text-gray-400 font-normal ml-0.5">
                                            ({{ formatShiftTime(employee.current_shift.start_time) }} - {{ formatShiftTime(employee.current_shift.end_time) }})
                                        </span>
                                     </span>
                                </div>
                                <div v-else class="flex items-center gap-1.5 mt-0.5">
                                    <i class="pi pi-calendar-times text-xs text-gray-300"></i>
                                    <span class="text-xs text-gray-400 italic">Sin turno asignado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center h-full text-gray-400 py-4">
                        <i class="pi pi-calendar-minus text-2xl mb-2 opacity-50"></i>
                        <span class="text-sm text-center">Nadie programado en calendario para este día.</span>
                    </div>
                </div>
            </div>

            <!-- Tabla de Ventas (Igual) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-gray-900">Transacciones del día</h3>
                    <Tag :value="`${sales.length} Ventas`" severity="secondary" rounded />
                </div>
                
                <DataTable :value="sales" v-model:expandedRows="expandedRows" dataKey="id" 
                    paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]"
                    tableStyle="min-width: 50rem"
                    rowHover stripedRows>
                    
                    <Column expander style="width: 3rem" />

                    <Column field="created_at" header="Hora" sortable>
                        <template #body="slotProps">
                            <span class="font-medium text-gray-700">{{ formatTime(slotProps.data.created_at) }}</span>
                        </template>
                    </Column>

                    <Column field="user.name" header="Vendedor" sortable>
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-user text-gray-400 text-xs"></i>
                                <span class="text-sm text-gray-600">{{ slotProps.data.user?.name || 'Sistema' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="payment_method" header="Método" sortable>
                        <template #body="slotProps">
                            <Tag :value="getPaymentLabel(slotProps.data.payment_method)" :severity="getPaymentSeverity(slotProps.data.payment_method)" class="!text-xs" rounded />
                        </template>
                    </Column>

                    <Column field="total" header="Total" sortable>
                        <template #body="slotProps">
                            <span class="font-bold text-gray-900">{{ formatCurrency(slotProps.data.total) }}</span>
                        </template>
                    </Column>

                    <template #expansion="slotProps">
                        <div class="p-4 bg-gray-50 border-t border-b border-gray-200 shadow-inner">
                            <h5 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Productos vendidos</h5>
                            <DataTable :value="slotProps.data.details" size="small" class="p-datatable-sm bg-transparent">
                                <Column field="product.name" header="Producto">
                                    <template #body="detailProps">
                                        <span class="font-medium text-gray-700">{{ detailProps.data.product?.name || 'Producto eliminado' }}</span>
                                    </template>
                                </Column>
                                <Column field="quantity" header="Cant." style="width: 10%">
                                    <template #body="detailProps">
                                        <span class="bg-white border border-gray-200 px-2 py-0.5 rounded text-xs font-bold">{{ detailProps.data.quantity }}</span>
                                    </template>
                                </Column>
                                <Column field="unit_price" header="P. Unit." style="width: 15%">
                                    <template #body="detailProps">
                                        <span class="text-xs text-gray-500">{{ formatCurrency(detailProps.data.unit_price) }}</span>
                                    </template>
                                </Column>
                                <Column field="subtotal" header="Subtotal" style="width: 15%">
                                    <template #body="detailProps">
                                        <span class="font-bold text-gray-700 text-sm">{{ formatCurrency(detailProps.data.subtotal) }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </template>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">No hay ventas registradas en este día.</div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- DIALOGO DE CORTE DE CAJA (Igual) -->
        <Dialog v-model:visible="showCloseDialog" modal header="Realizar corte de caja" :style="{ width: '600px' }" class="p-fluid">
            <div class="space-y-6 pt-2">
                <Message severity="info" :closable="false" class="mb-4">
                    Al confirmar, se cerrará el turno y se registrarán las diferencias encontradas.
                </Message>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                    <div class="flex justify-between items-center text-sm border-b border-gray-200 pb-3">
                        <span class="text-gray-500">Fondo inicial:</span>
                        <span class="font-semibold text-gray-700">{{ formatCurrency(operation.cash_start) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-gray-200 pb-3">
                        <span class="text-gray-500">Ventas totales:</span>
                        <span class="font-semibold text-gray-700">{{ formatCurrency(totalSales) }}</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <div class="bg-white p-3 rounded-lg border border-gray-200 text-center">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide block mb-1">Debería haber</span>
                            <span class="text-lg font-bold text-gray-800">{{ formatCurrency(expectedCash) }}</span>
                        </div>
                        
                        <div class="p-3 rounded-lg border text-center transition-colors duration-300"
                            :class="{
                                'bg-green-50 border-green-200': cashDifference >= 0,
                                'bg-red-50 border-red-200': cashDifference < 0
                            }">
                            <span class="text-xs font-bold uppercase tracking-wide block mb-1"
                                :class="cashDifference >= 0 ? 'text-green-600' : 'text-red-600'">
                                Diferencia
                            </span>
                            <span class="text-lg font-black"
                                :class="cashDifference >= 0 ? 'text-green-700' : 'text-red-700'">
                                {{ cashDifference > 0 ? '+' : '' }}{{ formatCurrency(cashDifference) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="field">
                        <label for="cash_end" class="font-bold text-gray-700 block mb-2">Efectivo contado en caja (real)</label>
                        <InputNumber 
                            id="cash_end" 
                            v-model="form.cash_end" 
                            mode="currency" 
                            currency="MXN" 
                            locale="es-MX" 
                            placeholder="$0.00" 
                            class="w-full" 
                            :class="{ 'p-invalid': form.errors.cash_end }"
                            autofocus
                        />
                        <small v-if="form.errors.cash_end" class="p-error block mt-1">{{ form.errors.cash_end }}</small>
                    </div>

                    <div class="field">
                        <label for="notes" class="font-bold text-gray-700 block mb-2">Notas / Justificación</label>
                        <Textarea 
                            id="notes" 
                            v-model="form.notes" 
                            rows="3" 
                            placeholder="Ej. Se tomó dinero para comprar hielo, sobró cambio..." 
                            class="w-full"
                        />
                    </div>

                    <div class="flex items-center gap-2 bg-indigo-50 p-3 rounded-lg border border-indigo-100">
                        <i class="pi pi-info-circle text-indigo-500"></i>
                        <span class="text-sm text-indigo-700 font-medium">
                            Comisión calculada para empleados: <strong>{{ formatCurrency(commissionPerTurn) }}</strong> / turno.
                        </span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 mt-4">
                    <Button label="Cancelar" icon="pi pi-times" text @click="showCloseDialog = false" class="!text-gray-500" />
                    <Button 
                        label="Confirmar corte" 
                        icon="pi pi-check" 
                        severity="danger" 
                        @click="submitClose" 
                        :loading="form.processing"
                    />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>