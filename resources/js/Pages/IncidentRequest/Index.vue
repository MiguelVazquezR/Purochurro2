<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import { useToast } from "primevue/usetoast";
import Toast from 'primevue/toast';
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    requests: Object,
    canApprove: Boolean, // True = Admin, False = Empleado
    incidentTypes: Array, // Lista de valores (strings)
});

const toast = useToast();

// --- ESTADOS PARA EL TOUR ---
const isLoadingTour = ref(false);
const isTourActive = ref(false);

// --- Mapeo de Etiquetas (Frontend) ---
const incidentLabels = {
    'asistencia': 'Asistencia normal',
    'falta_injustificada': 'Falta injustificada',
    'falta_justificada': 'Falta justificada',
    'permiso_con_goce': 'Permiso con goce de sueldo',
    'permiso_sin_goce': 'Permiso sin goce de sueldo',
    'incapacidad_general': 'Incapacidad general (IMSS)',
    'incapacidad_trabajo': 'Incapacidad riesgo Trabajo',
    'vacaciones': 'Vacaciones',
    'dia_festivo': 'Día festivo',
    'descanso': 'Descanso',
    'no_laboraba': 'No laboraba'
};

const getIncidentLabel = (value) => incidentLabels[value] || value;

// Solo mostramos opciones relevantes para solicitar
const requestOptions = props.incidentTypes
    .filter(t => ['vacaciones', 'permiso_sin_goce', 'permiso_con_goce', 'incapacidad_general', 'incapacidad_trabajo'].includes(t))
    .map(t => ({ label: getIncidentLabel(t), value: t }));

// --- Helpers ---
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getStatusSeverity = (status) => {
    switch (status) {
        case 'approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'warn';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'approved': return 'Aprobado';
        case 'rejected': return 'Rechazado';
        case 'pending': return 'Pendiente';
        default: return status;
    }
};

// --- CREACIÓN (Solo Empleados) ---
const showCreateModal = ref(false);
const createForm = useForm({
    incident_type: null,
    start_date: null,
    end_date: null,
    employee_reason: '',
});

const calculatedDays = computed(() => {
    if (!createForm.start_date || !createForm.end_date) return 0;
    const start = new Date(createForm.start_date);
    const end = new Date(createForm.end_date);
    if (end < start) return 0;
    const diffTime = Math.abs(end - start);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 para incluir el día final
});

const submitCreate = () => {
    createForm.transform((data) => ({
        ...data,
        start_date: data.start_date ? new Date(data.start_date).toISOString().split('T')[0] : null,
        end_date: data.end_date ? new Date(data.end_date).toISOString().split('T')[0] : null,
    })).post(route('incident-requests.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            toast.add({ severity: 'success', summary: 'Enviado', detail: 'Tu solicitud ha sido registrada.', life: 3000 });
        }
    });
};

// --- APROBACIÓN / RECHAZO (Solo Admin) ---
const showRejectModal = ref(false);
const selectedRequest = ref(null);
const rejectForm = useForm({
    status: 'rejected',
    admin_response: '',
});

const approveRequest = (request) => {
    router.visit(route('incident-requests.update-status', request.id), {
        method: 'patch',
        data: { status: 'approved' },
        preserveScroll: true,
        onSuccess: () => toast.add({ severity: 'success', summary: 'Aprobado', detail: 'Solicitud aprobada y aplicada.', life: 3000 }),
    });
};

const openRejectModal = (request) => {
    selectedRequest.value = request;
    rejectForm.reset();
    showRejectModal.value = true;
};

const confirmReject = () => {
    rejectForm.patch(route('incident-requests.update-status', selectedRequest.value.id), {
        onSuccess: () => {
            showRejectModal.value = false;
            selectedRequest.value = null;
            toast.add({ severity: 'info', summary: 'Rechazado', detail: 'La solicitud fue rechazada.', life: 3000 });
        }
    });
};

// --- LÓGICA DEL TUTORIAL (ONBOARDING) ---

const blockInteraction = (e) => {
    if (!isTourActive.value) return;
    if (e.target.closest && e.target.closest('.driver-popover')) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
};

const enableBlocking = () => {
    isTourActive.value = true;
    window.addEventListener('click', blockInteraction, true);
    window.addEventListener('mousedown', blockInteraction, true);
    window.addEventListener('touchstart', blockInteraction, true);
    window.addEventListener('keydown', blockInteraction, true);
};

const disableBlocking = () => {
    isTourActive.value = false;
    window.removeEventListener('click', blockInteraction, true);
    window.removeEventListener('mousedown', blockInteraction, true);
    window.removeEventListener('touchstart', blockInteraction, true);
    window.removeEventListener('keydown', blockInteraction, true);
};

