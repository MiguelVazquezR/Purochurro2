<script setup>
import { computed, ref, watchEffect } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    visible: Boolean, // Para móvil (Drawer)
    collapsed: Boolean, // Para desktop (Mini mode)
    desktopVisible: Boolean // NUEVO: Para ocultar completamente en desktop
});

const emit = defineEmits(['update:visible', 'toggle-collapsed']);

const page = usePage();
const user = page.props.auth.user;
const isAdmin = computed(() => user.id === 1);

const expandedGroups = ref({});
const showInfoModal = ref(false); // Control del modal de información

// Lógica de Menú
const menuItems = computed(() => {
    const nominaSubItems = [
        { label: 'Periodos de nómina', route: 'payroll.index', icon: 'pi pi-calendar', visible: true },
        { label: 'Turnos', route: 'shifts.index', icon: 'pi pi-clock', visible: isAdmin.value },
        { label: 'Horarios', route: 'schedule.index', icon: 'pi pi-calendar', visible: isAdmin.value },
        { label: 'Bonos', route: 'bonuses.index', icon: 'pi pi-star', visible: isAdmin.value },
        { label: 'Días festivos', route: 'holidays.index', icon: 'pi pi-calendar-plus', visible: isAdmin.value },
    ].filter(item => item.visible);

    const items = [
        { label: 'Inicio', route: 'dashboard', icon: 'pi pi-home', visible: true },
        { label: 'Punto de venta', route: 'pos.index', icon: 'pi pi-shopping-bag', visible: true },
        { label: 'Reportes', route: 'reports.index', icon: 'pi pi-chart-bar', visible: isAdmin.value },
        { label: 'Usuarios', route: 'employees.index', icon: 'pi pi-users', visible: isAdmin.value },
        { 
            label: 'Nóminas', 
            icon: 'pi pi-wallet', 
            visible: nominaSubItems.length > 0,
            items: nominaSubItems 
        },
        { label: 'Gastos', route: 'expenses.index', icon: 'pi pi-dollar', visible: isAdmin.value },
        { label: 'Ventas', route: 'sales.index', icon: 'pi pi-chart-line', visible: isAdmin.value },
        { label: 'Productos', route: 'products.index', icon: 'pi pi-box', visible: true },
        { label: 'Permisos', route: 'incident-requests.index', icon: 'pi pi-id-card', visible: true },
    ];

    return items.filter(item => item.visible);
});

const isRouteActive = (routeName) => {
    if (!routeName) return false;
    if (routeName.endsWith('.index')) {
        const baseRoute = routeName.replace('.index', '');
        return route().current(`${baseRoute}*`);
    }
    return route().current(`${routeName}*`);
};

const toggleGroup = (item) => {
    if (props.collapsed) {
        emit('toggle-collapsed');
        setTimeout(() => {
            expandedGroups.value[item.label] = true;
        }, 150);
    } else {
        expandedGroups.value[item.label] = !expandedGroups.value[item.label];
    }
};

watchEffect(() => {
    menuItems.value.forEach(item => {
        if (item.items) {
            const hasActiveChild = item.items.some(sub => isRouteActive(sub.route));
            if (hasActiveChild) {
                expandedGroups.value[item.label] = true;
            }
        }
    });
});
</script>

