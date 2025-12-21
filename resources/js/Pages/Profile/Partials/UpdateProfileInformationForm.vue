<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import FileUpload from 'primevue/fileupload'; // Opcional, pero usaremos input nativo estilizado para simpleza

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];
    if (! photo) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <div class="bg-white rounded-3xl shadow-sm border border-surface-200 overflow-hidden">
        <!-- Header Tarjeta -->
        <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
            <h2 class="text-lg font-bold text-surface-900">Información del Perfil</h2>
            <p class="text-sm text-surface-500">Actualiza tu información de contacto y foto.</p>
        </div>

        <form @submit.prevent="updateProfileInformation" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Columna Foto -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex flex-col items-center gap-4">
                <!-- <input id="photo" ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview"> -->

                <div class="relative group" @click="selectNewPhoto">
                    <!-- Foto Actual -->
                    <div v-show="!photoPreview" class="w-32 h-32 rounded-full overflow-hidden border-4 border-surface-100 shadow-md">
                        <img :src="user.profile_photo_url" :alt="user.name" class="w-full h-full object-cover">
                    </div>
                    <!-- Previsualización -->
                    <div v-show="photoPreview" class="w-32 h-32 rounded-full overflow-hidden border-4 border-surface-100 shadow-md bg-cover bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'">
                    </div>
                    
                    <!-- Overlay Hover -->
                    <!-- <div class="absolute inset-0 bg-black/30 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="pi pi-camera text-white text-2xl"></i>
                    </div> -->
                </div>

                <!-- <div class="flex flex-col gap-2 w-full">
                    <Button label="Cambiar Foto" size="small" outlined severity="secondary" @click.prevent="selectNewPhoto" class="w-full" />
                    <Button v-if="user.profile_photo_path" label="Eliminar" size="small" text severity="danger" @click.prevent="deletePhoto" class="w-full" />
                </div> -->
                
                <small class="text-red-500" v-if="form.errors.photo">{{ form.errors.photo }}</small>
            </div>

            <!-- Columna Formulario -->
            <div class="md:col-span-2 space-y-5">
                <div class="flex flex-col gap-2">
                    <label for="name" class="font-bold text-surface-700 text-sm">Nombre Completo</label>
                    <InputText id="name" v-model="form.name" type="text" class="w-full" :class="{'p-invalid': form.errors.name}" autocomplete="name" />
                    <small class="text-red-500" v-if="form.errors.name">{{ form.errors.name }}</small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="email" class="font-bold text-surface-700 text-sm">Correo Electrónico</label>
                    <InputText id="email" v-model="form.email" type="email" class="w-full" :class="{'p-invalid': form.errors.email}" autocomplete="username" />
                    <small class="text-red-500" v-if="form.errors.email">{{ form.errors.email }}</small>

                    <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null" class="mt-2 bg-yellow-50 p-3 rounded-xl border border-yellow-100">
                        <p class="text-sm text-yellow-800">
                            Tu correo no ha sido verificado.
                            <Link :href="route('verification.send')" method="post" as="button" class="underline font-bold hover:text-yellow-900" @click.prevent="sendEmailVerification">
                                Reenviar enlace de verificación.
                            </Link>
                        </p>
                        <div v-show="verificationLinkSent" class="mt-2 text-sm font-bold text-green-600">
                            Enlace enviado. Revisa tu bandeja de entrada.
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-surface-100 gap-3">
                    <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0" leave-active-class="transition ease-in duration-200" leave-to-class="opacity-0">
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600 font-bold">¡Guardado!</span>
                    </transition>
                    <Button type="submit" label="Guardar Cambios" icon="pi pi-check" :loading="form.processing" class="!bg-surface-900 !border-surface-900 hover:!bg-surface-800" />
                </div>
            </div>
        </form>
    </div>
</template>