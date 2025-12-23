<script setup>
import { ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';
import ScheduleTemplateInput from '@/Components/ScheduleTemplateInput.vue';

const props = defineProps({
    employee: Object,
    availableBonuses: Array,
    shifts: { type: Array, default: () => [] } // Recibimos los turnos
});

const toast = useToast();
const fileInput = ref(null);
const maxBirthDate = new Date(); // Fecha máxima para evitar re-renders infinitos

// Función auxiliar para parsear fechas "YYYY-MM-DD" a objetos Date sin desfase
const parseDate = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString.split('T')[0] + 'T00:00:00');
};

// Extraer IDs de los bonos que ya tiene el empleado
const initialBonuses = props.employee.recurring_bonuses 
    ? props.employee.recurring_bonuses.map(b => b.id) 
    : [];

const form = useForm({
    _method: 'PUT', // Necesario para enviar archivos en edición
    first_name: props.employee.first_name,
    last_name: props.employee.last_name,
    email: props.employee.email,
    phone: props.employee.phone,
    password: '', // Nuevo campo para contraseña
    birth_date: parseDate(props.employee.birth_date),
    address: props.employee.address,
    hired_at: parseDate(props.employee.hired_at),
    base_salary: parseFloat(props.employee.base_salary),
    photo: null,
    remove_photo: false, // NUEVA BANDERA: Indica si se debe borrar la foto
    recurring_bonuses: initialBonuses,
    // Cargamos la plantilla existente o una vacía por defecto
    default_schedule_template: props.employee.default_schedule_template || {
        monday: null, 
        tuesday: null, 
        wednesday: null, 
        thursday: null, 
        friday: null, 
        saturday: null, 
        sunday: null
    }
});

// --- Lógica de Formateo de Teléfono ---
watch(() => form.phone, (val) => {
    if (!val) return;

    let numbers = val.replace(/\D/g, '');

    if (numbers.length > 10) {
        numbers = numbers.slice(0, 10);
    }

    let formatted = '';
    if (numbers.length > 0) formatted += numbers.slice(0, 2);
    if (numbers.length > 2) formatted += ' ' + numbers.slice(2, 6);
    if (numbers.length > 6) formatted += ' ' + numbers.slice(6, 10);

    if (val !== formatted) {
        form.phone = formatted;
    }
});

// --- Lógica de Imagen ---
// Inicializamos el preview con la foto existente (si tiene)
const photoPreview = ref(props.employee.profile_photo_url);

const onFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.photo = file;
        form.remove_photo = false; // Si sube una nueva, NO queremos "solo borrar", sino reemplazar
        photoPreview.value = URL.createObjectURL(file);
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const removePhoto = () => {
    form.photo = null;
    form.remove_photo = true; // Activar bandera de borrado
    photoPreview.value = null; // Quitar la foto visualmente
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        birth_date: data.birth_date ? data.birth_date.toISOString().split('T')[0] : null,
        hired_at: data.hired_at ? data.hired_at.toISOString().split('T')[0] : null,
    })).post(route('employees.update', props.employee.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Empleado actualizado correctamente', life: 3000 });
            form.password = ''; // Limpiar campo contraseña tras guardar
            // Restablecer el estado de la foto si es necesario
            if (form.remove_photo) {
                form.remove_photo = false;
            }
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar empleado">
        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">

            <!-- Contenedor Principal -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">

                <!-- Encabezado Sticky -->
                <div
                    class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('employees.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Editar empleado</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Actualiza la información personal y
                                laboral.</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('employees.index')">
                            <Button label="Cancelar" text severity="secondary" class="!font-medium" />
                        </Link>
                        <Button label="Guardar cambios" icon="pi pi-check" @click="submit" :loading="form.processing"
                            class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-bold shadow-lg shadow-orange-200/50"
                            rounded />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-10">

                    <!-- Columna Izquierda: Foto -->
                    <div class="lg:col-span-1 flex flex-col gap-6">
                        <div
                            class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col items-center text-center">
                            <span class="text-sm font-semibold text-surface-700 mb-4 self-start">Foto de perfil</span>

                            <div class="relative group cursor-pointer w-48 h-48">
                                <input ref="fileInput" type="file" accept="image/*" class="hidden"
                                    @change="onFileChange" />

                                <!-- Área Visual -->
                                <div class="w-full h-full rounded-full overflow-hidden border-2 border-dashed border-surface-300 flex items-center justify-center bg-surface-50 transition-all duration-300 group-hover:border-orange-400 group-hover:bg-orange-50 relative"
                                    @click="triggerFileInput">
                                    <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
                                    <div v-else class="flex flex-col items-center gap-2 text-surface-400">
                                        <i class="pi pi-camera !text-3xl"></i>
                                        <span class="text-xs font-medium">Cambiar foto</span>
                                    </div>
                                </div>

                                <!-- Botón Eliminar (Solo si hay preview) -->
                                <Button v-if="photoPreview" icon="pi pi-times" rounded size="small" severity="danger"
                                    class="absolute bottom-3 right-0 !size-6 shadow-md" @click.stop="removePhoto" />
                            </div>
                            <small class="text-surface-400 text-xs mt-4 px-2">
                                Importante: Rostro claro y sin accesorios para el reconocimiento facial.
                            </small>
                            <small v-if="form.errors.photo" class="text-red-500 text-xs mt-1">{{ form.errors.photo
                                }}</small>
                        </div>
                    </div>

                    <!-- Columna Derecha: Datos -->
                    <div class="lg:col-span-2 flex flex-col gap-6">

                        <!-- Información Personal -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-user text-orange-500"></i> Información personal
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label for="first_name" class="font-medium text-surface-700">Nombre(s)</label>
                                    <InputText id="first_name" v-model="form.first_name" class="w-full"
                                        :invalid="!!form.errors.first_name" />
                                    <small v-if="form.errors.first_name" class="text-red-500">{{ form.errors.first_name
                                        }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="last_name" class="font-medium text-surface-700">Apellidos</label>
                                    <InputText id="last_name" v-model="form.last_name" class="w-full"
                                        :invalid="!!form.errors.last_name" />
                                    <small v-if="form.errors.last_name" class="text-red-500">{{ form.errors.last_name
                                        }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="birth_date" class="font-medium text-surface-700">Fecha de
                                        nacimiento</label>
                                    <DatePicker id="birth_date" v-model="form.birth_date" showIcon :maxDate="maxBirthDate" dateFormat="dd/mm/yy"
                                        class="w-full" :invalid="!!form.errors.birth_date" />
                                    <small v-if="form.errors.birth_date" class="text-red-500">{{ form.errors.birth_date
                                        }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Contacto y Acceso -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-id-card text-blue-500"></i> Contacto y acceso
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div class="flex flex-col gap-2">
                                    <label for="email" class="font-medium text-surface-700">Correo electrónico</label>
                                    <InputText id="email" v-model="form.email" class="w-full"
                                        placeholder="usuario@empresa.com" :invalid="!!form.errors.email" />
                                    <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                                </div>

                                <!-- Password (Nuevo) -->
                                <div class="flex flex-col gap-2">
                                    <label for="password" class="font-medium text-surface-700">Contraseña de sistema</label>
                                    <InputText id="password" v-model="form.password" type="password" class="w-full"
                                        placeholder="Dejar vacío para no cambiar" :invalid="!!form.errors.password" />
                                    <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                                </div>

                                <!-- Phone -->
                                <div class="flex flex-col gap-2">
                                    <label for="phone" class="font-medium text-surface-700">Teléfono</label>
                                    <InputText id="phone" v-model="form.phone" class="w-full" maxlength="12"
                                        placeholder="Ej. 3312345678" :invalid="!!form.errors.phone" />
                                    <small v-if="form.errors.phone" class="text-red-500">{{ form.errors.phone }}</small>
                                </div>

                                <!-- Address -->
                                <div class="flex flex-col gap-2">
                                    <label for="address" class="font-medium text-surface-700">Dirección</label>
                                    <Textarea id="address" v-model="form.address" rows="1" autoResize class="w-full"
                                        :invalid="!!form.errors.address" />
                                    <small v-if="form.errors.address" class="text-red-500">{{ form.errors.address
                                        }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Datos Laborales -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-briefcase text-purple-500"></i> Datos laborales
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-2">
                                    <label for="hired_at" class="font-medium text-surface-700">Fecha de
                                        contratación</label>
                                    <DatePicker id="hired_at" v-model="form.hired_at" showIcon dateFormat="dd/mm/yy"
                                        class="w-full" :invalid="!!form.errors.hired_at" />
                                    <small v-if="form.errors.hired_at" class="text-red-500">{{ form.errors.hired_at
                                        }}</small>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label for="base_salary" class="font-medium text-surface-700">Sueldo base (por
                                        turno)</label>
                                    <InputNumber id="base_salary" v-model="form.base_salary" mode="currency"
                                        currency="MXN" locale="es-MX" class="w-full" placeholder="$0.00"
                                        :invalid="!!form.errors.base_salary" />
                                    <small v-if="form.errors.base_salary" class="text-red-500">{{
                                        form.errors.base_salary }}</small>
                                </div>

                                <div
                                    class="md:col-span-2 flex flex-col gap-2 bg-blue-50 p-4 rounded-xl border border-blue-100">
                                    <label class="font-bold text-sm text-blue-800 flex items-center gap-2">
                                        <i class="pi pi-gift"></i> Bonos recurrentes (automáticos)
                                    </label>
                                    <MultiSelect v-model="form.recurring_bonuses" :options="availableBonuses"
                                        optionLabel="name" optionValue="id" placeholder="Seleccionar bonos..."
                                        display="chip" class="w-full" filter />
                                </div>
                            </div>
                        </div>

                        <!-- Configuración de Horario (Nueva Sección) -->
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-4 flex items-center gap-2">
                                <i class="pi pi-calendar text-indigo-500"></i> Configuración de Horario
                            </h2>
                            
                            <!-- Componente para la Plantilla de Turnos -->
                            <ScheduleTemplateInput 
                                v-model="form.default_schedule_template" 
                                :shifts="shifts" 
                            />
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Ajustes finos para inputs */
:deep(.p-inputtext),
:deep(.p-textarea),
:deep(.p-inputnumber-input) {
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}

:deep(.p-inputtext:focus),
:deep(.p-textarea:focus) {
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
    border-color: #f97316;
}

:deep(.p-datepicker-input) {
    border-radius: 0.75rem;
}
</style>