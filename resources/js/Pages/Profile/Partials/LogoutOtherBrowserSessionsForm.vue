<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';

defineProps({
    sessions: Array,
});

const confirmingLogout = ref(false);
const form = useForm({
    password: '',
});

const confirmLogout = () => {
    confirmingLogout.value = true;
};

const logoutOtherBrowserSessions = () => {
    form.delete(route('other-browser-sessions.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => {
            // Focus manual si es necesario
        },
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingLogout.value = false;
    form.reset();
};
</script>

<template>
    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
            <h2 class="text-lg font-bold text-surface-900">Sesiones Activas</h2>
            <p class="text-sm text-surface-500">Gestiona y cierra sesión en otros navegadores y dispositivos.</p>
        </div>

        <div class="p-6">
            <p class="text-sm text-surface-600 mb-6 max-w-xl leading-relaxed">
                Si es necesario, puedes cerrar sesión en todas tus otras sesiones de navegador en todos tus dispositivos. Algunas de tus sesiones recientes se enumeran a continuación; sin embargo, esta lista puede no ser exhaustiva.
            </p>

            <!-- Lista de Sesiones -->
            <div v-if="sessions.length > 0" class="space-y-4 mb-6">
                <div v-for="(session, i) in sessions" :key="i" class="flex items-center gap-4 p-3 rounded-xl border border-surface-100 bg-surface-50/50">
                    <div class="text-surface-400">
                        <i v-if="session.agent.is_desktop" class="pi pi-desktop text-2xl"></i>
                        <i v-else class="pi pi-mobile text-2xl"></i>
                    </div>

                    <div class="flex-1">
                        <div class="text-sm font-bold text-surface-800">
                            {{ session.agent.platform ? session.agent.platform : 'Desconocido' }} - {{ session.agent.browser ? session.agent.browser : 'Desconocido' }}
                        </div>

                        <div class="text-xs text-surface-500 mt-0.5">
                            {{ session.ip_address }},
                            <span v-if="session.is_current_device" class="text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded-full ml-1">Este dispositivo</span>
                            <span v-else>Última actividad {{ session.last_active }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center pt-2">
                <Button label="Cerrar otras sesiones" icon="pi pi-sign-out" severity="secondary" @click="confirmLogout" />
                
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0" leave-active-class="transition ease-in duration-200" leave-to-class="opacity-0">
                    <span v-if="form.recentlySuccessful" class="ml-3 text-sm text-green-600 font-bold">¡Listo!</span>
                </transition>
            </div>

            <!-- Modal de Confirmación -->
            <Dialog v-model:visible="confirmingLogout" modal header="Cerrar Otras Sesiones" :style="{ width: '450px' }">
                <div class="flex flex-col gap-4">
                    <p class="text-surface-600 text-sm">
                        Ingresa tu contraseña para confirmar que deseas cerrar sesión en los demás dispositivos.
                    </p>

                    <div class="flex flex-col gap-2">
                        <Password 
                            v-model="form.password" 
                            :feedback="false" 
                            toggleMask 
                            placeholder="Contraseña" 
                            class="w-full" 
                            inputClass="w-full"
                            :invalid="!!form.errors.password"
                            @keyup.enter="logoutOtherBrowserSessions"
                            autofocus
                        />
                        <small class="text-red-500" v-if="form.errors.password">{{ form.errors.password }}</small>
                    </div>
                </div>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <Button label="Cancelar" icon="pi pi-times" text @click="closeModal" severity="secondary" />
                        <Button label="Cerrar Sesiones" icon="pi pi-check" @click="logoutOtherBrowserSessions" severity="danger" :loading="form.processing" />
                    </div>
                </template>
            </Dialog>
        </div>
    </div>
</template>