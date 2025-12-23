<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppTopbar from '@/Layouts/AppTopbar.vue';
import AppSidebar from '@/Layouts/AppSidebar.vue';

defineProps({
    title: String,
});

// Estado para Móvil (Drawer overlay)
const isMobileSidebarOpen = ref(false);

// Estado para Desktop:
// 1. isDesktopSidebarOpen: Controla si la barra está visible o completamente oculta (tipo Drawer)
// 2. isDesktopSidebarCollapsed: Controla si la barra está en modo "Mini" (iconos) o extendida
const isDesktopSidebarOpen = ref(true);
const isDesktopSidebarCollapsed = ref(false);

// Cargar estado del sidebar desde localStorage
onMounted(() => {
    // Restaurar colapso (mini mode)
    const savedCollapsedState = localStorage.getItem('sidebar-collapsed');
    if (savedCollapsedState !== null) {
        isDesktopSidebarCollapsed.value = savedCollapsedState === 'true';
    }
    
    // Restaurar visibilidad (open/closed) - Opcional, por defecto true
    const savedOpenState = localStorage.getItem('sidebar-open');
    if (savedOpenState !== null) {
        isDesktopSidebarOpen.value = savedOpenState === 'true';
    }
});

// Función inteligente de Toggle
const toggleSidebar = () => {
    if (window.innerWidth >= 768) { 
        // Lógica Desktop: Alternar visibilidad completa
        isDesktopSidebarOpen.value = !isDesktopSidebarOpen.value;
        localStorage.setItem('sidebar-open', isDesktopSidebarOpen.value);
    } else {
        // Lógica Móvil: Alternar Drawer
        isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
    }
};

const toggleDesktopCollapsed = () => {
    isDesktopSidebarCollapsed.value = !isDesktopSidebarCollapsed.value;
    localStorage.setItem('sidebar-collapsed', isDesktopSidebarCollapsed.value);
};
</script>

<template>
    <div class="min-h-screen bg-surface-50 font-sans text-surface-900 selection:bg-orange-100 selection:text-orange-700 overflow-x-hidden">
        <Head :title="title" />

        <!-- Componentes Globales -->
        <Toast position="bottom-right" />
        <ConfirmDialog />

        <!-- Topbar (Fijo arriba) -->
        <div class="fixed top-0 left-0 right-0 z-50">
            <!-- Pasamos el mismo evento toggle-sidebar -->
            <AppTopbar @toggle-sidebar="toggleSidebar" />
        </div>

        <!-- Sidebar (Fijo izquierda) -->
        <AppSidebar 
            v-model:visible="isMobileSidebarOpen"
            :collapsed="isDesktopSidebarCollapsed"
            :desktop-visible="isDesktopSidebarOpen"
            @toggle-collapsed="toggleDesktopCollapsed"
        />

        <!-- 
            WRAPPER PRINCIPAL
            Ajusta dinámicamente el margen izquierdo en desktop:
            - Si está cerrado (hidden): ml-0
            - Si está abierto y colapsado (mini): ml-20
            - Si está abierto y full: ml-64
        -->
        <main 
            class="pt-20 pb-8 px-4 transition-all duration-300 min-h-screen flex flex-col"
            :class="[
                !isDesktopSidebarOpen ? 'md:ml-0' : (isDesktopSidebarCollapsed ? 'md:ml-20' : 'md:ml-64')
            ]"
        >
            <div class="w-full max-w-7xl mx-auto fade-in-up flex-1">
                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
.fade-in-up {
    animation: fadeInUp 0.5s ease-out forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>