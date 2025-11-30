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

// Cargar estado del sidebar desde localStorage al montar
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
    <!-- Agregado overflow-x-hidden para evitar scroll horizontal en móvil -->
    <div class="min-h-screen bg-surface-50 font-sans text-surface-900 selection:bg-orange-100 selection:text-orange-700 overflow-x-hidden">
        <Head :title="title" />

        <!-- Componentes Globales -->
        <Toast position="bottom-right" />
        <ConfirmDialog />

        <!-- Topbar -->
        <div class="fixed top-0 left-0 right-0 z-50">
            <AppTopbar @toggle-sidebar="toggleMobileSidebar" />
        </div>

        <!-- Sidebar -->
        <!-- Pasamos el estado 'collapsed' y escuchamos el evento para alternarlo -->
        <AppSidebar 
            v-model:visible="isMobileSidebarOpen"
            :collapsed="isDesktopSidebarCollapsed"
            @toggle-collapsed="toggleDesktopSidebar"
        />

        <!-- Contenido Principal -->
        <!-- El margen izquierdo (ml) ahora es dinámico: 64 (expandido) o 20 (colapsado) -->
        <main 
            class="pt-20 px-4 pb-8 transition-all duration-300 container mx-auto max-w-7xl"
            :class="isDesktopSidebarCollapsed ? 'md:ml-20' : 'md:ml-64'"
        >
            <div class="fade-in-up">
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