import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta institucional
                marca: {
                    50: '#eef4ff', 100: '#d9e5ff', 200: '#bcd2ff', 300: '#8eb5ff',
                    400: '#598cff', 500: '#3363ff', 600: '#1d41f5', 700: '#162fe1',
                    800: '#1829b6', 900: '#1a2a8f', 950: '#141a57',
                },
            },
        },
    },
    plugins: [],
};