<template>
    <!-- MÓVIL: Drawer (Sin cambios mayores, solo se usa en md:hidden) -->
    <Drawer 
        :visible="visible" 
        @update:visible="$emit('update:visible', $event)" 
        header="Menú"
        class="md:hidden"
        :pt="{ 
            root: { class: '!bg-surface-0/95 !backdrop-blur-xl !border-r !border-surface-200 !w-72' }, 
            header: { class: '!pb-2' }
        }"
    >
        <div class="flex flex-col gap-1">
            <template v-for="(item, index) in menuItems" :key="index">
                <!-- Móvil: Item Simple -->
                <Link 
                    v-if="!item.items" 
                    :href="route(item.route)" 
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group"
                    :class="isRouteActive(item.route) ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-surface-600 hover:bg-surface-100'"
                    @click="$emit('update:visible', false)"
                >
                    <i :class="[item.icon, 'text-lg']"></i>
                    <span>{{ item.label }}</span>
                </Link>

                <!-- Móvil: Grupo -->
                <div v-else class="flex flex-col gap-1">
                    <button 
                        @click="toggleGroup(item)"
                        class="flex items-center justify-between w-full px-4 py-3 rounded-xl transition-all duration-200 text-surface-600 hover:bg-surface-100"
                    >
                        <div class="flex items-center gap-3">
                            <i :class="[item.icon, 'text-lg']"></i>
                            <span>{{ item.label }}</span>
                        </div>
                        <i class="pi pi-chevron-down text-xs transition-transform duration-300" :class="{ 'rotate-180': expandedGroups[item.label] }"></i>
                    </button>
                    
                    <div 
                        class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                        :class="expandedGroups[item.label] ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                    >
                        <div class="overflow-hidden">
                            <div class="flex flex-col gap-1 pl-4 border-l-2 border-surface-100 ml-6 pb-2 transition-opacity duration-300"
                                :class="expandedGroups[item.label] ? 'opacity-100' : 'opacity-0'"
                            >
                                <Link 
                                    v-for="(subItem, subIndex) in item.items"
                                    :key="subIndex"
                                    :href="route(subItem.route)"
                                    class="flex items-center gap-3 px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                                    :class="isRouteActive(subItem.route) ? 'text-orange-600 font-medium bg-orange-50' : 'text-surface-500 hover:text-surface-900'"
                                    @click="$emit('update:visible', false)"
                                >
                                    <i :class="[subItem.icon, 'text-xs']"></i>
                                    <span>{{ subItem.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </Drawer>

    <!-- DESKTOP: Sidebar Fijo Dinámico -->
    <aside 
        class="hidden md:flex flex-col fixed left-0 top-16 bottom-0 bg-white/80 backdrop-blur-xl border-r border-surface-200 z-40 transition-all duration-300 ease-in-out"
        :class="[
            collapsed ? 'w-20' : 'w-64',
            desktopVisible ? 'translate-x-0' : '-translate-x-full'
        ]"
    >
        <!-- Botón de Colapso (Mini Mode) -->
        <div class="absolute -right-3 top-4 z-50" v-if="desktopVisible">
            <button 
                @click="$emit('toggle-collapsed')"
                class="w-6 h-6 bg-white border border-surface-200 rounded-full shadow-sm flex items-center justify-center text-surface-500 hover:text-orange-600 hover:border-orange-200 transition-colors focus:outline-none transform hover:scale-110 duration-200"
                title="Minimizar menú"
            >
                <i class="pi pi-angle-left text-xs transition-transform duration-300" :class="{ 'rotate-180': collapsed }"></i>
            </button>
        </div>

        <div class="flex flex-col gap-1 p-3 overflow-y-auto custom-scrollbar flex-1">
            <template v-for="(item, index) in menuItems" :key="index">
                
                <!-- Desktop: Item Simple -->
                <Link 
                    v-if="!item.items" 
                    :href="route(item.route)" 
                    class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group relative overflow-hidden"
                    :class="[
                        isRouteActive(item.route) ? 'bg-orange-50 text-orange-700' : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900',
                        collapsed ? 'justify-center' : ''
                    ]"
                    :title="collapsed ? item.label : ''"
                >
                    <i :class="[item.icon, 'text-xl flex-shrink-0 transition-colors', isRouteActive(item.route) ? 'text-orange-600' : 'text-surface-400 group-hover:text-surface-600']"></i>
                    
                    <span 
                        class="whitespace-nowrap transition-all duration-300 overflow-hidden"
                        :class="collapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
                    >
                        {{ item.label }}
                    </span>
                    
                    <div v-if="isRouteActive(item.route)" class="absolute left-0 top-1/2 -translate-y-1/2 h-8 w-1 bg-orange-500 rounded-r-full"></div>
                </Link>

                <!-- Desktop: Grupo -->
                <div v-else class="flex flex-col">
                    <button 
                        @click="toggleGroup(item)"
                        class="flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group w-full relative hover:bg-surface-50"
                        :class="[
                            collapsed ? 'justify-center' : 'justify-between',
                            expandedGroups[item.label] && !collapsed ? 'text-surface-900' : 'text-surface-600'
                        ]"
                        :title="collapsed ? item.label : ''"
                    >
                        <div class="flex items-center gap-3 overflow-hidden">
                            <i :class="[item.icon, 'text-xl flex-shrink-0 text-surface-400 group-hover:text-surface-600']"></i>
                            <span 
                                class="whitespace-nowrap transition-all duration-300"
                                :class="collapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
                            >
                                {{ item.label }}
                            </span>
                        </div>
                        
                        <i 
                            v-if="!collapsed" 
                            class="pi pi-chevron-down text-xs text-surface-400 transition-transform duration-300" 
                            :class="{ 'rotate-180': expandedGroups[item.label] }"
                        ></i>
                    </button>

                    <div 
                        class="grid transition-[grid-template-rows] duration-300 ease-in-out"
                        :class="(expandedGroups[item.label] && !collapsed) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                    >
                        <div class="overflow-hidden">
                            <div class="flex flex-col gap-1 ml-4 border-l border-surface-200 pl-2 mt-1 transition-opacity duration-300"
                                :class="(expandedGroups[item.label] && !collapsed) ? 'opacity-100' : 'opacity-0'"
                            >
                                <Link 
                                    v-for="(subItem, subIndex) in item.items"
                                    :key="subIndex"
                                    :href="route(subItem.route)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-200 text-sm whitespace-nowrap"
                                    :class="isRouteActive(subItem.route) 
                                        ? 'bg-orange-50 text-orange-700 font-medium' 
                                        : 'text-surface-500 hover:text-surface-900 hover:bg-surface-100'"
                                >
                                    <span>{{ subItem.label }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </template>
        </div>
        
        <!-- Footer Clickable (Abre Modal de DTW) -->
        <div 
            class="mt-auto p-4 border-t border-surface-100 bg-white/50 backdrop-blur-sm overflow-hidden transition-all duration-300 cursor-pointer hover:bg-gray-50 group"
            :class="collapsed ? 'items-center flex justify-center' : ''"
            @click="showInfoModal = true"
            title="Ver información del sistema"
        >
            <div class="text-xs text-surface-400 whitespace-nowrap" :class="collapsed ? 'text-center' : ''">
                <p v-if="!collapsed" class="group-hover:text-blue-600 transition-colors font-bold">v2.0.0</p>
                <p v-if="!collapsed" class="font-light">Punto de venta</p>
                <i v-else class="pi pi-info-circle text-lg text-surface-400 group-hover:text-blue-600 transition-colors"></i>
            </div>
        </div>
    </aside>

    <!-- Modal de Información (DTW Agency) -->
    <Dialog v-model:visible="showInfoModal" modal header="Acerca del Sistema" :style="{ width: '25rem' }" :draggable="false">
        <div class="flex flex-col items-center text-center">
            <!-- Logo -->
            <img src="/images/DTW_logo_negro.webp" alt="DTW Agency" class="h-20 w-auto mb-4 object-contain" />

            <h3 class="text-lg font-bold text-gray-800 pt-3 border-t">Punto de venta</h3>
            <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full mt-1">v2.0.0</span>
            
            <p class="text-gray-500 text-sm mt-4 leading-relaxed">
                Desarrollamos soluciones tecnológicas a medida para potenciar tu negocio.
            </p>

            <div class="bg-gray-50 rounded-lg p-3 w-full mt-4 text-left border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Especialidades</p>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li class="flex items-center gap-2"><i class="pi pi-check text-green-500 text-xs"></i> ERP Completos</li>
                    <li class="flex items-center gap-2"><i class="pi pi-check text-green-500 text-xs"></i> Gestión de Clientes (CRM)</li>
                    <li class="flex items-center gap-2"><i class="pi pi-check text-green-500 text-xs"></i> Control de Inventarios</li>
                    <li class="flex items-center gap-2"><i class="pi pi-check text-green-500 text-xs"></i> Reportes Estadísticos</li>
                </ul>
            </div>
            
            <!-- Datos de Contacto y Botón Whatsapp -->
            <div class="mt-4 w-full flex flex-col items-center gap-3">
                <!-- Email -->
                <a href="mailto:contacto@dtw.com.mx" class="text-sm text-gray-600 hover:text-blue-600 flex items-center gap-2 transition-colors">
                    <i class="pi pi-envelope"></i> contacto@dtw.com.mx
                </a>

                <!-- Botón WhatsApp -->
                <a 
                    href="https://wa.me/523315314045" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center gap-2 group"
                >
                    <i class="pi pi-whatsapp text-xl group-hover:scale-110 transition-transform"></i>
                    <span>Soporte Técnico</span>
                </a>
            </div>

            <div class="mt-6 text-xs text-gray-400">
                &copy; {{ new Date().getFullYear() }} DTW. Todos los derechos reservados.
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>