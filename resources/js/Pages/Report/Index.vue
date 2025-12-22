<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'chart.js/auto';

// Definimos las props, incluyendo las nuevas para los montos anteriores
const props = defineProps({
    filter: String,
    currentSales: Number,
    currentExpenses: Number,
    currentProfit: Number,
    averageTicket: Number,
    prevSales: Number,      // Nuevo prop
    prevExpenses: Number,   // Nuevo prop
    prevProfit: Number,     // Nuevo prop
    variations: Object,
    topProducts: Array,
    chartData: Object
});

// --- Referencias para la Gráfica ---
const chartRef = ref(null);
let chartInstance = null;

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
    
    // Crear degradado estilo Mac/iOS
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.5)'); // Indigo 500
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.chartData.labels,
            datasets: [{
                label: 'Ventas',
                data: props.chartData.values,
                borderColor: '#6366f1', // Indigo 500
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 // Curva suave
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
                            return '$' + value; // Simplificado para eje Y
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

// Lifecycle Hooks
onMounted(() => {
    initChart();
});

// Observar cambios en los datos (por si se cambia el filtro) para redibujar
watch(() => props.chartData, () => {
    initChart();
});

// --- Lógica de Variaciones (Colores e Iconos) ---
const getTrendClass = (type, value) => {
    const isPositive = value >= 0;
    // Para gastos: Subir es malo (Rojo), Bajar es bueno (Verde)
    if (type === 'expenses') {
        return isPositive ? 'text-red-500 bg-red-50' : 'text-emerald-500 bg-emerald-50';
    }
    // Para ventas/ganancia: Subir es bueno (Verde), Bajar es malo (Rojo)
    return isPositive ? 'text-emerald-500 bg-emerald-50' : 'text-red-500 bg-red-50';
};

const getTrendIcon = (value) => {
    return value >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down';
};
</script>

<template>
    <AppLayout title="Reportes Financieros">
        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Header y Filtros -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Reportes Financieros</h1>
                        <p class="text-sm text-gray-500 mt-1">Visión general del rendimiento del negocio.</p>
                    </div>

                    <!-- Filtro Tipo Segmented Control (Mac Style) -->
                    <div class="bg-gray-100 p-1 rounded-lg inline-flex shadow-inner">
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
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
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Tendencia de Ventas</h3>
                        <div class="relative h-80 w-full">
                            <canvas ref="chartRef"></canvas>
                        </div>
                    </div>

                    <!-- Top Productos -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
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