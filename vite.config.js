import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        Components({
            resolvers: [
                PrimeVueResolver()
            ]
        })
    ],
    // comentar los siguientes bloques si no se usa el dominio purochurro.test
    server: {
        detectTls: 'purochurro.test',
        // 💡 Añade este bloque hmr para corregir el WebSocket de Vite:
        hmr: {
            host: 'purochurro.test',
            protocol: 'wss',
        },
    },
});
