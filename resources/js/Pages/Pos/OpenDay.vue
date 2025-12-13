<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from "primevue/usetoast";
import Textarea from 'primevue/textarea';

const props = defineProps({
    auth: Object
});

const toast = useToast();
const today = new Date().toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

const form = useForm({
    cash_start: 0,
    notes: ''
});

// --- Lógica de Teclado Numérico ---
const displayValue = ref(''); // Valor visual para el input

const appendNumber = (num) => {
    if (displayValue.value.includes('.') && num === '.') return;
    if (displayValue.value === '0' && num !== '.') {
        displayValue.value = num.toString();
    } else {
        displayValue.value += num.toString();
    }
    form.cash_start = parseFloat(displayValue.value);
};

const backspace = () => {
    displayValue.value = displayValue.value.slice(0, -1);
    form.cash_start = displayValue.value ? parseFloat(displayValue.value) : 0;
};

const clear = () => {
    displayValue.value = '';
    form.cash_start = 0;
};

// --- Envío ---
const submitOpenDay = () => {
    if (form.cash_start < 0) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'El fondo de caja no puede ser negativo.', life: 3000 });
        return;
    }

    form.post(route('pos.open'), {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Caja Abierta', detail: '¡Buen día! La operación ha iniciado.', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo abrir la caja.', life: 3000 });
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0);
};
</script>

<template>
    <AppLayout title="Apertura de caja">
        <div class="h-[calc(100vh-5rem)] flex items-center justify-center p-4">
            
            <div class="w-full max-w-4xl bg-white/80 backdrop-blur-xl border border-surface-200 rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row h-full md:h-auto min-h-[500px]">
                
                <!-- Panel Izquierdo: Información e Input -->
                <div class="flex-1 p-8 flex flex-col justify-between bg-gradient-to-br from-indigo-50 to-white">
                    <div>
                        <h1 class="text-2xl font-black text-surface-900 mb-2">¡Hola, {{ auth.user.name }}! 👋</h1>
                        <p class="text-surface-500 text-lg mb-4 capitalize">{{ today }}</p>
                        
                        <div class="bg-indigo-100/50 p-4 rounded-2xl border border-indigo-100 mb-6">
                            <label class="block text-indigo-900 font-bold text-sm uppercase tracking-wider mb-2">Fondo inicial de caja</label>
                            <div class="text-3xl font-mono font-black text-indigo-600 truncate">
                                {{ displayValue ? `$${displayValue}` : '$0.00' }}
                            </div>
                            <p class="text-xs text-indigo-400 mt-2">Ingresa el efectivo con el que inicias el día.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-surface-600 font-bold text-sm">Notas de apertura (opcional)</label>
                            <Textarea v-model="form.notes" rows="3" placeholder="Ej. Faltó cambio de 50, se limpió terminal..." class="w-full bg-white !border-surface-200" />
                        </div>
                    </div>

                    <div class="mt-6 md:mt-0">
                        <button 
                            @click="submitOpenDay"
                            :disabled="form.processing"
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-lg shadow-indigo-200 transform active:scale-95 transition-all flex items-center justify-center gap-2"
                        >
                            <i v-if="form.processing" class="pi pi-spin pi-spinner"></i>
                            <i v-else class="pi pi-lock-open"></i>
                            <span>Abrir caja e iniciar</span>
                        </button>
                    </div>
                </div>

                <!-- Panel Derecho: Teclado Numérico -->
                <div class="w-full md:w-[300px] bg-white border-l border-surface-100 p-5 flex flex-col justify-center">
                    <div class="grid grid-cols-3 gap-2 h-full md:h-auto">
                        <button v-for="n in [1,2,3,4,5,6,7,8,9]" :key="n" @click="appendNumber(n)" class="aspect-square md:aspect-[4/3] bg-surface-50 hover:bg-surface-100 rounded-2xl text-2xl font-bold text-surface-700 shadow-sm border border-surface-200 active:scale-95 transition-all">
                            {{ n }}
                        </button>
                        
                        <button @click="appendNumber('.')" class="aspect-square md:aspect-[4/3] bg-surface-50 hover:bg-surface-100 rounded-2xl text-2xl font-bold text-surface-700 shadow-sm border border-surface-200 active:scale-95 transition-all">.</button>
                        <button @click="appendNumber(0)" class="aspect-square md:aspect-[4/3] bg-surface-50 hover:bg-surface-100 rounded-2xl text-2xl font-bold text-surface-700 shadow-sm border border-surface-200 active:scale-95 transition-all">0</button>
                        
                        <!-- Botón Borrar (Backspace) -->
                        <button @click="backspace" class="aspect-square md:aspect-[4/3] bg-orange-50 hover:bg-orange-100 rounded-2xl text-2xl text-orange-600 shadow-sm border border-orange-200 active:scale-95 transition-all flex items-center justify-center">
                            <i class="pi pi-delete-left"></i>
                        </button>
                    </div>
                    
                    <button @click="clear" class="w-full mt-4 py-3 text-surface-400 font-medium text-sm hover:text-red-500 transition-colors">
                        Limpiar todo
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-inputtextarea) {
    border-radius: 1rem;
    resize: none;
}
</style>