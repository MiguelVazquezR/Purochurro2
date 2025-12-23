<script setup>
import { Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    employee: Object,
    business: Object,
    period: Object, // { start: '...', end: '...' }
    date: String // Fecha actual formateada
});

const printDocument = () => {
    window.print();
};
</script>

<template>
    <Head title="Carta de recomendación" />

    <!-- 
      Corrección aplicada: 
      Se eliminaron los IDs y estilos complejos de visibilidad. 
      Al ser una página dedicada, basta con ocultar el botón y ajustar márgenes.
    -->
    <div class="min-h-screen bg-gray-100 p-8 print:p-0 print:m-0 print:bg-white font-serif text-gray-900">
        
        <!-- Barra de herramientas (Oculta al imprimir) -->
        <div class="max-w-[21.59cm] mx-auto mb-6 flex justify-between items-center print:hidden">
            <button 
                @click="printDocument" 
                class="bg-blue-700 text-white px-4 py-2 rounded shadow hover:bg-blue-800 font-sans font-bold flex items-center gap-2 transition-colors"
            >
                <i class="pi pi-print"></i> Imprimir carta
            </button>
            <span class="text-sm text-gray-500 font-sans">Formato Carta</span>
        </div>

        <!-- Documento -->
        <div class="page-content mx-auto bg-white print:w-full print:shadow-none">
            
            <!-- Encabezado: Logo y Fecha -->
             <div class="flex justify-between items-start mb-16">
                <!-- Logo -->
                <div class="w-32 print:w-32">
                    <ApplicationLogo class="w-full h-auto text-gray-800 fill-current" />
                </div>
                
                 <div class="text-right text-sm">
                     <p>Zapopan, Jalisco a {{ date }}</p>
                     <p class="font-bold mt-4 uppercase tracking-wide text-xs">Asunto: Carta de Recomendación</p>
                 </div>
             </div>

            <!-- Destinatario -->
            <div class="mb-12 font-bold uppercase text-sm">
                <p>A QUIEN CORRESPONDA:</p>
                <p>PRESENTE.</p>
            </div>

            <!-- Cuerpo de la Carta -->
            <div class="text-justify leading-loose space-y-4 text-sm text-black print:text-black">
                <p>
                    Por medio de la presente, me permito recomendar ampliamente al colaborador 
                    <strong>{{ employee.first_name }} {{ employee.last_name }}</strong>, 
                    quien prestó sus servicios en mi negocio comercial denominado <strong>"{{ business.name }}"</strong>.
                </p>

                <p>
                    Durante el tiempo que laboró con nosotros, comprendido del <strong>{{ period.start }}</strong> al <strong>{{ period.end }}</strong>, 
                    desempeñó el puesto de <strong>AYUDANTE GENERAL</strong>, demostrando ser una persona responsable, honesta, trabajadora y competente en las labores que le fueron encomendadas.
                </p>

                <p>
                    Durante su permanencia, observó siempre una buena conducta y un alto sentido de compromiso, por lo cual no tengo inconveniente alguno en recomendarlo(a) para cualquier actividad laboral que pretenda desempeñar, estando seguro de que cumplirá cabalmente con sus responsabilidades.
                </p>

                <p>
                    Extiendo la presente carta a petición del interesado(a) para los fines legales y personales que a él (ella) convengan.
                </p>
            </div>

            <!-- Despedida -->
            <div class="mt-12 text-center text-sm">
                <p>ATENTAMENTE</p>
            </div>

            <!-- Firma -->
            <div class="mt-16 pt-4 flex justify-center break-inside-avoid">
                <div class="text-center w-2/3 md:w-1/2">
                    <div class="border-t border-black pt-4">
                        <p class="font-bold uppercase text-sm">{{ business.rep }}</p>
                        <p class="text-xs text-gray-500 mt-1 uppercase">Propietario / Representante Legal</p>
                        <p class="font-bold uppercase text-sm mt-1">"{{ business.name }}"</p>
                    </div>
                </div>
            </div>

            <!-- Datos de Contacto (NUEVO) -->
            <div class="mt-8 text-center text-xs text-gray-500 print:text-gray-600">
                <p>Para cualquier duda o confirmación de la presente, favor de comunicarse al teléfono:</p>
                <p class="font-bold text-sm mt-1">33 1038 5768</p>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Estilos Base para Pantalla */
.page-content {
    width: 21.59cm; /* Ancho Carta */
    min-height: 22.94cm; /* Alto Carta */
    padding: 2.5cm; /* Márgenes visuales */
    background-color: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
}

/* Reglas de Impresión Simplificadas y Robustas */
@media print {
    @page {
        size: letter;
        margin: 2.5cm; /* Margen físico de la impresora */
    }

    body, html {
        background-color: white;
        height: auto;
        margin: 0;
        padding: 0;
    }

    /* Ocultamos elementos de UI explícitamente */
    .print\:hidden {
        display: none !important;
    }

    /* Ajustamos el contenedor principal */
    .page-content {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important; /* El margen ya lo maneja @page */
        box-shadow: none !important;
        border: none !important;
    }

    /* Forzamos color negro para evitar grises tenues */
    p, span, div, strong {
        color: #000 !important;
    }
}
</style>