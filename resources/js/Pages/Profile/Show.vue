<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <AppLayout title="Mi Perfil">
        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8 space-y-10">
            
            <!-- Encabezado -->
            <div>
                <h1 class="text-3xl font-bold text-surface-900 tracking-tight">Configuración de Cuenta</h1>
                <p class="text-surface-500 mt-1">Gestiona tu información personal y seguridad.</p>
            </div>

            <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                <UpdateProfileInformationForm :user="$page.props.auth.user" />
            </div>

            <div v-if="$page.props.jetstream.canUpdatePassword">
                <UpdatePasswordForm />
            </div>

            <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                <TwoFactorAuthenticationForm
                    :requires-confirmation="confirmsTwoFactorAuthentication"
                />
            </div>

            <LogoutOtherBrowserSessionsForm :sessions="sessions" />

            <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                <div class="pt-10 border-t border-surface-200">
                    <h3 class="text-lg font-bold text-red-600 mb-4">Zona de Peligro</h3>
                    <DeleteUserForm />
                </div>
            </template>
        </div>
    </AppLayout>
</template>