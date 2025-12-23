<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';

// Asumimos que Select y Button están registrados globalmente o auto-importados
// Si usas PrimeVue v3, cambia <Select> por <Dropdown> en el template

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    shifts: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['update:modelValue']);
const toast = useToast();

// Días de la semana
const days = [
    { key: 'monday', label: 'Lunes', short: 'Lun' },
    { key: 'tuesday', label: 'Martes', short: 'Mar' },
    { key: 'wednesday', label: 'Miércoles', short: 'Mié' },
    { key: 'thursday', label: 'Jueves', short: 'Jue' },
    { key: 'friday', label: 'Viernes', short: 'Vie' },
    { key: 'saturday', label: 'Sábado', short: 'Sáb' },
    { key: 'sunday', label: 'Domingo', short: 'Dom' },
];

// Helper para formato de hora (09:00:00 -> 09:00)
const formatTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.substring(0, 5);
};

// Obtener objeto de turno completo por ID
const getShift = (shiftId) => {
    return props.shifts.find(s => s.id === shiftId);
};

// --- Acciones Masivas ---

const copyMondayToWeekdays = () => {
    const mondayShift = props.modelValue['monday'];
    if (mondayShift === undefined) {
        toast.add({ severity: 'info', summary: 'Atención', detail: 'Selecciona primero un turno para el Lunes', life: 3000 });
        return;
    }

    const newValue = { ...props.modelValue };
    // Aplicar a Mar, Mié, Jue, Vie
    ['tuesday', 'wednesday', 'thursday', 'friday'].forEach(day => {
        newValue[day] = mondayShift;
    });
    
    emit('update:modelValue', newValue);
    toast.add({ severity: 'success', summary: 'Aplicado', detail: 'Turno de Lunes copiado a toda la semana laboral', life: 2000 });
};

const clearAll = () => {
    const newValue = {};
    days.forEach(day => newValue[day.key] = null);
    emit('update:modelValue', newValue);
};

const updateDay = (dayKey, value) => {
    const newValue = { ...props.modelValue };
    newValue[dayKey] = value;
    emit('update:modelValue', newValue);
};
</script>

<template>
    <div class="flex flex-col gap-4">
        
        <!-- Barra de Herramientas -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-surface-50 p-3 rounded-xl border border-surface-200">
            <div>
                <h3 class="text-sm font-bold text-surface-800 flex items-center gap-2">
                    <i class="pi pi-calendar text-indigo-600"></i>
                    Semana típica (plantilla)
                </h3>
                <p class="text-xs text-surface-500 mt-1">Configura los turnos base para la generación automática de horario semanal.</p>
            </div>
            
            <div class="flex gap-2 w-full sm:w-auto">
                <Button 
                    label="Copiar Lun a Vie" 
                    icon="pi pi-copy" 
                    size="small" 
                    severity="secondary" 
                    outlined
                    class="!text-xs w-full sm:w-auto"
                    @click="copyMondayToWeekdays"
                    v-tooltip.top="'Usa el turno del Lunes para rellenar hasta el Viernes'"
                />
                <Button 
                    icon="pi pi-trash" 
                    size="small" 
                    severity="danger" 
                    text
                    class="!w-10"
                    @click="clearAll"
                    v-tooltip.top="'Limpiar toda la semana'"
                />
            </div>
        </div>

        <!-- Grid de Días -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4">
            <div 
                v-for="day in days" 
                :key="day.key" 
                class="bg-white rounded-xl border shadow-sm transition-all duration-200 hover:shadow-md group"
                :class="modelValue?.[day.key] ? 'border-surface-200' : 'border-surface-100 bg-surface-50/50'"
                :style="{ borderLeft: `4px solid ${getShift(modelValue?.[day.key])?.color || '#e5e7eb'}` }"
            >
                <div class="p-3 flex flex-col gap-2">
                    <!-- Header Día -->
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-surface-700 uppercase tracking-wide text-xs">
                            {{ day.label }}
                        </span>
                        <i v-if="modelValue?.[day.key]" class="pi pi-check-circle text-green-500 !text-xs"></i>
                    </div>

                    <!-- Selector PrimeVue -->
                    <!-- Nota: Si usas v3 cambia Select por Dropdown -->
                    <Select 
                        :modelValue="modelValue?.[day.key]"
                        @update:modelValue="(val) => updateDay(day.key, val)"
                        :options="shifts" 
                        optionLabel="name" 
                        optionValue="id"
                        placeholder="Descanso" 
                        class="w-full !text-sm"
                        :pt="{
                            root: { class: '!border-0 !bg-transparent !shadow-none !p-0 !h-auto' },
                            input: { class: '!p-1 !text-sm !font-semibold !text-surface-900' },
                            trigger: { class: '!w-6 !text-surface-400' },
                            panel: { class: '!border !border-surface-100 !shadow-lg !rounded-xl' }
                        }"
                        showClear
                    >
                        <!-- Template para el valor seleccionado (dentro del input) -->
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex flex-col items-start gap-0.5">
                                <span class="text-sm font-bold text-surface-900 leading-tight">
                                    {{ getShift(slotProps.value)?.name }}
                                </span>
                                <span class="text-[10px] text-surface-500 font-medium bg-surface-100 px-1.5 rounded">
                                    {{ formatTime(getShift(slotProps.value)?.start_time) }} - 
                                    {{ formatTime(getShift(slotProps.value)?.end_time) }}
                                </span>
                            </div>
                            <span v-else class="text-surface-400 font-normal italic text-sm py-2">
                                - Descanso -
                            </span>
                        </template>

                        <!-- Template para las opciones (lista desplegable) -->
                        <template #option="slotProps">
                            <div class="flex items-center gap-3 py-1">
                                <div 
                                    class="w-2 h-8 rounded-full flex-shrink-0"
                                    :style="{ backgroundColor: slotProps.option.color }"
                                ></div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-surface-800 text-sm">
                                        {{ slotProps.option.name }}
                                    </span>
                                    <span class="text-xs text-surface-500">
                                        {{ formatTime(slotProps.option.start_time) }} - {{ formatTime(slotProps.option.end_time) }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </Select>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ajustes finos para forzar estilos dentro del componente Select si es necesario */
:deep(.p-select-label) {
    display: flex;
    align-items: center;
}
</style>