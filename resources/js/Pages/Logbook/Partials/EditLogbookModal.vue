<script setup>
import { watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    visible: Boolean,
    logbook: Object // La bitácora a editar
});

const emit = defineEmits(['update:visible', 'success']);
const page = usePage();

const form = useForm({
    content: '',
});

// Cuando se abre el modal, cargamos el texto de la bitácora
watch(() => props.logbook, (newVal) => {
    if (newVal) {
        form.content = newVal.content;
    }
}, { immediate: true });

const close = () => {
    emit('update:visible', false);
    setTimeout(() => {
        form.reset();
        form.clearErrors();
    }, 200);
};

const submitLogbook = () => {
    form.put(route('logbooks.update', props.logbook.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (!page.props.flash?.error) {
                emit('success', 'Bitácora actualizada correctamente.');
                close();
            }
        }
    });
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)" 
        modal 
        header="Editar reporte" 
        :style="{ width: '40rem' }"
        :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        @hide="close"
    >
        <form @submit.prevent="submitLogbook" class="space-y-5 mt-2">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Detalles del reporte</label>
                <Textarea 
                    v-model="form.content" 
                    rows="5" 
                    class="w-full !border-gray-300 !rounded-md" 
                    :class="{ 'p-invalid': form.errors.content }"
                />
                <small class="p-error block mt-1" v-if="form.errors.content">{{ form.errors.content }}</small>
            </div>

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
                    label="Guardar cambios" 
                    icon="pi pi-check" 
                    :loading="form.processing" 
                    :disabled="!form.content.trim()"
                />
            </div>
        </form>
    </Dialog>
</template>