/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.ts',
        './resources/**/*.tsx',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', 'system-ui', 'sans-serif'],
                mono: ['DM Mono', 'monospace'],
            },
            colors: {
                surface: {
                    DEFAULT: '#111114',
                    2: '#18181b',
                    3: '#1e1e23',
                },
                border: {
                    DEFAULT: '#27272a',
                    light: '#3f3f46',
                },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
};
