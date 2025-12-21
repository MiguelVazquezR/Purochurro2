<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import debounce from 'lodash/debounce';

const props = defineProps({
    employees: Object, // Paginator Object
    filters: Object,
});

const confirm = useConfirm();
const toast = useToast();

const search = ref(props.filters.search || '');

const onSearch = debounce((value) => {
    router.get(route('employees.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch(search, (value) => {
    onSearch(value);
});

const onRowClick = (event) => {
    if (event.originalEvent.target.closest('.action-btn')) return;
    router.visit(route('employees.show', event.data.id));
};

const deleteEmployee = (employee) => {
    confirm.require({
        message: `¿Estás seguro de dar de baja a ${employee.first_name} ${employee.last_name}?`,
        header: 'Confirmar Baja',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Dar de Baja',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(route('employees.destroy', employee.id), {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Procesado', detail: 'Empleado dado de baja correctamente', life: 3000 });
                }
            });
        }
    });
};

const getInitials = (firstName, lastName) => {
    return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
};
</script>

<template>
    <AppLayout title="Usuarios">
        <div class="w-full flex flex-col gap-6">
            
            <!-- Encabezado -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-surface-900">Usuarios</h1>
                    <p class="text-surface-500 text-sm mt-1">Gestión de empleados, accesos y asistencia.</p>
                </div>
                
                <div class="flex gap-3">
                    <Link :href="route('employees.create')">
                        <Button 
                            label="Nuevo empleado" 
                            icon="pi pi-user-plus" 
                            class="!bg-orange-600 !border-orange-600 hover:!bg-orange-700 font-semibold shadow-lg shadow-orange-200/50" 
                            rounded 
                        />
                    </Link>
                </div>
            </div>

            <!-- Tabla Glassmorphism -->
            <div class="bg-white/80 backdrop-blur-xl border border-surface-200 rounded-3xl shadow-xl overflow-hidden p-1 flex flex-col h-full">
                
                <DataTable 
                    :value="employees.data" 
                    dataKey="id" 
                    selectionMode="single"
                    @row-click="onRowClick"
                    class="p-datatable-sm"
                    sortMode="multiple"
                    removableSort
                    :pt="{
                        root: { class: 'rounded-2xl overflow-hidden' },
                        header: { class: '!bg-transparent !border-0 !p-4' },
                        thead: { class: '!bg-surface-50' },
                        bodyRow: { class: 'hover:!bg-orange-50/50 transition-colors duration-200 cursor-pointer' }
                    }"
                >
                    <!-- Header Tabla (Buscador) -->
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                            <span class="text-lg font-semibold text-surface-700 pl-2 hidden sm:block">Directorio</span>
                            <IconField iconPosition="left" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search text-surface-400" />
                                </InputIcon>
                                <InputText 
                                    v-model="search" 
                                    placeholder="Buscar por nombre..." 
                                    class="!rounded-full !bg-surface-50 !border-surface-200 focus:!ring-orange-200 w-full sm:w-80" 
                                />
                            </IconField>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center p-8 text-surface-400">
                            <i class="pi pi-users text-4xl mb-3 opacity-50"></i>
                            <p>No se encontraron empleados.</p>
                        </div>
                    </template>

                    <!-- Columna: Foto -->
                    <Column header="Foto" class="w-[80px]">
                        <template #body="slotProps">
                            <div class="relative">
                                <img 
                                    v-if="slotProps.data.profile_photo_url" 
                                    :src="slotProps.data.profile_photo_url" 
                                    alt="Foto"
                                    class="size-[46px] rounded-full object-cover border border-surface-200 shadow-sm"
                                />
                                <Avatar 
                                    v-else 
                                    :label="getInitials(slotProps.data.first_name, slotProps.data.last_name)" 
                                    shape="circle" 
                                    size="large"
                                    class="!bg-orange-100 !text-orange-600 !border !border-orange-200"
                                />
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: ID de Usuario (Para Login) -->
                    <Column field="user_id" header="ID Acceso" sortable class="w-[120px]">
                        <template #body="slotProps">
                            <div class="inline-flex items-center justify-center px-2 py-1 bg-surface-100 border border-surface-200 rounded-md">
                                <span class="font-mono font-bold text-surface-700 text-sm">#{{ slotProps.data.user_id }}</span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Nombre -->
                    <Column field="first_name" header="Nombre" sortable class="min-w-[200px]">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span class="font-bold text-surface-800 text-base">
                                    {{ slotProps.data.first_name }} {{ slotProps.data.last_name }}
                                </span>
                                <span class="text-xs text-surface-500">
                                    Puesto: Empleado
                                </span>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Contacto -->
                    <Column header="Contacto" class="min-w-[200px] hidden md:table-cell">
                        <template #body="slotProps">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2 text-sm text-surface-600">
                                    <i class="pi pi-envelope text-surface-400"></i>
                                    <span>{{ slotProps.data.email }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-surface-600">
                                    <i class="pi pi-phone text-surface-400"></i>
                                    <span>{{ slotProps.data.phone }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- Columna: Estado -->
                    <Column field="is_active" header="Estado" sortable class="w-[100px]">
                        <template #body="slotProps">
                            <Tag 
                                :value="slotProps.data.is_active ? 'Activo' : 'Baja'" 
                                :severity="slotProps.data.is_active ? 'success' : 'danger'" 
                                rounded
                                class="!px-2 !py-0.5 !text-xs !font-medium"
                            />
                        </template>
                    </Column>

                    <!-- Columna: Acciones -->
                    <Column header="" class="w-[100px] text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1 action-btn">
                                <Button 
                                    icon="pi pi-pencil" 
                                    text 
                                    rounded 
                                    severity="secondary" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-orange-600 hover:!bg-orange-50"
                                    v-tooltip.top="'Editar'"
                                    @click="router.get(route('employees.edit', slotProps.data.id))"
                                />
                                <Button 
                                    icon="pi pi-trash" 
                                    text 
                                    rounded 
                                    severity="danger" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-red-600 hover:!bg-red-50"
                                    v-tooltip.top="'Dar de Baja'"
                                    @click="deleteEmployee(slotProps.data)"
                                />
                            </div>
                        </template>
                    </Column>

                </DataTable>

                <!-- Paginación Personalizada -->
                <div v-if="employees.links && employees.links.length > 3" class="p-4 border-t border-surface-100 flex justify-center">
                    <div class="flex gap-1">
                        <template v-for="(link, key) in employees.links" :key="key">
                            <Link 
                                v-if="link.url" 
                                :href="link.url"
                                class="px-3 py-1 text-sm rounded-full transition-colors"
                                :class="link.active 
                                    ? 'bg-orange-50 text-orange-600 font-bold border border-orange-100' 
                                    : 'text-surface-500 hover:bg-surface-50 hover:text-surface-900'"
                                v-html="link.label"
                            />
                            <span 
                                v-else 
                                class="px-3 py-1 text-sm text-surface-300" 
                                v-html="link.label"
                            ></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-wrapper) {
    border-radius: 0; 
}
</style>