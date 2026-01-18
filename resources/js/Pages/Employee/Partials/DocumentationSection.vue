<script setup>
import { ref } from 'vue';
import { useToast } from "primevue/usetoast";
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';

const props = defineProps({
    employeeId: {
        type: Number,
        required: true
    },
    hiredAt: {
        type: String,
        required: true
    }
});

const toast = useToast();

// --- LÓGICA DE CONTRATOS ---
const showContractConfigDialog = ref(false);
const contractConfig = ref({
    type: 'indefinite', // training, seasonal, indefinite
    start_date: new Date(),
    end_date: null,
    season_name: ''
});

const openContractDialog = (type) => {
    contractConfig.value.type = type;
    // La fecha de inicio por defecto es la de contratación
    const hiredDate = props.hiredAt ? new Date(props.hiredAt) : new Date();
    contractConfig.value.start_date = hiredDate;
    
    if (type === 'training') {
        const end = new Date(hiredDate);
        end.setDate(end.getDate() + 30);
        contractConfig.value.end_date = end;
    } else if (type === 'seasonal') {
        contractConfig.value.end_date = null;
        contractConfig.value.season_name = 'Navideña';
    } else {
        contractConfig.value.end_date = null;
    }
    showContractConfigDialog.value = true;
};

const generateContract = () => {
    if (contractConfig.value.type !== 'indefinite' && !contractConfig.value.end_date) {
        toast.add({ severity: 'warn', summary: 'Faltan datos', detail: 'Selecciona una fecha de término.', life: 3000 });
        return;
    }

    const params = {
        start_date: contractConfig.value.start_date?.toISOString().split('T')[0],
        end_date: contractConfig.value.end_date?.toISOString().split('T')[0],
        season_name: contractConfig.value.season_name
    };

    const url = route('employees.contract', { 
        employee: props.employeeId, 
        type: contractConfig.value.type,
        ...params 
    });

    window.open(url, '_blank');
    showContractConfigDialog.value = false;
};

// --- LÓGICA DE ACTA ADMINISTRATIVA ---
const showActaConfigDialog = ref(false);
const actaConfig = ref({
    motive: '',
    description: '',
    penalty_type: 'none',
    penalty_value: ''
});

const penaltyOptions = [
    { label: 'No hay penalización (Solo llamada de atención)', value: 'none' },
    { label: 'Suspensión sin goce de sueldo', value: 'suspension' },
    { label: 'Penalización monetaria (Daños)', value: 'monetary' },
];

const openActaDialog = () => {
    actaConfig.value = {
        motive: '',
        description: '',
        penalty_type: 'none',
        penalty_value: ''
    };
    showActaConfigDialog.value = true;
};

const generateActa = () => {
    if (!actaConfig.value.motive || !actaConfig.value.description) {
        toast.add({ severity: 'warn', summary: 'Campos incompletos', detail: 'Ingresa el motivo y descripción de los hechos.', life: 3000 });
        return;
    }

    const params = {
        motive: actaConfig.value.motive,
        description: actaConfig.value.description,
        penalty_type: actaConfig.value.penalty_type,
        penalty_value: actaConfig.value.penalty_value
    };

    const url = route('employees.acta', {
        employee: props.employeeId,
        ...params
    });

    window.open(url, '_blank');
    showActaConfigDialog.value = false;
};

// --- LÓGICA DE RENUNCIA Y RECOMENDACIÓN ---
const generateResignation = () => {
    const url = route('employees.resignation', { employee: props.employeeId });
    window.open(url, '_blank');
};

const generateRecommendation = () => {
    const url = route('employees.recommendation', { employee: props.employeeId });
    window.open(url, '_blank');
};

// --- LÓGICA DE FINIQUITO ---
const generateSettlement = () => {
    const url = route('employees.settlement', { employee: props.employeeId });
    window.open(url, '_blank');
};
</script>

