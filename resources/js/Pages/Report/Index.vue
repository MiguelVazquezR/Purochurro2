<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'chart.js/auto';
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

// Definimos las props
const props = defineProps({
    filter: String,
    currentSales: Number,
    currentExpenses: Number,
    currentProfit: Number,
    averageTicket: Number,
    prevSales: Number,      
    prevExpenses: Number,   
    prevProfit: Number,     
    variations: Object,
    topProducts: Array,
    chartData: Object
});

// --- Referencias para la Gráfica ---
const chartRef = ref(null);
let chartInstance = null;

// --- ESTADOS PARA EL TOUR ---
const isLoadingTour = ref(false);
const isTourActive = ref(false);

// --- Helpers de Formato ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2
    }).format(value || 0);
};

const formatPercentage = (value) => {
    return `${Math.abs(value).toFixed(1)}%`;
};

// --- Lógica de Filtros ---
const filters = [
    { key: 'today', label: 'Hoy' },
    { key: 'week', label: 'Esta Semana' },
    { key: 'month', label: 'Este Mes' },
    { key: 'year', label: 'Este Año' },
];

const setFilter = (key) => {
    router.get(route('reports.index'), { filter: key }, {
        preserveState: true,
        preserveScroll: true,
        only: [
            'filter', 
            'currentSales', 'currentExpenses', 'currentProfit', 'averageTicket', 
            'prevSales', 'prevExpenses', 'prevProfit', 
            'variations', 'topProducts', 'chartData'
        ]
    });
};

