<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    visible: Boolean,
    logbook: Object,
    currentUserId: Number
});

const emit = defineEmits(['update:visible', 'read-confirmed']);
const page = usePage();

const close = () => {
    emit('update:visible', false);
};

// Observamos cuando el modal se abre para enviar la confirmación de lectura por detrás
watch(() => props.visible, (isVisible) => {
    if (isVisible && props.logbook) {
        const alreadyRead = props.logbook.readers?.some(r => r.id === props.currentUserId);
        
        // Solo llamamos al backend si el usuario actual no lo ha leído aún
        if (!alreadyRead) {
            axios.get(route('logbooks.show', props.logbook.id))
                .then(() => {
                    // Notificamos al componente padre que se leyó con éxito
                    emit('read-confirmed', props.logbook.id, page.props.auth.user);
                })
                .catch(error => console.error("Error al marcar como leído:", error));
        }
    }
});

const formatTimeFull = (dateString) => {
    if (!dateString) return '';
    const options = { weekday: 'long', day: 'numeric', month: 'short', hour: 'numeric', minute: 'numeric', hour12: true };
    return new Intl.DateTimeFormat('es-MX', options).format(new Date(dateString));
};

// Generar iniciales para los avatares si no hay foto
const getInitials = (name) => {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)" 
        modal 
        header="Detalle del reporte" 
        :style="{ width: '42rem' }"
        :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
        @hide="close"
        :pt="{
            root: { class: '!rounded-3xl !border-0 !shadow-2xl !overflow-hidden !backdrop-blur-xl' },
            header: { class: '!border-b !border-gray-100/50 !bg-transparent !px-6 !py-4' },
            title: { class: '!text-lg !font-semibold !tracking-tight !text-gray-800' },
            content: { class: '!px-6 !pb-6 !pt-5 !bg-transparent' },
            closeButton: { class: 'hover:!bg-gray-100 !transition-colors !rounded-full !w-8 !h-8' }
        }"
    >
        <div v-if="logbook" class="flex flex-col h-full">
            
            <!-- Cabecera: Autor y Fecha -->
            <div class="flex items-center gap-4 mb-6">
                <Avatar 
                    :image="logbook.author?.profile_photo_path || `https://ui-avatars.com/api/?name=${logbook.author?.name}&background=EBF4FF&color=7F9CF5`" 
                    shape="circle" 
                    class="w-12 h-12 shrink-0 border border-gray-100 shadow-sm" 
                />
                <div class="flex flex-col">
                    <span class="text-base font-bold text-gray-900 tracking-tight leading-tight">{{ logbook.author?.name }}</span>
                    <span class="text-xs font-medium text-gray-400 capitalize mt-0.5">{{ formatTimeFull(logbook.created_at) }}</span>
                </div>
            </div>

            <!-- Contenido principal estilo "Notes" -->
            <div class="mb-8">
                <p class="text-[15px] text-gray-800 whitespace-pre-wrap leading-relaxed tracking-normal">
                    {{ logbook.content }}
                </p>
            </div>

            <!-- Evidencias adjuntas -->
            <div v-if="logbook.media && logbook.media.length > 0" class="mb-8">
                <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="pi pi-paperclip text-gray-300"></i> Evidencias adjuntas
                </h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                    <div 
                        v-for="image in logbook.media" 
                        :key="image.id" 
                        class="aspect-square rounded-2xl overflow-hidden border border-gray-100 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] bg-gray-50 group"
                    >
                        <Image 
                            :src="image.original_url" 
                            alt="Evidencia" 
                            preview 
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" 
                        />
                    </div>
                </div>
            </div>

            <!-- Footer: Listado de Lectores (Estilo Pills) -->
            <div class="mt-auto pt-4 border-t border-gray-100/60">
                <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">
                    <i class="pi pi-eye text-blue-400"></i>
                    <span>Visto por ({{ logbook.readers?.length || 0 }})</span>
                </div>
                
                <div v-if="logbook.readers?.length > 0" class="flex flex-wrap gap-2">
                    <!-- Pills de usuarios que han leído -->
                    <div 
                        v-for="reader in logbook.readers" 
                        :key="reader.id" 
                        class="flex items-center gap-1.5 bg-gray-50 border border-gray-200/60 text-gray-700 px-2 py-1 rounded-full shadow-[0_1px_2px_rgba(0,0,0,0.02)]"
                    >
                        <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[9px] font-bold shrink-0">
                            {{ getInitials(reader.name) }}
                        </div>
                        <span class="text-xs font-medium pr-1 tracking-tight">{{ reader.name.split(' ')[0] }} {{ reader.name.split(' ')[1]?.[0] || '' }}.</span>
                    </div>
                </div>
                
                <div v-else class="text-sm text-gray-400 italic bg-gray-50/50 p-3 rounded-xl border border-gray-100/50">
                    Aún no hay confirmaciones de lectura.
                </div>
            </div>
            
        </div>
    </Dialog>
</template>

<style scoped>
/* Asegurar que las miniaturas de PrimeVue Image ocupen todo el recuadro redondeado */
:deep(.p-image) {
    width: 100%;
    height: 100%;
    display: block;
}
:deep(.p-image-preview-container) {
    width: 100%;
    height: 100%;
}
:deep(.p-image-preview-container > img) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>