<template>
    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 p-6 flex flex-col gap-4">
        <h3 class="text-xs font-bold text-surface-400 uppercase tracking-wider flex items-center gap-2">
            <i class="pi pi-file-pdf"></i> Documentación
        </h3>
        <p class="text-xs text-surface-500 mb-2">Generar documentos legales listos para firmar.</p>
        
        <div class="space-y-2">
            <Button 
                label="Capacitación (30 días)" 
                icon="pi pi-file" 
                severity="secondary" 
                outlined 
                size="small"
                class="w-full justify-start text-left"
                @click="openContractDialog('training')"
            />
            <Button 
                label="Contrato de Temporada" 
                icon="pi pi-sun" 
                severity="secondary" 
                outlined 
                size="small"
                class="w-full justify-start text-left"
                @click="openContractDialog('seasonal')"
            />
            <Button 
                label="Contrato Indeterminado" 
                icon="pi pi-briefcase" 
                severity="secondary" 
                outlined 
                size="small"
                class="w-full justify-start text-left"
                @click="openContractDialog('indefinite')"
            />
        </div>

        <div class="border-t border-surface-100 my-2"></div>

        <div class="space-y-2">
            <Button 
                label="Acta Administrativa" 
                icon="pi pi-exclamation-circle" 
                severity="danger" 
                outlined 
                size="small"
                class="w-full justify-start text-left"
                @click="openActaDialog"
            />
            <div class="grid grid-cols-2 gap-2">
                <Button 
                    label="Renuncia" 
                    icon="pi pi-thumbs-down" 
                    severity="info" 
                    outlined 
                    size="small"
                    class="w-full justify-center"
                    @click="generateResignation"
                    v-tooltip.top="'Carta de Renuncia Voluntaria'"
                />
                <Button 
                    label="Recom." 
                    icon="pi pi-thumbs-up" 
                    severity="success" 
                    outlined 
                    size="small"
                    class="w-full justify-center"
                    @click="generateRecommendation"
                    v-tooltip.top="'Carta de Recomendación'"
                />
            </div>
            <Button 
                label="Recibo de Finiquito" 
                icon="pi pi-wallet" 
                severity="success" 
                outlined 
                size="small"
                class="w-full justify-start text-left"
                @click="generateSettlement"
            />
        </div>

        <!-- DIÁLOGO: Configurar Contrato -->
        <Dialog 
            v-model:visible="showContractConfigDialog" 
            modal 
            header="Configurar Contrato" 
            :style="{ width: '30rem' }"
            :breakpoints="{ '960px': '90vw' }"
        >
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-700 text-sm">Fecha de Inicio del Contrato</label>
                    <DatePicker v-model="contractConfig.start_date" showIcon dateFormat="dd/mm/yy" class="w-full" />
                </div>

                <div class="flex flex-col gap-2" v-if="contractConfig.type !== 'indefinite'">
                    <label class="font-bold text-surface-700 text-sm">Fecha de Término</label>
                    <DatePicker v-model="contractConfig.end_date" showIcon dateFormat="dd/mm/yy" class="w-full" />
                </div>

                <div class="flex flex-col gap-2" v-if="contractConfig.type === 'seasonal'">
                    <label class="font-bold text-surface-700 text-sm">Nombre de la Temporada</label>
                    <InputText v-model="contractConfig.season_name" placeholder="Ej. Navideña, Semana Santa" class="w-full" />
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" text @click="showContractConfigDialog = false" severity="secondary" />
                    <Button label="Generar PDF" icon="pi pi-file-pdf" @click="generateContract" />
                </div>
            </template>
        </Dialog>

        <!-- DIÁLOGO: Configurar Acta Administrativa -->
        <Dialog 
            v-model:visible="showActaConfigDialog" 
            modal 
            header="Levantar Acta Administrativa" 
            :style="{ width: '40rem' }"
            :breakpoints="{ '960px': '90vw' }"
        >
            <div class="flex flex-col gap-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800 flex gap-2">
                    <i class="pi pi-info-circle mt-0.5"></i>
                    <p>Este documento quedará registrado en el expediente. Describe los hechos con objetividad.</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-700 text-sm">Motivo de la Falta</label>
                    <InputText v-model="actaConfig.motive" placeholder="Ej. Falta injustificada, Retardo, Uso indebido de equipo..." class="w-full" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-700 text-sm">Descripción de los Hechos</label>
                    <Textarea v-model="actaConfig.description" rows="4" class="w-full" placeholder="Detalla qué ocurrió, cuándo y dónde..." />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="font-bold text-surface-700 text-sm">Tipo de Sanción</label>
                        <Select 
                            v-model="actaConfig.penalty_type" 
                            :options="penaltyOptions" 
                            optionLabel="label" 
                            optionValue="value" 
                            class="w-full"
                        />
                    </div>
                    
                    <div v-if="actaConfig.penalty_type === 'suspension'" class="flex flex-col gap-2 animate-fade-in">
                        <label class="font-bold text-surface-700 text-sm">Días de Suspensión</label>
                        <div class="p-inputgroup flex-1">
                            <InputText v-model="actaConfig.penalty_value" placeholder="Ej. 3" type="number" />
                            <span class="p-inputgroup-addon">Días</span>
                        </div>
                    </div>

                    <div v-if="actaConfig.penalty_type === 'monetary'" class="flex flex-col gap-2 animate-fade-in">
                        <label class="font-bold text-surface-700 text-sm">Monto de Penalización</label>
                        <div class="p-inputgroup flex-1">
                            <span class="p-inputgroup-addon">$</span>
                            <InputText v-model="actaConfig.penalty_value" placeholder="0.00" type="number" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" text @click="showActaConfigDialog = false" severity="secondary" />
                    <Button label="Generar Acta" icon="pi pi-print" severity="danger" @click="generateActa" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>