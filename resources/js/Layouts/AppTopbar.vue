<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

const emit = defineEmits(['toggle-sidebar']);
const page = usePage();
const user = page.props.auth.user;

// Menú de usuario
const userMenu = ref();
const userMenuItems = ref([
    {
        label: 'Perfil',
        icon: 'pi pi-user',
        command: () => router.get(route('profile.show'))
    },
    {
        separator: true
    },
    {
        label: 'Cerrar Sesión',
        icon: 'pi pi-sign-out',
        command: () => router.post(route('logout'))
    }
]);

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};
</script>

<template>
    <header class="h-16 bg-white/80 backdrop-blur-md border-b border-surface-200 flex items-center justify-between px-4 md:px-6 shadow-sm transition-all duration-300">
        
        <!-- Izquierda: Logo y Toggle Móvil -->
        <div class="flex items-center gap-4">
            <!-- Botón Hamburguesa (Solo Móvil) -->
            <button 
                @click="$emit('toggle-sidebar')" 
                class="md:hidden p-2 rounded-lg hover:bg-surface-100 text-surface-500 transition-colors"
            >
                <i class="pi pi-bars text-xl"></i>
            </button>

            <!-- Logo -->
            <Link :href="route('dashboard')" class="flex items-center gap-2 group">
                <AuthenticationCardLogo class="w-20 text-orange-600 group-hover:scale-110 transition-transform duration-300" />
            </Link>
        </div>

        <!-- Derecha: Perfil de Usuario -->
        <div class="flex items-center gap-4">
            <button 
                @click="toggleUserMenu" 
                class="flex items-center gap-2 p-1.5 rounded-full border border-transparent hover:bg-surface-100 hover:border-surface-200 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-orange-200"
            >
                <img 
                    class="h-8 w-8 rounded-full object-cover border border-surface-200 shadow-sm" 
                    :src="user.profile_photo_url" 
                    :alt="user.name"
                >
                <span class="text-sm font-medium text-surface-700 hidden md:block pr-2">
                    {{ user.name }}
                </span>
                <i class="pi pi-chevron-down text-xs text-surface-400 hidden md:block mr-1"></i>
            </button>
            
            <Menu ref="userMenu" :model="userMenuItems" :popup="true" class="w-48" />
        </div>
    </header>
</template>