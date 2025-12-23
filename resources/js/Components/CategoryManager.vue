<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from "primevue/useconfirm";

const props = defineProps({
    visible: Boolean,
    categories: Array
});

const emit = defineEmits(['update:visible']);

const toast = useToast();
const confirm = useConfirm();

const editingCategory = ref(null);

// Formulario para Categoría
const form = useForm({
    name: '',
    color: '3b82f6',
});

// Cerrar el modal
const closeDialog = () => {
    emit('update:visible', false);
    resetForm();
};

const resetForm = () => {
    editingCategory.value = null;
    form.reset();
    form.color = '3b82f6';
    form.clearErrors();
};

const submitCategory = () => {
    const url = editingCategory.value 
        ? route('categories.update', editingCategory.value.id) 
        : route('categories.store');
    
    const method = editingCategory.value ? 'put' : 'post';

    form[method](url, {
        preserveScroll: true,
        preserveState: true, // Mantiene el estado de la vista padre (ej. formulario de producto)
        onSuccess: () => {
            toast.add({ 
                severity: 'success', 
                summary: 'Categoría', 
                detail: editingCategory.value ? 'Actualizada correctamente' : 'Creada correctamente', 
                life: 3000 
            });
            resetForm();
        }
    });
};

const editCategoryObj = (category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.color = category.color || '3b82f6';
};

const deleteCategory = (category) => {
    confirm.require({
        message: `¿Eliminar la categoría "${category.name}"?`,
        header: 'Confirmar eliminación',
        icon: 'pi pi-exclamation-triangle',
        group: 'categoryManagerConfirm', // Grupo único
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('categories.destroy', category.id), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.add({ severity: 'info', summary: 'Eliminado', detail: 'Categoría eliminada', life: 3000 }),
                onError: (errors) => {
                    const msg = errors.error || 'No se pudo eliminar la categoría';
                    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <!-- ConfirmDialog interno del componente -->
    <ConfirmDialog group="categoryManagerConfirm" />

    <Dialog 
        :visible="visible" 
        @update:visible="$emit('update:visible', $event)"
        modal 
        header="Gestionar Categorías" 
        :style="{ width: '30rem' }"
        :breakpoints="{ '960px': '75vw', '641px': '90vw' }"
        class="p-fluid"
        @hide="resetForm"
    >
        <div class="flex flex-col gap-4">
            <!-- Formulario (Crear/Editar) -->
            <div class="bg-surface-50 p-4 rounded-xl border border-surface-200 flex flex-col gap-3">
                <span class="text-sm font-bold text-surface-600 uppercase">{{ editingCategory ? 'Editar Categoría' : 'Nueva Categoría' }}</span>
                
                <div class="flex gap-2">
                    <div class="flex-1">
                        <InputText v-model="form.name" placeholder="Nombre (ej. Bebidas)" class="w-full" :invalid="!!form.errors.name" />
                        <small v-if="form.errors.name" class="text-red-500 block mt-1">{{ form.errors.name }}</small>
                    </div>
                    <div class="w-12">
                            <ColorPicker v-model="form.color"  />
                    </div>
                </div>
                
                <div class="flex justify-end gap-2">
                    <Button 
                        v-if="editingCategory" 
                        label="Cancelar" 
                        icon="pi pi-times" 
                        text 
                        size="small" 
                        @click="resetForm" 
                    />
                    <Button 
                        :label="editingCategory ? 'Actualizar' : 'Agregar'" 
                        :icon="editingCategory ? 'pi pi-save' : 'pi pi-plus'" 
                        size="small" 
                        @click="submitCategory" 
                        :loading="form.processing"
                        :disabled="!form.name"
                    />
                </div>
            </div>

            <!-- Lista de Categorías -->
            <div class="border-t border-surface-200 pt-2">
                <span class="text-xs text-surface-500 block mb-2">Categorías Existentes ({{ categories.length }})</span>
                
                <div v-if="categories.length === 0" class="text-center py-4 text-surface-400 italic">
                    No hay categorías registradas.
                </div>
                
                <div v-else class="max-h-60 overflow-y-auto custom-scrollbar flex flex-col gap-1 pr-1">
                    <div 
                        v-for="cat in categories" 
                        :key="cat.id" 
                        class="flex items-center justify-between p-2 rounded-lg hover:bg-surface-50 transition-colors group"
                        :class="{ 'bg-orange-50': editingCategory && editingCategory.id === cat.id }"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-4 h-4 rounded-full border border-surface-300 shadow-sm" :style="{ backgroundColor: cat.color || '#ccc' }"></div>
                            <span class="text-sm font-medium text-surface-700">{{ cat.name }}</span>
                        </div>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button icon="pi pi-pencil" text rounded size="small" severity="info" @click="editCategoryObj(cat)" />
                            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteCategory(cat)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
:deep(.p-inputtext) {
    border-radius: 0.75rem;
    padding: 0.5rem 1rem; 
}
:deep(.p-colorpicker-preview) {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 20px;
}
</style>