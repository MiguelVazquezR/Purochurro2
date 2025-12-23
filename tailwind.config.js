import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
// 1. Importar el plugin oficial de PrimeUI
import primeui from 'tailwindcss-primeui';

/** @type {import('tailwindcss').Config} */
export default {
    // Habilitar el modo oscuro mediante clase (necesario para PrimeVue Dark Mode)
    darkMode: ['selector', '.app-dark'], 

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Aquí podrías extender otros valores si quisieras
        },
    },

    // 2. Agregar 'primeui' a la lista de plugins
    plugins: [forms, typography, primeui],
};