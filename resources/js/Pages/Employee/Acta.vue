<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    business: Object,
    date: Object, // { full, time, day, month, year }
    motive: String,
    description: String,
    penalty_type: String, // 'none', 'suspension', 'monetary'
    penalty_value: String // '3' (días) o '500' (pesos)
});

const printDocument = () => {
    window.print();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);
};
</script>

<template>
    <Head title="Acta administrativa" />

    <div class="min-h-screen bg-gray-100 p-8 print:p-0 print:bg-white font-serif text-gray-900">
        
        <!-- Barra de herramientas (Pantalla) -->
        <div class="max-w-[21.59cm] mx-auto mb-6 flex justify-between items-center print:hidden">
            <button 
                @click="printDocument" 
                class="bg-red-700 text-white px-4 py-2 rounded shadow hover:bg-red-800 font-sans font-bold flex items-center gap-2"
            >
                <i class="pi pi-print"></i> Imprimir acta
            </button>
            <span class="text-sm text-gray-500 font-sans">Formato carta</span>
        </div>

        <!-- Documento -->
        <div class="page-content mx-auto bg-white">
            
            <!-- Encabezado -->
            <div class="flex justify-between items-start border-b-2 border-black pb-4 mb-6">
                <div>
                    <ApplicationLogo class="h-12 w-auto mb-2" />
                    <p class="text-xs uppercase text-gray-600">Dirección General</p>
                </div>
                <div class="text-right">
                    <h2 class="font-bold text-lg text-red-700 uppercase">Acta administrativa</h2>
                    <p class="text-sm">Fecha: {{ date.full }}</p>
                </div>
            </div>

            <!-- Cuerpo del Acta -->
            <div class="text-justify leading-relaxed space-y-6 text-sm">
                
                <p>
                    En la ciudad de <strong>Zapopan, Jalisco</strong>, siendo las <strong>{{ date.time }}</strong> horas del día <strong>{{ date.day }} de {{ date.month }} del año {{ date.year }}</strong>, 
                    se reúnen en las instalaciones de la empresa <strong>“{{ business.name }}”</strong>, ubicada en {{ business.business_address || business.address }}, 
                    el Supervisor/Encargado en turno y el colaborador involucrado, con el objetivo de levantar la presente <strong>ACTA ADMINISTRATIVA</strong>.
                </p>

                <div class="bg-gray-50 border border-gray-200 p-4 rounded print:border-black print:bg-transparent">
                    <h3 class="font-bold border-b border-gray-300 mb-2 pb-1 uppercase text-xs text-gray-500">Datos del Colaborador</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs font-bold text-gray-600">Nombre:</span>
                            <span class="text-base uppercase">{{ employee.first_name }} {{ employee.last_name }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-600">Puesto:</span>
                            <span class="text-base uppercase">Ayudante General</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold uppercase underline mb-2">I. HECHOS Y MOTIVO</h3>
                    <p>
                        Se hace constar que el colaborador incurrió en la siguiente falta al Reglamento Interior de Trabajo y/o a las políticas internas de la empresa:
                    </p>
                    <p class="mt-2 font-bold text-red-800 uppercase text-center bg-red-50 py-1 print:bg-transparent print:text-black">
                        "{{ motive }}"
                    </p>
                </div>

                <div>
                    <h3 class="font-bold uppercase underline mb-2">II. DESCRIPCIÓN DE LOS HECHOS</h3>
                    <p>
                        A continuación, se describen los hechos ocurridos de manera detallada:
                    </p>
                    <div class="mt-2 p-4 border border-gray-300 min-h-[100px] italic bg-gray-50 print:bg-transparent">
                        “{{ description }}”
                    </div>
                </div>

                <div>
                    <h3 class="font-bold uppercase underline mb-2">III. RESOLUCIÓN Y SANCIONES</h3>
                    <p>
                        Derivado de lo anterior y conforme a la gravedad de la falta, la Dirección determina aplicar la siguiente medida disciplinaria:
                    </p>
                    
                    <div class="mt-4 p-4 border-2 border-black font-bold text-center uppercase text-base">
                        <span v-if="penalty_type === 'none'">
                            AMONESTACIÓN VERBAL / ESCRITA<br>
                            <span class="text-xs font-normal normal-case block mt-1">(Llamado de atención a no reincidir en la falta)</span>
                        </span>
                        
                        <span v-else-if="penalty_type === 'suspension'">
                            SUSPENSIÓN DE LABORES SIN GOCE DE SUELDO<br>
                            <span class="text-sm block mt-1">Por un periodo de: {{ penalty_value }} DÍA(S)</span>
                        </span>

                        <span v-else-if="penalty_type === 'monetary'">
                            PENALIZACIÓN ECONÓMICA<br>
                            <span class="text-xs font-normal normal-case block mt-1">(Reposición de daños o faltantes)</span>
                            <span class="text-sm block mt-1">Monto: {{ formatCurrency(penalty_value) }}</span>
                        </span>
                    </div>

                    <p class="mt-4 text-xs">
                        El colaborador manifiesta estar enterado de la sanción y se compromete a corregir su conducta, entendiendo que la reincidencia podrá derivar en suspensión temporal sin goce de sueldo, penalización económica o la rescisión de su contrato laboral sin responsabilidad para el patrón, conforme a la Ley Federal del Trabajo.
                    </p>
                </div>

            </div>

            <!-- Firmas -->
            <div class="mt-20 pt-10 break-inside-avoid">
                <div class="flex justify-between items-end px-4 gap-8">
                    
                    <div class="text-center flex-1">
                        <div class="border-t border-black pt-2">
                            <strong class="uppercase text-sm block mb-8">SUPERVISOR / ENCARGADO</strong>
                            <span class="text-xs text-gray-500">(Nombre y Firma)</span>
                        </div>
                    </div>

                    <div class="text-center flex-1">
                        <div class="border-t border-black pt-2">
                            <strong class="uppercase text-sm block">{{ employee.first_name }} {{ employee.last_name }}</strong>
                            <span class="text-xs uppercase block mb-8">Colaborador</span>
                            <span class="text-xs text-gray-500">(Firma de conformidad)</span>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Pantalla */
.page-content {
    width: 21.59cm;
    min-height: 27.94cm;
    padding: 2.5cm;
    background-color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Impresión */
@media print {
    @page {
        size: letter;
        margin: 2cm;
    }
    body {
        margin: 0;
        background: white;
    }
    .page-content {
        width: 100%;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>