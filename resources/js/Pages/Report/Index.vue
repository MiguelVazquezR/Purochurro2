<script setup>
import { ref, onMounted, watch, onBeforeUnmount, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'chart.js/auto';
import axios from 'axios';
import ProgressSpinner from 'primevue/progressspinner';
import DatePicker from 'primevue/datepicker'; 
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import InputNumber from 'primevue/inputnumber'; 

// --- IMPORTAR DRIVER.JS PARA EL TOUR ---
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

// Definimos las props
const props = defineProps({
    filter: String,
    customStart: String, 
    customEnd: String,   
    discount: Number, 
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

// --- ESTADOS PARA FECHAS Y DESCUENTO ---
const customRange = ref(null);
const op = ref(); 
const opDiscount = ref(); 
const discountValue = ref(props.discount || 0); 

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
    { key: 'last_3_months', label: 'Últimos 3 Meses' },
    { key: 'year', label: 'Este Año' },
    { key: 'previous_year', label: 'Año Anterior' },
];

const currentLabel = computed(() => {
    if (props.filter === 'custom' && props.customStart && props.customEnd) {
        const start = new Date(props.customStart).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
        const end = new Date(props.customEnd).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' });
        return `${start} - ${end}`;
    }
    const active = filters.find(f => f.key === props.filter);
    return active ? active.label : 'Personalizado';
});

const setFilter = (key) => {
    router.get(route('reports.index'), { filter: key, discount: discountValue.value }, {
        preserveState: true,
        preserveScroll: true,
        only: [
            'filter', 'customStart', 'customEnd', 'discount',
            'currentSales', 'currentExpenses', 'currentProfit', 'averageTicket', 
            'prevSales', 'prevExpenses', 'prevProfit', 
            'variations', 'topProducts', 'chartData'
        ]
    });
};

const toggleCustomDate = (event) => {
    op.value.toggle(event);
};

const applyCustomRange = () => {
    if (!customRange.value || customRange.value.length < 2 || !customRange.value[0] || !customRange.value[1]) return;

    const start = customRange.value[0].toLocaleDateString('en-CA'); 
    const end = customRange.value[1].toLocaleDateString('en-CA');

    router.get(route('reports.index'), { 
        filter: 'custom', 
        start_date: start, 
        end_date: end,
        discount: discountValue.value
    }, {
        preserveState: true,
        preserveScroll: true,
    });
    
    op.value.hide();
};

// --- Lógica del Descuento ---
const toggleDiscount = (event) => {
    opDiscount.value.toggle(event);
};

const applyDiscount = () => {
    let params = { 
        filter: props.filter,
        discount: discountValue.value || 0
    };
    
    if (props.filter === 'custom') {
        params.start_date = props.customStart;
        params.end_date = props.customEnd;
    }

    router.get(route('reports.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: [
            'filter', 'customStart', 'customEnd', 'discount',
            'currentSales', 'currentExpenses', 'currentProfit', 'averageTicket', 
            'prevSales', 'prevExpenses', 'prevProfit', 
            'variations', 'topProducts', 'chartData'
        ]
    });

    opDiscount.value.hide();
};

// --- Lógica de Gráfica ---

// Plugin personalizado para mostrar los valores encima y debajo de los puntos
const alwaysShowDataLabels = {
    id: 'alwaysShowDataLabels',
    afterDatasetsDraw(chart) {
        const { ctx, data } = chart;
        ctx.save();
        ctx.font = 'bold 11px sans-serif';
        ctx.textAlign = 'center';
        
        const dataset = data.datasets[0];
        const meta = chart.getDatasetMeta(0);
        
        meta.data.forEach((bar, index) => {
            const value = dataset.data[index];
            if (value > 0) { // Solo mostrar mayores a 0 para mantener limpio
                const formatted = new Intl.NumberFormat('es-MX', { 
                    style: 'currency', 
                    currency: 'MXN', 
                    maximumFractionDigits: 0 
                }).format(value);
                
                ctx.fillStyle = '#6366f1'; // Color indigo
                
                // Alternar la posición del texto: Par = Arriba, Impar = Abajo
                if (index % 2 === 0) {
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(formatted, bar.x, bar.y - 10);
                } else {
                    ctx.textBaseline = 'top';
                    ctx.fillText(formatted, bar.x, bar.y + 10);
                }
            }
        });
        
        ctx.restore();
    }
};

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
        plugins: [alwaysShowDataLabels], // Inyectamos nuestro plugin personalizado
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 25, // Damos espacio arriba para que quepan los números
                    bottom: 25 // Damos espacio abajo para los números alternados
                }
            },
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
                    title: 'Reportes financieros', 
                    description: 'Esta sección te permite analizar la salud financiera de tu negocio. Puedes ver cómo han evolucionado tus ventas y gastos.',
                    side: "bottom",
                    align: 'start'
                } 
            },
            { 
                element: '#tour-time-filters', 
                popover: { 
                    title: 'Filtros de tiempo', 
                    description: 'Usa estos botones para cambiar rápidamente el periodo. Puedes elegir rangos como "Hoy", "Este Mes" o "Año Anterior".',
                } 
            },
            { 
                element: '#tour-custom-date', 
                popover: { 
                    title: 'Fechas personalizadas', 
                    description: 'Si necesitas un rango específico (ej. del 15 al 20 de Enero), usa el botón de calendario "Personalizado".',
                } 
            },
            { 
                element: '#tour-discount', 
                popover: { 
                    title: 'Descuento Global', 
                    description: 'Puedes ingresar un porcentaje aquí para descontarlo automáticamente de todos los montos mostrados en tu reporte.',
                } 
            },
            { 
                element: '#tour-kpi-grid', 
                popover: { 
                    title: 'Indicadores clave (KPIs)', 
                    description: 'Aquí ves el resumen: Ventas Totales, Gastos y Ganancia Neta. También te mostramos la comparación (en porcentaje) con el periodo anterior.',
                } 
            },
            { 
                element: '#tour-sales-chart', 
                popover: { 
                    title: 'Tendencia de ventas', 
                    description: 'Esta gráfica te ayuda a identificar visualmente los picos y caídas en tus ventas durante el periodo seleccionado.',
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
                <div id="tour-reports-header" class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-8 gap-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Reportes Financieros</h1>
                        <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                            <i class="pi pi-calendar text-indigo-500"></i>
                            Viendo: <span class="font-bold text-gray-700">{{ currentLabel }}</span>
                        </p>
                    </div>

                    <!-- Contenedor de Botones -->
                    <div class="flex flex-wrap gap-2 items-center">
                        <div id="tour-time-filters" class="bg-gray-100 p-1 rounded-lg inline-flex shadow-inner overflow-x-auto max-w-full">
                            <button 
                                v-for="item in filters" 
                                :key="item.key"
                                @click="setFilter(item.key)"
                                class="px-3 py-1.5 text-xs sm:text-sm font-medium rounded-md transition-all duration-200 whitespace-nowrap"
                                :class="filter === item.key 
                                    ? 'bg-white text-indigo-600 shadow-sm font-bold' 
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                            >
                                {{ item.label }}
                            </button>
                        </div>

                        <!-- Botón Rango Personalizado -->
                        <div id="tour-custom-date">
                            <Button 
                                icon="pi pi-calendar-plus" 
                                :label="filter === 'custom' ? 'Rango' : ''" 
                                @click="toggleCustomDate" 
                                severity="secondary" 
                                outlined 
                                class="!border-gray-300 !text-gray-600 hover:!bg-gray-50"
                                :class="filter === 'custom' ? '!bg-indigo-50 !border-indigo-200 !text-indigo-700' : ''"
                            />
                            <Popover ref="op">
                                <div class="flex flex-col gap-4 p-2">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-gray-700">Selecciona un rango</label>
                                        <DatePicker v-model="customRange" selectionMode="range" :manualInput="false" inline class="w-full" />
                                    </div>
                                    <Button label="Aplicar Filtro" size="small" @click="applyCustomRange" :disabled="!customRange || customRange.length < 2" />
                                </div>
                            </Popover>
                        </div>

                        <!-- Botón Descuento -->
                        <div id="tour-discount">
                            <Button 
                                icon="pi pi-percentage" 
                                :label="discount > 0 ? `-${discount}%` : 'Descuento'" 
                                @click="toggleDiscount" 
                                severity="secondary" 
                                outlined 
                                class="!border-gray-300 !text-gray-600 hover:!bg-gray-50"
                                :class="discount > 0 ? '!bg-amber-50 !border-amber-200 !text-amber-700' : ''"
                            />
                            <Popover ref="opDiscount">
                                <div class="flex flex-col gap-4 p-2 w-56">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-gray-700">Descontar porcentaje a todos los montos</label>
                                        <InputNumber 
                                            v-model="discountValue" 
                                            inputId="percent" 
                                            prefix="%" 
                                            :min="0" 
                                            :max="100" 
                                            placeholder="Ej. 10" 
                                            class="w-full" 
                                        />
                                    </div>
                                    <Button label="Aplicar Descuento" size="small" @click="applyDiscount" />
                                </div>
                            </Popover>
                        </div>
                    </div>
                </div>

                <!-- Grid de KPIs -->
                <div id="tour-kpi-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                    
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

                <!-- Sección Principal: Gráfica a todo lo ancho -->
                <div id="tour-sales-chart" class="w-full bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 shrink-0">Tendencia de Ventas</h3>
                    <div class="relative flex-1 w-full min-h-[350px]">
                        <canvas ref="chartRef"></canvas>
                    </div>
                </div>

                <!-- Sección Inferior: Desglose y Top Productos (Mitad y Mitad) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Tabla: Desglose de Puntos -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col max-h-[400px]">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 shrink-0">Desglose de Ventas</h3>
                        <div class="overflow-y-auto custom-scrollbar flex-1 pr-2">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-400 uppercase border-b border-gray-100 sticky top-0 bg-white z-10">
                                    <tr>
                                        <th class="pb-3 font-semibold">Periodo</th>
                                        <th class="pb-3 font-semibold text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="(val, index) in chartData.values" :key="index" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-2.5 font-medium text-gray-700">{{ chartData.labels[index] }}</td>
                                        <td class="py-2.5 text-right font-semibold text-gray-900">{{ formatCurrency(val) }}</td>
                                    </tr>
                                    <tr v-if="!chartData.values || chartData.values.length === 0">
                                        <td colspan="2" class="py-4 text-center text-gray-400 italic">No hay datos en este periodo</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Productos -->
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
                                                    class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold shrink-0"
                                                    :class="index === 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500'"
                                                >
                                                    {{ index + 1 }}
                                                </span>
                                                <span class="truncate">{{ product.name }}</span>
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
canvas {
    max-width: 100%;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; 
}
</style>