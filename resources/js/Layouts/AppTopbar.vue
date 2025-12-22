<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import Dialog from 'primevue/dialog';
import Menu from 'primevue/menu';

const emit = defineEmits(['toggle-sidebar']);
const page = usePage();
const toast = useToast();
const user = page.props.auth.user;

// --- ESTADO DE ASISTENCIA ---
const attendanceState = ref('loading'); // none, checked_in, completed
const checkInTime = ref(null);
const checkOutTime = ref(null);
const op = ref(); // Referencia al Popover

// --- ESTADO DE CÁMARA ---
const showCameraModal = ref(false);
const videoRef = ref(null);
const canvasRef = ref(null);
const isCameraActive = ref(false);
const isProcessing = ref(false);
const stream = ref(null);

// Menú de usuario
const userMenu = ref();
const userMenuItems = ref([
    {
        label: 'Perfil',
        icon: 'pi pi-user',
        command: () => router.get(route('profile.show'))
    },
    { separator: true },
    {
        label: 'Cerrar Sesión',
        icon: 'pi pi-sign-out',
        command: () => router.post(route('logout'))
    }
]);

// Cargar estado inicial al montar (Solo si no es super admin id=1, opcional)
onMounted(async () => {
    if (user.id !== 1) {
        try {
            const response = await axios.get(route('attendance.status'));
            if (response.data.status === 'success') {
                attendanceState.value = response.data.state;
                checkInTime.value = response.data.check_in;
                checkOutTime.value = response.data.check_out;
            }
        } catch (error) {
            console.error('Error cargando asistencia', error);
            attendanceState.value = 'error';
        }
    } else {
        attendanceState.value = 'admin'; // Estado especial para admin
    }
});

// Configuración del botón según estado
const buttonConfig = computed(() => {
    switch (attendanceState.value) {
        case 'none':
            return { label: 'Registrar asistencia', icon: 'pi pi-clock', severity: 'info', class: 'bg-blue-600 border-blue-600' };
        case 'checked_in':
            return { label: 'Registrar salida', icon: 'pi pi-sign-out', severity: 'warn', class: 'bg-orange-500 border-orange-500' };
        case 'completed':
            return { label: 'Jornada completada', icon: 'pi pi-check-circle', severity: 'success', class: 'bg-green-600 border-green-600', disabled: false }; // Disabled false para ver el popover
        default:
            return { label: '', icon: 'pi pi-spin pi-spinner', severity: 'secondary', disabled: true, class: 'opacity-0' };
    }
});

const toggleAttendance = (event) => {
    op.value.toggle(event);
};

const openCamera = () => {
    op.value.hide(); // Ocultar popover
    showCameraModal.value = true;
    startCamera();
};

