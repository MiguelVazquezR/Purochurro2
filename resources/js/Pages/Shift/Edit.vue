<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    shift: Object,
});

const toast = useToast();

// Helper para convertir "09:00:00" a objeto Date para el componente
const parseTime = (timeString) => {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(hours);
    date.setMinutes(minutes);
    return date;
};

const form = useForm({
    name: props.shift.name,
    start_time: parseTime(props.shift.start_time),
    end_time: parseTime(props.shift.end_time),
    color: props.shift.color.split('#')[1] || '3b82f6',
    is_active: Boolean(props.shift.is_active),
});

const submit = () => {
    const formatTime = (date) => {
        if (!date) return null;
        return date.toLocaleTimeString('es-MX', { hour12: false, hour: '2-digit', minute: '2-digit' });
    };

    form.transform((data) => ({
        ...data,
        start_time: formatTime(data.start_time),
        end_time: formatTime(data.end_time),
    })).put(route('shifts.update', props.shift.id), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Turno actualizado correctamente', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Por favor revisa los campos del formulario', life: 3000 });
        }
    });
};
</script>

<template>
    <AppLayout title="Editar Turno">
        <div class="w-full max-w-5xl mx-auto flex gap-8 items-start relative">
            
            <!-- Contenedor Principal -->
            <div class="flex-1 flex flex-col gap-8 w-full min-w-0">
                
                <!-- Encabezado Sticky -->
                <div class="sticky top-16 z-30 bg-surface-50/90 backdrop-blur-md py-4 border-b border-surface-200/50 flex items-center justify-between transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <Link :href="route('shifts.index')">
                            <Button icon="pi pi-arrow-left" text rounded severity="secondary" aria-label="Volver" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-surface-900">Editar turno</h1>
                            <p class="text-surface-500 text-sm mt-1 hidden md:block">Modifica la configuración del horario.</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('shifts.index')">
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
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-5">
                            <span class="text-sm font-semibold text-surface-700">Apariencia</span>
                            
                            <!-- Color Picker -->
                            <div class="flex flex-col gap-2">
                                <label class="font-bold text-surface-700 text-sm">Color distintivo</label>
                                <div class="flex items-center gap-3">
                                    <ColorPicker v-model="form.color" format="hex" />
                                    <span class="text-surface-500 text-sm font-mono">{{ form.color }}</span>
                                </div>
                                <small class="text-surface-400 text-xs">Se usará en el calendario.</small>
                            </div>

                            <hr class="border-surface-100" />

                            <!-- Estado -->
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-surface-900">Estado activo</span>
                                    <span class="text-xs text-surface-500">Disponible para asignar</span>
                                </div>
                                <ToggleSwitch v-model="form.is_active" />
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Detalles -->
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6">
                            <h2 class="text-lg font-bold text-surface-900 mb-6 flex items-center gap-2">
                                <i class="pi pi-clock text-orange-500"></i> Definición de Horario
                            </h2>

                            <div class="grid grid-cols-1 gap-6">
                                <!-- Nombre -->
                                <div class="flex flex-col gap-2">
                                    <label for="name" class="font-bold text-surface-700">Nombre del turno</label>
                                    <InputText 
                                        id="name" 
                                        v-model="form.name" 
                                        class="w-full" 
                                        :invalid="!!form.errors.name"
                                    />
                                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                                </div>

                                <!-- Horarios -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-2">
                                        <label for="start_time" class="font-bold text-surface-700">Hora de Entrada</label>
                                        <DatePicker 
                                            id="start_time" 
                                            v-model="form.start_time" 
                                            timeOnly 
                                            hourFormat="24"
                                            showIcon
                                            iconDisplay="input"
                                            placeholder="00:00"
                                            class="w-full"
                                            :invalid="!!form.errors.start_time"
                                        >
                                            <template #inputicon="slotProps">
                                                <i class="pi pi-clock" @click="slotProps.clickCallback" />
                                            </template>
                                        </DatePicker>
                                        <small v-if="form.errors.start_time" class="text-red-500">{{ form.errors.start_time }}</small>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <label for="end_time" class="font-bold text-surface-700">Hora de Salida</label>
                                        <DatePicker 
                                            id="end_time" 
                                            v-model="form.end_time" 
                                            timeOnly 
                                            hourFormat="24"
                                            showIcon
                                            iconDisplay="input"
                                            placeholder="00:00"
                                            class="w-full"
                                            :invalid="!!form.errors.end_time"
                                        >
                                            <template #inputicon="slotProps">
                                                <i class="pi pi-clock" @click="slotProps.clickCallback" />
                                            </template>
                                        </DatePicker>
                                        <small v-if="form.errors.end_time" class="text-red-500">{{ form.errors.end_time }}</small>
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
:deep(.p-inputtext), :deep(.p-textarea), :deep(.p-inputnumber-input), :deep(.p-datepicker-input) {
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}
:deep(.p-inputtext:focus) {
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
    border-color: #f97316;
}
:deep(.p-colorpicker-preview) {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
}
:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: #f97316 !important;
    border-color: #f97316 !important;
}
</style>