const startTour = () => {
    enableBlocking();

    // Definimos los pasos base
    const steps = [
        { 
            element: '#tour-incidents-header', 
            popover: { 
                title: props.canApprove ? 'Gestión de Permisos' : 'Mis Permisos e Incidencias', 
                description: props.canApprove 
                    ? 'Aquí podrás visualizar, aprobar o rechazar las solicitudes de permisos y vacaciones de tus colaboradores.' 
                    : 'Desde aquí puedes gestionar tus vacaciones, permisos especiales o reportar incapacidades.',
                side: "bottom",
                align: 'start'
            } 
        }
    ];

    // Paso específico para empleados: Botón de crear
    if (!props.canApprove) {
        steps.push({
            element: '#tour-create-btn',
            popover: {
                title: 'Nueva Solicitud',
                description: 'Haz clic aquí para abrir el formulario y solicitar un nuevo permiso (vacaciones, incapacidad, etc).',
            }
        });
    }

    // Paso común: Tabla
    steps.push({
        element: '#tour-incidents-table',
        popover: {
            title: props.canApprove ? 'Historial de Solicitudes' : 'Historial y Estado',
            description: props.canApprove
                ? 'Revisa el historial de todas las solicitudes recibidas. Podrás ver el motivo y el estado actual de cada una.'
                : 'Aquí verás el estado de tus solicitudes anteriores. Mantente atento a si fueron "Aprobadas" o "Rechazadas".',
        }
    });

    // Paso específico para administradores: Columna de acciones (si hay datos)
    if (props.canApprove && props.requests.data.length > 0) {
        // Intentamos apuntar a la celda de acciones si existe, si no, al encabezado
        steps.push({
            element: '.p-datatable-tbody tr:first-child td:last-child', 
            popover: {
                title: 'Aprobar o Rechazar',
                description: 'Usa estos botones para dar respuesta rápida a las solicitudes pendientes. Al aprobar, se actualizará automáticamente la asistencia del empleado.',
            }
        });
    }

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: steps,
        onDestroyStarted: () => {
            markTourAsCompleted();
            tourDriver.destroy();
            disableBlocking();
        }
    });

    tourDriver.drive();
};

const markTourAsCompleted = async () => {
    try {
        await axios.post(route('tutorials.complete'), { module_name: 'incident_requests' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'incident_requests'));
        if (!response.data.completed) {
            isLoadingTour.value = true;
            setTimeout(() => {
                isLoadingTour.value = false;
                startTour();
            }, 800);
        }
    } catch (error) {
        console.error('Error verificando tutorial', error);
        isLoadingTour.value = false;
    }
});

onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Permisos e incidencias">
        
        <!-- Overlay de Carga (Spinner) -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa de Bloqueo -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">
            
            <!-- Header -->
            <!-- ID TOUR: Header -->
            <div id="tour-incidents-header" class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">
                        {{ canApprove ? 'Gestión de permisos' : 'Mis solicitudes' }}
                    </h1>
                    <p class="text-gray-500 text-sm">
                        {{ canApprove 
                            ? 'Revisa y procesa las solicitudes de tus empleados.' 
                            : 'Solicita vacaciones o permisos especiales.' }}
                    </p>
                </div>

                <!-- ID TOUR: Botón Crear -->
                <div id="tour-create-btn">
                    <Button 
                        v-if="!canApprove" 
                        label="Solicitar permiso" 
                        icon="pi pi-plus" 
                        @click="showCreateModal = true" 
                        class="shadow-lg"
                    />
                </div>
            </div>

            <!-- Tabla de Solicitudes -->
            <!-- ID TOUR: Tabla -->
            <div id="tour-incidents-table" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <DataTable :value="requests.data" :rows="10" paginator stripedRows>
                    <template #empty>
                        <div class="text-center py-12 text-gray-500">
                            <i class="pi pi-inbox !text-4xl mb-3 opacity-50"></i>
                            <p>No hay solicitudes registradas.</p>
                        </div>
                    </template>

                    <!-- ID -->
                    <Column field="id" header="#" sortable class="w-[50px]">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-400 font-bold">#{{ data.id }}</span>
                        </template>
                    </Column>

                    <!-- Empleado (Solo Admin) -->
                    <Column v-if="canApprove" header="Empleado" sortable field="employee.first_name">
                        <template #body="{ data }">
                            <div class="font-bold text-gray-800">{{ data.employee?.first_name }} {{ data.employee?.last_name }}</div>
                        </template>
                    </Column>

                    <!-- Tipo -->
                    <Column header="Tipo de Permiso">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i v-if="data.incident_type === 'vacaciones'" class="pi pi-sun text-orange-500"></i>
                                <i v-else-if="data.incident_type.includes('incapacidad')" class="pi pi-heart-fill text-red-500"></i>
                                <i v-else class="pi pi-file text-blue-500"></i>
                                <span class="font-medium text-gray-700">{{ getIncidentLabel(data.incident_type) }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Fechas -->
                    <Column header="Periodo">
                        <template #body="{ data }">
                            <div class="flex flex-col text-sm">
                                <span class="font-bold text-gray-800">
                                    {{ formatDate(data.start_date) }} <i class="pi pi-arrow-right text-[10px] mx-1 text-gray-400"></i> {{ formatDate(data.end_date) }}
                                </span>
                                <!-- Cálculo simple de días si son iguales o difieren -->
                                <span class="text-xs text-gray-500 mt-0.5">
                                    {{ data.start_date === data.end_date ? '1 día' : 'Varios días' }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Motivo -->
                    <Column header="Motivo / Justificación" class="max-w-[200px]">
                        <template #body="{ data }">
                            <div class="text-sm text-gray-600 truncate" :title="data.employee_reason">
                                {{ data.employee_reason }}
                            </div>
                            <div v-if="data.status === 'rejected' && data.admin_response" class="mt-1 text-xs text-red-600 bg-red-50 p-1 rounded">
                                <strong>Resp:</strong> {{ data.admin_response }}
                            </div>
                        </template>
                    </Column>

                    <!-- Estado -->
                    <Column header="Estado" field="status" sortable>
                        <template #body="{ data }">
                            <Tag :value="getStatusLabel(data.status)" :severity="getStatusSeverity(data.status)" rounded />
                        </template>
                    </Column>

                    <!-- Acciones (Solo Admin y Pendientes) -->
                    <Column v-if="canApprove" header="Acciones" class="w-[120px] text-center">
                        <template #body="{ data }">
                            <div v-if="data.status === 'pending'" class="flex gap-2 justify-center">
                                <Button 
                                    icon="pi pi-check" 
                                    severity="success" 
                                    rounded 
                                    text 
                                    v-tooltip.top="'Aprobar'"
                                    @click="approveRequest(data)" 
                                />
                                <Button 
                                    icon="pi pi-times" 
                                    severity="danger" 
                                    rounded 
                                    text 
                                    v-tooltip.top="'Rechazar'"
                                    @click="openRejectModal(data)" 
                                />
                            </div>
                            <span v-else class="text-xs text-gray-400 italic">Procesado</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- MODAL: Crear Solicitud (Empleados) -->
        <Dialog 
            v-model:visible="showCreateModal" 
            modal 
            header="Nueva solicitud" 
            :style="{ width: '500px' }"
            :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
        >
            <div class="flex flex-col gap-4 pt-2">
                <Message severity="info" :closable="false">
                    Las solicitudes aprobadas se reflejarán automáticamente en tu asistencia y nómina.
                </Message>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-gray-700">Tipo de permiso</label>
                    <Select 
                        v-model="createForm.incident_type" 
                        :options="requestOptions" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="Selecciona una opción..."
                        class="w-full"
                        :class="{'p-invalid': createForm.errors.incident_type}"
                    />
                    <small class="text-red-500" v-if="createForm.errors.incident_type">{{ createForm.errors.incident_type }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-bold text-gray-700">Fecha inicio</label>
                        <DatePicker v-model="createForm.start_date" showIcon dateFormat="yy-mm-dd" :minDate="new Date()" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-bold text-gray-700">Fecha fin</label>
                        <DatePicker v-model="createForm.end_date" showIcon dateFormat="yy-mm-dd" :minDate="createForm.start_date" />
                    </div>
                </div>

                <div v-if="calculatedDays > 0" class="text-right">
                    <span class="text-sm text-gray-500 font-medium">Duración estimada:</span>
                    <span class="ml-2 text-lg font-bold text-indigo-600">{{ calculatedDays }} días</span>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-gray-700">Motivo</label>
                    <Textarea 
                        v-model="createForm.employee_reason" 
                        rows="3" 
                        placeholder="Explica brevemente la razón..." 
                        class="w-full"
                    />
                    <small class="text-red-500" v-if="createForm.errors.employee_reason">{{ createForm.errors.employee_reason }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 mt-4">
                    <Button label="Cancelar" icon="pi pi-times" text @click="showCreateModal = false" severity="secondary" />
                    <Button 
                        label="Enviar solicitud" 
                        icon="pi pi-send" 
                        @click="submitCreate" 
                        :loading="createForm.processing"
                    />
                </div>
            </template>
        </Dialog>

        <!-- MODAL: Rechazar Solicitud (Admin) -->
        <Dialog 
            v-model:visible="showRejectModal" 
            modal 
            header="Rechazar solicitud" 
            :style="{ width: '400px' }"
        >
            <div class="flex flex-col gap-4 pt-2">
                <p class="text-sm text-gray-600">
                    Estás por rechazar la solicitud de 
                    <span class="font-bold text-gray-900">{{ selectedRequest?.employee?.first_name }}</span>. 
                    Por favor indica el motivo para el empleado.
                </p>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-gray-700">Motivo del rechazo</label>
                    <Textarea 
                        v-model="rejectForm.admin_response" 
                        rows="3" 
                        placeholder="Ej. Fechas no disponibles por carga de trabajo..." 
                        class="w-full"
                        autofocus
                    />
                    <small class="text-red-500" v-if="rejectForm.errors.admin_response">{{ rejectForm.errors.admin_response }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 mt-4">
                    <Button label="Cancelar" icon="pi pi-times" text @click="showRejectModal = false" severity="secondary" />
                    <Button 
                        label="Confirmar rechazo" 
                        icon="pi pi-ban" 
                        severity="danger" 
                        @click="confirmReject" 
                        :loading="rejectForm.processing"
                        :disabled="!rejectForm.admin_response"
                    />
                </div>
            </template>
        </Dialog>

    </AppLayout>
</template>