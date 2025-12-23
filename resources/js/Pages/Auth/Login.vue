<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

// CAMBIO: Usamos 'id' en lugar de 'user_id' para coincidir con la validación de Fortify
const form = useForm({
    id: '', 
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión" />

    <div class="min-h-screen flex flex-col justify-center items-center bg-surface-50 selection:bg-orange-100 selection:text-orange-700 font-sans">
        
        <div class="w-full max-w-md px-8 py-10 bg-white shadow-2xl rounded-2xl border border-surface-200 relative overflow-hidden backdrop-blur-sm">
            
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-orange-400 to-orange-500 opacity-90"></div>

            <div class="flex justify-center mb-8">
                <AuthenticationCardLogo class="w-36" />
            </div>

            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-surface-900 tracking-tight">Bienvenido de nuevo</h2>
                <p class="text-surface-500 text-sm mt-2">Ingresa tus credenciales para acceder</p>
            </div>

            <div v-if="status" class="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-lg border border-green-100 text-center">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                
                <!-- CAMBIO: ID de Usuario (Mapeado a 'id') -->
                <div class="flex flex-col gap-2">
                    <label for="id" class="text-sm font-medium text-surface-700 ml-1">ID de Usuario</label>
                    <IconField>
                        <InputIcon class="pi pi-id-card z-10 text-surface-400" />
                        <InputText 
                            id="id" 
                            v-model="form.id" 
                            type="text" 
                            class="w-full pl-10 !border-surface-300 hover:!border-orange-400 focus:!border-orange-500 focus:!ring-orange-200/50" 
                            :class="{ 'p-invalid': form.errors.id }"
                            placeholder="Ingresa tu ID (ej. 1, 45, 102)"
                            required 
                            autofocus 
                        />
                    </IconField>
                    <!-- CAMBIO: Ahora mostramos el error de 'form.errors.id' -->
                    <small v-if="form.errors.id" class="text-red-500 text-xs ml-1 transition-all duration-300 font-medium flex items-center gap-1">
                        <i class="pi pi-exclamation-circle"></i> {{ form.errors.id }}
                    </small>
                </div>

                <!-- Contraseña -->
                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium text-surface-700 ml-1">Contraseña</label>
                    <div class="w-full">
                        <Password 
                            v-model="form.password" 
                            :feedback="false" 
                            toggleMask 
                            placeholder="••••••••"
                            inputClass="w-full pl-10 !border-surface-300 hover:!border-orange-400 focus:!border-orange-500 focus:!ring-orange-200/50"
                            class="w-full"
                            :pt="{
                                root: { class: 'w-full' },
                                input: { class: 'w-full' }
                            }"
                            :invalid="!!form.errors.password"
                        />
                    </div>
                    <small v-if="form.errors.password" class="text-red-500 text-xs ml-1 transition-all duration-300 font-medium flex items-center gap-1">
                        <i class="pi pi-exclamation-circle"></i> {{ form.errors.password }}
                    </small>
                </div>

                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center">
                        <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                        <label for="remember" class="ml-2 text-sm text-surface-600 cursor-pointer select-none hover:text-surface-900 transition-colors">Recordarme</label>
                    </div>

                    <!-- <Link 
                        v-if="canResetPassword" 
                        :href="route('password.request')" 
                        class="text-sm text-orange-600 hover:text-orange-700 font-medium transition-colors duration-200"
                    >
                        ¿Olvidaste tu contraseña?
                    </Link> -->
                </div>

                <div class="pt-2">
                    <Button 
                        type="submit" 
                        label="Iniciar sesión" 
                        icon="pi pi-arrow-right" 
                        iconPos="right"
                        class="w-full font-bold !bg-orange-600 !border-orange-600 hover:!bg-orange-700" 
                        :loading="form.processing"
                        rounded
                    />
                </div>
            </form>
        </div>

        <div class="mt-8 text-center text-xs text-surface-400">
            &copy; {{ new Date().getFullYear() }} Purochurro. Todos los derechos reservados.
        </div>
    </div>
</template>

<style scoped>
:deep(.p-inputtext) {
    border-radius: 0.75rem; 
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
    transition: all 0.2s ease;
}

:deep(.p-inputtext:focus) {
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important; 
}

:deep(.p-password-input) {
    width: 100%;
}

:deep(.p-checkbox-box) {
    border-radius: 6px; 
}

:deep(.p-checkbox-checked .p-checkbox-box) {
    background-color: var(--p-primary-500);
    border-color: var(--p-primary-500);
}
</style>