<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';

import AdminDashboard from './AdminDashboard.vue';
import EmployeeDashboard from './EmployeeDashboard.vue';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const props = defineProps({
    isAdmin: Boolean,
    employee: Object, 
    stats: Object,
});

// --- ESTADOS PARA EL TOUR ---
const isLoadingTour = ref(false);
const isTourActive = ref(false);

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

    // Definimos pasos dinámicos según el rol
    let steps = [];

    if (props.isAdmin) {
        // --- Pasos para ADMINISTRADOR ---
        steps = [
            {
                element: '#tour-admin-header',
                popover: {
                    title: 'Panel de Control',
                    description: 'Bienvenido a tu centro de comando. Aquí tienes una visión general inmediata de tu negocio hoy.',
                    side: "bottom",
                    align: 'start'
                }
            },
            {
                element: '#tour-admin-stats',
                popover: {
                    title: 'Métricas Clave',
                    description: 'Revisa las ventas netas del día, gastos registrados y el estado de las solicitudes pendientes de tus empleados.',
                }
            },
            {
                element: '#tour-admin-attendance',
                popover: {
                    title: 'Control de Asistencia',
                    description: 'Monitorea en tiempo real quién está trabajando, quién falta por llegar y quién se encuentra de vacaciones.',
                }
            },
            {
                element: '#tour-admin-inventory',
                popover: {
                    title: 'Inventario Crítico',
                    description: 'Atención aquí: Lista de productos con bajo stock que requieren resurtido urgente.',
                }
            },
            {
                element: '#tour-birthdays',
                popover: {
                    title: 'Cumpleaños',
                    description: 'No olvides felicitar a tu equipo. Aquí verás los próximos cumpleaños.',
                }
            }
        ];
    } else {
        // --- Pasos para EMPLEADO ---
        steps = [
            {
                element: '#tour-emp-welcome',
                popover: {
                    title: 'Mi Espacio',
                    description: 'Bienvenido a tu portal personal. Aquí encontrarás todo lo relacionado con tu trabajo y asistencia.',
                    side: "bottom",
                    align: 'start'
                }
            },
            {
                element: '#tour-emp-shift',
                popover: {
                    title: 'Próximo Turno',
                    description: 'Consulta rápidamente cuándo te toca trabajar y accede a tu horario semanal completo.',
                }
            },
            {
                element: '#tour-emp-payroll',
                popover: {
                    title: 'Nómina Estimada',
                    description: 'Lleva el control de tus ganancias. Aquí verás un estimado de lo que llevas acumulado en la semana actual.',
                }
            },
            {
                element: '#tour-emp-vacation',
                popover: {
                    title: 'Mis Vacaciones',
                    description: 'Este es tu saldo de días disponibles. Desde aquí puedes ir directamente a solicitar un descanso.',
                }
            },
            {
                element: '#tour-birthdays',
                popover: {
                    title: 'Celebraciones',
                    description: 'Entérate de los próximos cumpleaños de tus compañeros.',
                }
            }
        ];
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
        await axios.post(route('tutorials.complete'), { module_name: 'dashboard' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('tutorials.check', 'dashboard'));
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
    <AppLayout title="Dashboard">
            <h2 id="tour-admin-header" v-if="isAdmin" class="font-semibold text-xl text-gray-800 leading-tight">
                Panel de Control
            </h2>
            <h2 id="tour-emp-header" v-else class="font-semibold text-xl text-gray-800 leading-tight">
                Mi Espacio
            </h2>

        <!-- Overlay de Carga -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa de Bloqueo -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="py-12 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <AdminDashboard v-if="isAdmin" :stats="stats" />
                
                <EmployeeDashboard v-else :employee="employee" :stats="stats" />

            </div>
        </div>
    </AppLayout>
</template>