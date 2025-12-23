<script setup>
import { Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    employee: Object,
    type: String, // 'training', 'seasonal', 'indefinite'
    shifts: Array,
    business: Object,
    dates: Object, // { start: '...', end: '...', today: '...' }
    season_name: String
});

// Títulos según el tipo
const titles = {
    training: 'CONTRATO INDIVIDUAL DE TRABAJO POR CAPACITACIÓN INICIAL',
    seasonal: 'CONTRATO INDIVIDUAL DE TRABAJO POR TIEMPO DETERMINADO (TEMPORADA)',
    indefinite: 'CONTRATO INDIVIDUAL DE TRABAJO POR TIEMPO INDETERMINADO',
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);
};

const printContract = () => {
    window.print();
};
</script>

<template>
    <Head :title="titles[type]" />

    <div class="main-container font-serif text-justify text-gray-900 leading-relaxed text-sm">
        
        <!-- Barra de herramientas (Oculta al imprimir) -->
        <div class="print:hidden bg-gray-100 p-4 mb-4 border-b flex justify-between items-center sticky top-0 z-50">
            <h2 class="font-bold text-gray-700">Vista previa del contrato</h2>
            <button 
                @click="printContract" 
                class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 font-sans font-bold flex items-center gap-2"
            >
                <i class="pi pi-print"></i> Imprimir / Guardar PDF
            </button>
        </div>

        <!-- Documento -->
        <div class="page-content mx-auto bg-white">
            
            <!-- Encabezado -->
            <div class="text-center mb-6">
                <ApplicationLogo class="h-10 w-auto mx-auto mb-2" />
                <h1 class="font-bold text-base uppercase">{{ titles[type] }}</h1>
            </div>

            <p class="mb-4">
                Que celebran por una parte la empresa <strong>“{{ business.name }}”</strong> representada en este acto por <strong>{{ business.rep }}</strong>, 
                con domicilio en {{ business.address }}, en adelante denominado "El Empleador", y por la otra parte el (la) trabajador(a) 
                <strong>{{ employee.first_name }} {{ employee.last_name }}</strong>, con domicilio en {{ employee.address }}, 
                en adelante denominado "El (La) Trabajador (a)", al tenor de las siguientes declaraciones y cláusulas:
            </p>

            <h3 class="font-bold uppercase text-sm mb-2">DECLARACIONES:</h3>
            <ul class="list-none space-y-2 mb-4">
                <li>
                    <strong>El Empleador</strong> manifiesta que es propietario del negocio de venta churros rellenos y botanas ubicado en la dirección mencionada anteriormente y que requiere de personal para desempeñar diversas funciones relacionadas con la atención al cliente y la cocina.
                </li>
                <li>
                    <strong>El (La) Trabajador(a)</strong> manifiesta que cuenta con la disposición y aptitudes necesarias para llevar a cabo las funciones asignadas por El Empleador y está dispuesto a someterse a 
                    <span v-if="type === 'training'">un período de capacitación inicial.</span>
                    <span v-else-if="type === 'seasonal'">un contrato por tiempo determinado por temporada.</span>
                    <span v-else>una relación de trabajo por tiempo indeterminado.</span>
                </li>
            </ul>

            <h3 class="font-bold uppercase text-center text-sm my-4">CLÁUSULAS:</h3>

            <div class="space-y-4">
                <p>
                    <strong>PRIMERA: OBJETO DEL CONTRATO.</strong> El presente contrato tiene por objeto establecer las condiciones bajo las cuales El (La) Trabajador(a) prestará sus servicios a El Empleador, específicamente 
                    <span v-if="type === 'training'">durante un período de capacitación inicial.</span>
                    <span v-else-if="type === 'seasonal'">durante la temporada <strong>{{ season_name }}</strong>.</span>
                    <span v-else>por tiempo indeterminado.</span>
                </p>

                <p>
                    <strong>SEGUNDA: DURACIÓN.</strong> 
                    <span v-if="type === 'training'">
                        La duración de la capacitación inicial será de 30 días, durante los cuales El (La) Trabajador(a) deberá adquirir los conocimientos y habilidades necesarios para desempeñar las funciones asignadas. 
                        Iniciando su vigencia el día <strong>{{ dates.start }}</strong> y terminando el <strong>{{ dates.end }}</strong>.
                    </span>
                    <span v-else-if="type === 'seasonal'">
                        La duración del presente contrato por temporada será determinada por la necesidad extraordinaria del servicio, 
                        iniciando su vigencia el día <strong>{{ dates.start }}</strong> y terminando el <strong>{{ dates.end }}</strong>.
                    </span>
                    <span v-else>
                        La relación de trabajo será por tiempo indeterminado, iniciando su vigencia el día <strong>{{ dates.start }}</strong>.
                    </span>
                </p>

                <p>
                    <strong>TERCERA: JORNADA DE TRABAJO.</strong> 
                    El (La) Trabajador(a) se sujetará al horario de labores que El empleador le designe. Este horario será determinado según los turnos disponibles en el negocio. Los turnos que se manejan en el negocio son los siguientes:
                    <ul class="list-disc list-inside ml-4 mt-1 mb-2">
                        <li v-for="shift in shifts" :key="shift.id">
                            {{ shift.name }}: {{ shift.start_time.substring(0,5) }} a {{ shift.end_time.substring(0,5) }} hrs.
                        </li>
                    </ul>
                    En ocasiones, El (La) Trabajador(a) podrá ser requerido para trabajar tiempo extra, previo acuerdo con El Empleador.
                    <br>
                    El (La) Trabajador(a) prestará sus servicios en el lugar que le señale El Empleador, comprometiéndose a registrar el inicio y término de la jornada laboral en el sistema interno establecido.
                    <br>
                    El (La) Trabajador(a) reconoce la importancia de la puntualidad. En caso de faltas o retardos sin causa justificada, El Empleador se reserva el derecho de aplicar sanciones proporcionales (descuentos salariales, advertencias o rescisión en casos reiterados).
                </p>

                <p>
                    <strong>CUARTA: SALARIO Y COMISIONES.</strong>
                    El salario diario de El (La) Trabajador(a) será de <strong>{{ formatCurrency(employee.base_salary) }}</strong> el turno base.
                    El (la) Trabajador(a) tendrá derecho a percibir comisiones por turno trabajado calculadas en base a las ventas realizadas. 
                    El pago se realizará los días Domingo de cada mes en Efectivo.
                    <br>
                    Los días festivos no se laborarán; sin embargo, en caso de trabajar, se pagará el doble del salario normal por las horas laboradas ese día.
                    <br>
                    El (La) Trabajador(a) disfrutará de vacaciones, prima vacacional, descanso semanal y aguinaldo de acuerdo a la jornada laboral y al salario que perciba, tomando en cuenta el tiempo efectivamente trabajado.
                </p>

                <p>
                    <strong>QUINTA: ROLES Y CAMBIOS DE FUNCIÓN.</strong>
                    El Empleador se reserva el derecho de asignar a Él (La) Trabajador(a) funciones específicas, ya sea en cocina, atención al cliente o cualquier otra actividad propia que se relacione con la empresa, según las necesidades del negocio.
                </p>

                <p>
                    <strong>SEXTA: REGLAMENTO INTERIOR DE TRABAJO.</strong>
                    El (La) Trabajador(a) manifiesta que conoce el reglamento interior de trabajo que rige a la empresa y se obliga a observarlo en todas y cada una de sus partes.
                </p>

                <p>
                    <strong>SÉPTIMA: TERMINACIÓN DEL CONTRATO.</strong>
                    <span v-if="type === 'training'">
                        Al término de la capacitación inicial, El Empleador evaluará el desempeño de El (La) Trabajador(a) y decidirá la continuación de la relación laboral. Si los servicios no resultasen eficientes, se podrá rescindir del presente contrato sin responsabilidad para El Empleador.
                    </span>
                    <span v-else>
                        La terminación de la relación laboral se sujetará a lo dispuesto por la Ley Federal del Trabajo.
                    </span>
                    En caso de concluir la relación laboral, El Empleador liquidará lo correspondiente a las partes proporcionales de aguinaldo, vacaciones y prima vacacional.
                </p>
            </div>

            <!-- Firmas -->
            <div class="mt-12 break-inside-avoid">
                <p class="text-center mb-16">
                    Leído que fue el presente contrato y enteradas las partes de su contenido y alcance legal, lo firman en 
                    <strong>Zapopan, Jalisco</strong> a los <strong>{{ dates.today }}</strong>.
                </p>

                <div class="flex justify-between items-end px-4">
                    <div class="text-center w-5/12">
                        <div class="border-t border-black pt-2">
                            <strong>{{ business.rep }}</strong><br>
                            <span class="text-xs uppercase">El Empleador</span>
                        </div>
                    </div>

                    <div class="text-center w-5/12">
                        <div class="border-t border-black pt-2">
                            <strong>{{ employee.first_name }} {{ employee.last_name }}</strong><br>
                            <span class="text-xs uppercase">El (La) Trabajador(a)</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Estilos para Pantalla */
.main-container {
    width: 100%;
    background-color: #f3f4f6;
    padding-bottom: 2rem;
}

.page-content {
    width: 21.59cm; /* Ancho Carta Exacto */
    min-height: 27.94cm; /* Alto Carta Mínimo */
    padding: 2.5cm 2.5cm; /* Márgenes visuales en pantalla */
    margin: 0 auto;
    background-color: white;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Estilos de Impresión Estrictos */
@media print {
    @page {
        size: letter;
        margin: 2cm; /* Margen físico de la impresora */
    }

    body {
        margin: 0;
        padding: 0;
        background: white;
    }

    .main-container {
        background: white;
        padding: 0;
    }

    .page-content {
        width: 100%;
        margin: 0;
        padding: 0; /* Quitamos padding interno porque ya usamos margin en @page */
        box-shadow: none;
    }

    /* Ocultar elementos de UI */
    .print\:hidden {
        display: none !important;
    }
}
</style>