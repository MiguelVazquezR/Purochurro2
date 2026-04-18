<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    visible: Boolean
});

const emit = defineEmits(['update:visible', 'success']);
const page = usePage();

const form = useForm({
    content: '',
    images: [] // Guardará los objetos File nativos
});

const imagePreviews = ref([]);
const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    
    // Validar cantidad máxima
    if (form.images.length + files.length > 5) {
        alert('Solo puedes adjuntar un máximo de 5 imágenes.');
        event.target.value = ''; // Limpiar input
        return;
    }

    files.forEach(file => {
        // Validar tamaño (5MB)
        if (file.size > 5242880) {
            alert(`La imagen ${file.name} es demasiado grande. El máximo es 5MB.`);
            return;
        }

        form.images.push(file);
        imagePreviews.value.push({
            name: file.name,
            url: URL.createObjectURL(file)
        });
    });

    event.target.value = ''; // Resetear input para permitir subir la misma imagen si se elimina
};

const removeImage = (index) => {
    // Liberar memoria del navegador
    URL.revokeObjectURL(imagePreviews.value[index].url);
    imagePreviews.value.splice(index, 1);
    form.images.splice(index, 1);
};

const close = () => {
    emit('update:visible', false);
    
    // Limpieza manual forzada después de la animación de cierre
    setTimeout(() => {
        form.content = ''; 
        form.images = [];
        form.clearErrors();
        imagePreviews.value.forEach(p => URL.revokeObjectURL(p.url));
        imagePreviews.value = [];
    }, 200); 
};

const submitLogbook = () => {
    // Usamos transform para limpiar el array de imágenes si está vacío y evitar bugs de FormData
    form.transform((data) => {
        return {
            ...data,
            images: data.images.length > 0 ? data.images : null,
        };
    }).post(route('logbooks.store'), {
        forceFormData: true, // ¡CRUCIAL! Obliga a Inertia a enviar como multipart/form-data
        preserveScroll: true,
        onSuccess: () => {
            // Verificamos si el controlador capturó un error interno y mandó un flash error
            if (page.props.flash?.error) {
                console.error("Error del backend:", page.props.flash.error);
                // No cerramos el modal, el Toast principal mostrará el error.
            } else {
                emit('success', 'Bitácora registrada correctamente.');
                close();
            }
        },
        onError: (errors) => {
            console.error("Errores de validación:", errors);
            // Si hay errores de validación de formulario, NO cerramos el modal para que el usuario los vea.
        }
    });
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)" 
        modal 
        header="Nueva bitácora" 
        :style="{ width: '40rem' }"
        :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        @hide="close"
    >
        <form @submit.prevent="submitLogbook" class="space-y-5 mt-2">
            
            <!-- Campo de texto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Detalles del reporte</label>
                <Textarea 
                    v-model="form.content" 
                    rows="5" 
                    class="w-full !border-gray-300 !rounded-md" 
                    placeholder="Ej: Se terminó la masa antes de tiempo en el turno matutino, hay que preparar más para la tarde..."
                    :class="{ 'p-invalid': form.errors.content }"
                />
                <small class="p-error block mt-1" v-if="form.errors.content">{{ form.errors.content }}</small>
            </div>
            
            <!-- Zona de subida de archivos -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Evidencias (opcional)</label>
                
                <input 
                    type="file" 
                    ref="fileInput" 
                    multiple 
                    accept="image/*" 
                    class="hidden" 
                    @change="handleFileSelect"
                />
                
                <Button 
                    type="button"
                    label="Adjuntar imágenes" 
                    icon="pi pi-camera" 
                    outlined
                    severity="secondary"
                    class="w-full sm:w-auto"
                    @click="triggerFileInput"
                    :disabled="form.images.length >= 5"
                />
                <small class="text-gray-500 block mt-1">Máximo 5 imágenes (5MB c/u).</small>
                
                <!-- Errores de validación de Laravel para imágenes -->
                <small class="p-error block mt-1" v-if="form.errors.images">{{ form.errors.images }}</small>
                <div v-for="(error, key) in form.errors" :key="key">
                    <small class="p-error block mt-1" v-if="key.startsWith('images.')">{{ error }}</small>
                </div>

                <!-- Previsualización de imágenes -->
                <div v-if="imagePreviews.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div 
                        v-for="(img, index) in imagePreviews" 
                        :key="index" 
                        class="relative group border rounded-lg overflow-hidden bg-gray-50 aspect-square"
                    >
                        <img :src="img.url" class="w-full h-full object-cover" />
                        
                        <!-- Capa superpuesta y botón de eliminar -->
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <Button 
                                icon="pi pi-trash" 
                                severity="danger" 
                                rounded 
                                aria-label="Eliminar" 
                                @click="removeImage(index)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <Button 
                    label="Cancelar" 
                    icon="pi pi-times" 
                    severity="secondary" 
                    text 
                    @click="close" 
                />
                <Button 
                    type="submit" 
                    label="Guardar registro" 
                    icon="pi pi-check" 
                    :loading="form.processing" 
                    :disabled="!form.content.trim() || form.processing"
                />
            </div>
        </form>
    </Dialog>
</template>