// --- Lógica de Gráfica ---
const initChart = () => {
    if (chartInstance) {
        chartInstance.destroy();
    }

    if (!chartRef.value) return;

    const ctx = chartRef.value.getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.5)'); 
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.chartData.labels,
            datasets: [{
                label: 'Ventas',
                data: props.chartData.values,
                borderColor: '#6366f1', 
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: { size: 13 },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function(context) {
                            return formatCurrency(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [5, 5],
                        color: '#f3f4f6'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value; 
                        },
                        font: { size: 10 }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
};

// --- Lógica de Variaciones ---
const getTrendClass = (type, value) => {
    const isPositive = value >= 0;
    if (type === 'expenses') {
        return isPositive ? 'text-red-500 bg-red-50' : 'text-emerald-500 bg-emerald-50';
    }
    return isPositive ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
};

const getTrendIcon = (value) => {
    return value >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down';
};

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

    const tourDriver = driver({
        showProgress: true,
        allowClose: false,
        showButtons: ['next', 'previous'],
        doneBtnText: '¡Entendido!',
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        steps: [
            { 
                element: '#tour-reports-header', 
                popover: { 
                    title: 'Reportes Financieros', 
                    description: 'Esta sección te permite analizar la salud financiera de tu negocio. Puedes ver cómo han evolucionado tus ventas y gastos.',
                    side: "bottom",
                    align: 'start'
                } 
            },
            { 
                element: '#tour-time-filters', 
                popover: { 
                    title: 'Filtros de Tiempo', 
                    description: 'Cambia la vista para analizar el rendimiento de Hoy, la Semana, el Mes o todo el Año. Todos los datos se actualizarán automáticamente.',
                } 
            },
            { 
                element: '#tour-kpi-grid', 
                popover: { 
                    title: 'Indicadores Clave (KPIs)', 
                    description: 'Aquí ves el resumen: Ventas Totales, Gastos y Ganancia Neta. También te mostramos la comparación (en porcentaje) con el periodo anterior.',
                } 
            },
            { 
                element: '#tour-sales-chart', 
                popover: { 
                    title: 'Tendencia de Ventas', 
                    description: 'Esta gráfica te ayuda a identificar visualmente los picos y caídas en tus ventas durante el periodo seleccionado.',
                } 
            },
            { 
                element: '#tour-top-products', 
                popover: { 
                    title: 'Productos Estrella', 
                    description: 'Descubre cuáles son tus 5 productos más vendidos. Útil para tomar decisiones de inventario y promociones.',
                } 
            }
        ],
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
        await axios.post(route('tutorials.complete'), { module_name: 'financial_reports' });
    } catch (error) {
        console.error('No se pudo guardar el progreso del tutorial', error);
    }
};

// Lifecycle Hooks
onMounted(async () => {
    initChart();

    try {
        const response = await axios.get(route('tutorials.check', 'financial_reports'));
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

watch(() => props.chartData, () => {
    initChart();
});

onBeforeUnmount(() => {
    disableBlocking();
});
</script>

<template>
    <AppLayout title="Reportes Financieros">
        
        <!-- Overlay de Carga -->
        <div v-if="isLoadingTour" class="fixed inset-0 z-[9999] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center">
            <ProgressSpinner strokeWidth="4" animationDuration=".5s" />
            <p class="mt-4 text-gray-500 font-medium animate-pulse">Preparando sistema...</p>
        </div>

        <!-- Capa de Bloqueo -->
        <div v-if="isTourActive" class="fixed inset-0 z-[60] bg-transparent cursor-default"></div>

        <div class="py-8 transition-opacity duration-300"
             :class="{ '!pointer-events-none select-none': isTourActive }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header y Filtros -->
                <!-- ID TOUR: Header -->
                <div id="tour-reports-header" class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Reportes Financieros</h1>
                        <p class="text-sm text-gray-500 mt-1">Visión general del rendimiento del negocio.</p>
                    </div>

                    <!-- Filtro Tipo Segmented Control -->
                    <!-- ID TOUR: Filtros -->
                    <div id="tour-time-filters" class="bg-gray-100 p-1 rounded-lg inline-flex shadow-inner">
                        <button 
                            v-for="item in filters" 
                            :key="item.key"
                            @click="setFilter(item.key)"
                            class="px-4 py-1.5 text-sm font-medium rounded-md transition-all duration-200"
                            :class="filter === item.key 
                                ? 'bg-white text-gray-900 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                </div>

                <!-- Grid de KPIs -->
                <!-- ID TOUR: KPIs -->
                <div id="tour-kpi-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card: Ventas -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ventas Totales</p>
                                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(currentSales) }}</h3>
                            </div>
                            <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                <i class="pi pi-shopping-bag text-xl"></i>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold flex items-center gap-1" :class="getTrendClass('sales', variations.sales)">
                                <i :class="getTrendIcon(variations.sales)" style="font-size: 0.6rem;"></i>
                                {{ formatPercentage(variations.sales) }}
                            </span>
                            <span class="text-xs text-gray-400 whitespace-nowrap">vs {{ formatCurrency(prevSales) }}</span>
                        </div>
                    </div>

                    <!-- Card: Gastos -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Gastos</p>
                                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(currentExpenses) }}</h3>
                            </div>
                            <div class="p-2 bg-orange-50 rounded-lg text-orange-600">
                                <i class="pi pi-wallet text-xl"></i>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold flex items-center gap-1" :class="getTrendClass('expenses', variations.expenses)">
                                <i :class="getTrendIcon(variations.expenses)" style="font-size: 0.6rem;"></i>
                                {{ formatPercentage(variations.expenses) }}
                            </span>
                            <span class="text-xs text-gray-400 whitespace-nowrap">vs {{ formatCurrency(prevExpenses) }}</span>
                        </div>
                    </div>

                    <!-- Card: Ganancia -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ganancia Neta</p>
                                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(currentProfit) }}</h3>
                            </div>
                            <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                                <i class="pi pi-dollar text-xl"></i>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold flex items-center gap-1" :class="getTrendClass('profit', variations.profit)">
                                <i :class="getTrendIcon(variations.profit)" style="font-size: 0.6rem;"></i>
                                {{ formatPercentage(variations.profit) }}
                            </span>
                            <span class="text-xs text-gray-400 whitespace-nowrap">vs {{ formatCurrency(prevProfit) }}</span>
                        </div>
                    </div>

                    <!-- Card: Ticket Promedio -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ticket Promedio</p>
                                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(averageTicket) }}</h3>
                            </div>
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <i class="pi pi-users text-xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">Gasto promedio por venta</span>
                        </div>
                    </div>

                </div>

                <!-- Sección Principal: Gráfica y Top Productos -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Gráfica de Tendencias -->
                    <!-- ID TOUR: Chart -->
                    <div id="tour-sales-chart" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Tendencia de Ventas</h3>
                        <div class="relative h-80 w-full">
                            <canvas ref="chartRef"></canvas>
                        </div>
                    </div>

                    <!-- Top Productos -->
                    <!-- ID TOUR: Top Productos -->
                    <div id="tour-top-products" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Top 5 Productos</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-400 uppercase border-b border-gray-100">
                                    <tr>
                                        <th class="pb-3 font-semibold">Producto</th>
                                        <th class="pb-3 font-semibold text-right">Cant.</th>
                                        <th class="pb-3 font-semibold text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(product, index) in topProducts" :key="index" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3 font-medium text-gray-700">
                                            <div class="flex items-center gap-2">
                                                <span 
                                                    class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold"
                                                    :class="index === 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500'"
                                                >
                                                    {{ index + 1 }}
                                                </span>
                                                {{ product.name }}
                                            </div>
                                        </td>
                                        <td class="py-3 text-right text-gray-600">{{ product.total_qty }}</td>
                                        <td class="py-3 text-right font-semibold text-gray-800">{{ formatCurrency(product.total_money) }}</td>
                                    </tr>
                                    <tr v-if="topProducts.length === 0">
                                        <td colspan="3" class="py-4 text-center text-gray-400 italic">
                                            No hay ventas en este periodo
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Asegura que el canvas no se desborde */
canvas {
    max-width: 100%;
}
</style>