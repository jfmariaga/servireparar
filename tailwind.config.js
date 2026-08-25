/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './vendor/livewire/livewire/src/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Arial', 'sans-serif'],
                display: ['"Space Grotesk"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                brand: {
                    blue: '#2648d6',
                    'blue-dark': '#1b34a6',
                    'blue-tint': '#eef1fd',
                    red: '#e0332c',
                    'red-tint': '#fdeceb',
                    navy: '#12172b',
                    'navy-soft': '#171d38',
                    'navy-active': '#1d2340',
                    'navy-hover': '#1a2040',
                },
            },
        },
    },
    plugins: [],
};
