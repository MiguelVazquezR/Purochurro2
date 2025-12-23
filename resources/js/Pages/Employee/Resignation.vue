<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    business: Object,
    date: String // Fecha formateada: 12 de Diciembre de 2024
});

const printDocument = () => {
    window.print();
};
</script>

<template>
    <Head title="Carta de Renuncia" />

    <div class="min-h-screen bg-gray-100 p-8 print:p-0 print:bg-white font-serif text-gray-900">
        
        <!-- Barra de herramientas (Pantalla) -->
        <div class="max-w-[21.59cm] mx-auto mb-6 flex justify-between items-center print:hidden">
            <button 
                @click="printDocument" 
                class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 font-sans font-bold flex items-center gap-2"
            >
                <i class="pi pi-print"></i> Imprimir carta
            </button>
            <span class="text-sm text-gray-500 font-sans">Formato carta</span>
        </div>

        <!-- Documento -->
        <div class="page-content mx-auto bg-white">
            
            <!-- Encabezado (Fecha y Lugar) -->
            <div class="text-right mb-12">
                <p>Zapopan, Jalisco a {{ date }}</p>
            </div>

            <!-- Destinatario -->
            <div class="mb-12 font-bold uppercase">
                <p>A QUIEN CORRESPONDA:</p>
                <p>PRESENTE.</p>
            </div>

            <!-- Cuerpo de la Renuncia -->
            <div class="text-justify leading-loose space-y-6 text-base">
                <p>
                    Por medio de la presente, comunico a usted mi decisión de presentar mi <strong>RENUNCIA VOLUNTARIA</strong> con carácter de irrevocable al puesto de 
                    <strong>AYUDANTE GENERAL</strong> que venía desempeñando en el establecimiento comercial denominado <strong>"{{ business.name }}"</strong>.
                </p>

                <p>
                    Hago constar que durante el tiempo que presté mis servicios, siempre fui tratado con dignidad y respeto, y que se me cubrieron puntualmente todas mis prestaciones de ley, salarios devengados, horas extras, días de descanso obligatorio, vacaciones, prima vacacional y aguinaldo, por lo que no me reservo acción ni derecho alguno que ejercitar en su contra, ni en contra de quien represente sus intereses, otorgando el más amplio finiquito que en derecho proceda.
                </p>

                <p>
                    Asimismo, manifiesto que no sufrí riesgo ni enfermedad de trabajo alguno durante el desempeño de mis labores.
                </p>

                <p>
                    Agradezco la oportunidad que se me brindó de laborar en su empresa y las atenciones recibidas durante mi estancia.
                </p>
            </div>

            <!-- Despedida -->
            <div class="mt-12 text-center">
                <p>ATENTAMENTE</p>
            </div>

            <!-- Firma -->
            <div class="mt-24 pt-4 flex justify-center">
                <div class="text-center w-1/2 border-t border-black pt-4">
                    <p class="font-bold uppercase">{{ employee.first_name }} {{ employee.last_name }}</p>
                    <p class="text-sm text-gray-500 mt-1">FIRMA DE RATIFICACIÓN</p>
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
    padding: 3cm 2.5cm; /* Márgenes superiores para carta formal */
    background-color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Impresión */
@media print {
    @page {
        size: letter;
        margin: 2.5cm;
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