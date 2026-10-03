import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
        './config/volunteering.php',
    ],

    theme: {
        extend: {
            // IEEE Volunteering palette (volunteer.ieee.org): IEEE orange as the
            // action colour, charcoal for chrome, IEEE blue as the secondary accent.
            colors: {
                brand: {
                    DEFAULT: '#e87722',
                    dark: '#c4601a',
                    light: '#f39a4e',
                    50: '#fef5ed',
                    100: '#fce4cf',
                    200: '#f9c9a0',
                    600: '#d76a18',
                    700: '#b35614',
                    800: '#8a4210',
                },
                charcoal: {
                    DEFAULT: '#3a3a3a',
                    dark: '#262626',
                    light: '#4a4a4a',
                },
                cream: '#fafafa',
                'warm-white': '#f4f4f4',
                'light-gray': '#e4e4e4',
                ink: '#222222',
                'warm-gray': '#666666',
                'warmer-gray': '#3d3d3d',
                accent: {
                    blue: '#00629b',
                    'blue-dark': '#004b76',
                    'blue-light': '#bfe3f5',
                    green: '#00843d',
                    'green-dark': '#006a31',
                    'green-light': '#c9f0d8',
                },
            },
            fontFamily: {
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
                heading: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
                ui: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            maxWidth: {
                container: '1280px',
            },
            boxShadow: {
                card: '0 2px 10px rgba(0, 0, 0, 0.06)',
                dropdown: '0 6px 24px rgba(0, 0, 0, 0.12)',
                button: '0 2px 6px rgba(232, 119, 34, 0.25)',
            },
            borderRadius: {
                xl: '10px',
            },
        },
    },

    plugins: [forms],
};
