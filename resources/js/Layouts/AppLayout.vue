<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppTopbar from '@/Layouts/AppTopbar.vue';
import AppSidebar from '@/Layouts/AppSidebar.vue';

defineProps({
    title: String,
});

const isMobileSidebarOpen = ref(false);
const isDesktopSidebarCollapsed = ref(false);

// Cargar estado del sidebar desde localStorage
onMounted(() => {
    const savedState = localStorage.getItem('sidebar-collapsed');
    if (savedState !== null) {
        isDesktopSidebarCollapsed.value = savedState === 'true';
    }
});

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
};

const toggleDesktopSidebar = () => {
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
            <AppTopbar @toggle-sidebar="toggleMobileSidebar" />
        </div>

        <!-- Sidebar (Fijo izquierda) -->
        <AppSidebar 
            v-model:visible="isMobileSidebarOpen"
            :collapsed="isDesktopSidebarCollapsed"
            @toggle-collapsed="toggleDesktopSidebar"
        />

        <!-- 
            WRAPPER PRINCIPAL (El que se ajusta al Sidebar)
            Nota: Ya NO tiene 'container mx-auto'. Su única función es dar el margen izquierdo.
            Usamos 'w-auto' implícito (bloque) para que ocupe todo el ancho restante.
        -->
        <main 
            class="pt-20 pb-8 px-4 transition-all duration-300 min-h-screen flex flex-col"
            :class="isDesktopSidebarCollapsed ? 'md:ml-20' : 'md:ml-64'"
        >
            <!-- 
                CONTENEDOR DE CENTRADO (El que centra el contenido)
                Este div toma el espacio disponible dentro del main y centra el contenido.
            -->
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