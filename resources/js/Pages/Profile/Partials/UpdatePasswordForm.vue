<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password'; // Usamos el componente Password de PrimeVue

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                // Nota: PrimeVue Password component ref handling might differ slightly depending on version, usually works on input element
            }
            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
            <h2 class="text-lg font-bold text-surface-900">Seguridad</h2>
            <p class="text-sm text-surface-500">Asegúrate de usar una contraseña larga y aleatoria.</p>
        </div>

        <form @submit.prevent="updatePassword" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Icono Decorativo -->
            <div class="hidden md:flex flex-col items-center justify-center text-surface-200">
                <i class="pi pi-lock !text-6xl"></i>
            </div>

            <div class="md:col-span-2 space-y-5">
                <div class="flex flex-col gap-2">
                    <label for="current_password" class="font-bold text-surface-700 text-sm">Contraseña Actual</label>
                    <Password 
                        id="current_password" 
                        v-model="form.current_password" 
                        toggleMask 
                        :feedback="false"
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.current_password"
                        autocomplete="current-password"
                    />
                    <small class="text-red-500" v-if="form.errors.current_password">{{ form.errors.current_password }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="font-bold text-surface-700 text-sm">Nueva Contraseña</label>
                    <Password 
                        id="password" 
                        v-model="form.password" 
                        toggleMask 
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.password"
                        autocomplete="new-password"
                        promptLabel="Ingresa una contraseña segura"
                        weakLabel="Débil"
                        mediumLabel="Media"
                        strongLabel="Fuerte"
                    />
                    <small class="text-red-500" v-if="form.errors.password">{{ form.errors.password }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password_confirmation" class="font-bold text-surface-700 text-sm">Confirmar Contraseña</label>
                    <Password 
                        id="password_confirmation" 
                        v-model="form.password_confirmation" 
                        toggleMask 
                        :feedback="false"
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.password_confirmation"
                        autocomplete="new-password"
                    />
                    <small class="text-red-500" v-if="form.errors.password_confirmation">{{ form.errors.password_confirmation }}</small>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-surface-100 gap-3">
                    <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0" leave-active-class="transition ease-in duration-200" leave-to-class="opacity-0">
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600 font-bold">¡Actualizado!</span>
                    </transition>
                    <Button type="submit" label="Actualizar Contraseña" icon="pi pi-shield" :loading="form.processing" class="!bg-surface-900 !border-surface-900 hover:!bg-surface-800" />
                </div>
            </div>
        </form>
    </div>
</template>