// --- CÁMARA ---
const startCamera = async () => {
    try {
        isCameraActive.value = true;
        const mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        stream.value = mediaStream;
        // Timeout para asegurar que el ref existe en el DOM
        setTimeout(() => {
            if (videoRef.value) {
                videoRef.value.srcObject = mediaStream;
            }
        }, 100);
    } catch (err) {
        console.error("Error cámara:", err);
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo acceder a la cámara.', life: 3000 });
        showCameraModal.value = false;
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    isCameraActive.value = false;
};

const captureAndRegister = async () => {
    if (!videoRef.value || !canvasRef.value) return;
    isProcessing.value = true;

    // Capturar frame
    const context = canvasRef.value.getContext('2d');
    canvasRef.value.width = videoRef.value.videoWidth;
    canvasRef.value.height = videoRef.value.videoHeight;
    context.drawImage(videoRef.value, 0, 0, canvasRef.value.width, canvasRef.value.height);
    const imageBase64 = canvasRef.value.toDataURL('image/jpeg', 0.8);

    try {
        // Usamos la nueva ruta 'attendance.web' que apunta a registerWeb
        const response = await axios.post(route('attendance.web'), { image: imageBase64 });

        if (response.data.status === 'success') {
            toast.add({ severity: 'success', summary: 'Registrado', detail: response.data.message, life: 4000 });
            
            // Actualizar estado local
            if (response.data.type === 'entrada') {
                attendanceState.value = 'checked_in';
                checkInTime.value = response.data.time;
            } else if (response.data.type === 'salida') {
                attendanceState.value = 'completed';
                checkOutTime.value = response.data.time;
            }
            
            showCameraModal.value = false;
            stopCamera();
        } else {
             // Warnings o Info
             toast.add({ severity: 'warn', summary: 'Atención', detail: response.data.message, life: 4000 });
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Error al procesar la imagen.';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 });
    } finally {
        isProcessing.value = false;
    }
};

const onModalHide = () => {
    stopCamera();
};

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};
</script>

<template>
    <header class="h-16 bg-white/80 backdrop-blur-md border-b border-surface-200 flex items-center justify-between px-4 md:px-6 shadow-sm transition-all duration-300">
        
        <!-- Izquierda: Toggle y Logo -->
        <div class="flex items-center gap-4">
            <button 
                @click="$emit('toggle-sidebar')" 
                class="p-2 rounded-lg hover:bg-surface-100 text-surface-500 transition-colors focus:outline-none focus:ring-2 focus:ring-orange-200"
            >
                <i class="pi pi-bars text-xl"></i>
            </button>

            <Link :href="route('dashboard')" class="flex items-center gap-2 group">
                <AuthenticationCardLogo class="w-20 text-orange-600 group-hover:scale-110 transition-transform duration-300" />
            </Link>
        </div>

        <!-- Derecha: Asistencia y Perfil -->
        <div class="flex items-center gap-4">
            
            <!-- BOTÓN DE ASISTENCIA (Oculto para Admin ID 1 si se desea) -->
            <div v-if="user.id !== 1 && attendanceState !== 'loading'">
                <Button 
                    :label="buttonConfig.label" 
                    :icon="buttonConfig.icon" 
                    :severity="buttonConfig.severity"
                    :disabled="buttonConfig.disabled && attendanceState !== 'completed'"
                    size="small"
                    rounded
                    @click="toggleAttendance"
                    class="font-bold !hidden md:!flex"
                    :class="buttonConfig.class"
                />
                <!-- Móvil solo ícono -->
                <Button 
                    :icon="buttonConfig.icon" 
                    :severity="buttonConfig.severity"
                    rounded
                    @click="toggleAttendance"
                    class="md:!hidden !w-9 !h-9"
                    :class="buttonConfig.class"
                />

                <!-- POPOVER DE DETALLES -->
                <Popover ref="op">
                    <div class="p-3 w-64">
                        <div class="flex flex-col gap-3">
                            <div class="text-center">
                                <span class="font-bold text-surface-900 block text-lg mb-1 capitalize">
                                    {{ new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }) }}
                                </span>
                                <span class="text-xs text-surface-500 uppercase tracking-wider font-semibold">Tu asistencia</span>
                            </div>
                            
                            <div class="bg-surface-50 rounded-lg p-3 border border-surface-100 space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-surface-600"><i class="pi pi-sign-in mr-1 text-green-500"></i> Entrada:</span>
                                    <span class="font-mono font-bold text-surface-900">{{ checkInTime || '--:--' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-surface-600"><i class="pi pi-sign-out mr-1 text-red-500"></i> Salida:</span>
                                    <span class="font-mono font-bold text-surface-900">{{ checkOutTime || '--:--' }}</span>
                                </div>
                            </div>

                            <div v-if="attendanceState === 'none'" class="text-center">
                                <Button label="Iniciar jornada" icon="pi pi-camera" class="w-full" @click="openCamera" />
                            </div>
                            <div v-else-if="attendanceState === 'checked_in'" class="text-center">
                                <Button label="Terminar jornada" severity="danger" icon="pi pi-camera" class="w-full" @click="openCamera" />
                            </div>
                            <div v-else-if="attendanceState === 'completed'" class="text-center">
                                <span class="text-green-600 font-bold text-sm flex items-center justify-center gap-1 bg-green-50 p-2 rounded border border-green-100">
                                    <i class="pi pi-check-circle"></i> Asistencia completada
                                </span>
                            </div>
                        </div>
                    </div>
                </Popover>
            </div>

            <!-- Perfil de Usuario -->
            <button 
                @click="toggleUserMenu" 
                class="flex items-center gap-2 p-1.5 rounded-full border border-transparent hover:bg-surface-100 hover:border-surface-200 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-orange-200"
            >
                <img 
                    class="h-8 w-8 rounded-full object-cover border border-surface-200 shadow-sm" 
                    :src="user.profile_photo_url" 
                    :alt="user.name"
                >
                <span class="text-sm font-medium text-surface-700 hidden md:block pr-2">
                    {{ user.name }}
                </span>
                <i class="pi pi-chevron-down text-xs text-surface-400 hidden md:block mr-1"></i>
            </button>
            
            <Menu ref="userMenu" :model="userMenuItems" :popup="true" class="w-48" />
        </div>

        <!-- MODAL DE CÁMARA -->
        <Dialog 
            v-model:visible="showCameraModal" 
            modal 
            header="Verificación Biométrica" 
            :style="{ width: '30rem' }" 
            :breakpoints="{ '960px': '75vw', '641px': '95vw' }"
            @hide="onModalHide"
        >
            <div class="flex flex-col items-center gap-4">
                <p class="text-sm text-center text-surface-600">
                    Asegúrate de que tu rostro esté bien iluminado y centrado.
                </p>

                <!-- Contenedor de Video -->
                <div class="relative w-full aspect-video bg-black rounded-lg overflow-hidden border-2 border-surface-200 shadow-inner">
                    <!-- scale-x-100 para efecto espejo -->
                    <video ref="videoRef" autoplay playsinline class="w-full h-full object-cover transform -scale-x-100"></video>
                    
                    <!-- Guía Visual -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-50">
                        <div class="w-40 h-56 border-2 border-white/70 rounded-[50%] border-dashed"></div>
                    </div>
                    <canvas ref="canvasRef" class="hidden"></canvas>
                </div>

                <div class="w-full flex justify-center pt-2">
                    <Button 
                        :label="isProcessing ? 'Verificando...' : 'Capturar y Registrar'" 
                        :icon="isProcessing ? 'pi pi-spin pi-spinner' : 'pi pi-camera'" 
                        @click="captureAndRegister" 
                        :disabled="isProcessing || !isCameraActive"
                        class="w-full md:w-auto px-8"
                        rounded
                    />
                </div>
            </div>
        </Dialog>
    </header>
</template>