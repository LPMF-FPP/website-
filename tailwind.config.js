import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,vue,jsx,ts,tsx}', // Added for Vue/React components
        './resources/**/*.js',
        './resources/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
                serif: ['Crimson Text', 'Georgia', 'Cambria', 'Times New Roman', 'Times', 'serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'Consolas', 'Monaco', 'Courier New', 'monospace'],
                display: ['Poppins', 'Inter', 'system-ui', 'sans-serif'],
                body: ['Inter', 'system-ui', 'sans-serif'],
            },
            fontSize: {
                'xs': ['0.75rem', { lineHeight: '1rem' }],
                'sm': ['0.875rem', { lineHeight: '1.25rem' }],
                'base': ['1rem', { lineHeight: '1.5rem' }],
                'lg': ['1.125rem', { lineHeight: '1.75rem' }],
                'xl': ['1.25rem', { lineHeight: '1.75rem' }],
                '2xl': ['1.5rem', { lineHeight: '2rem' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
                '5xl': ['3rem', { lineHeight: '1' }],
                '6xl': ['3.75rem', { lineHeight: '1' }],
                '7xl': ['4.5rem', { lineHeight: '1' }],
                '8xl': ['6rem', { lineHeight: '1' }],
                '9xl': ['8rem', { lineHeight: '1' }],
            },
            fontWeight: {
                'thin': '100',
                'extralight': '200',
                'light': '300',
                'normal': '400',
                'medium': '500',
                'semibold': '600',
                'bold': '700',
                'extrabold': '800',
                'black': '900',
            },
            letterSpacing: {
                'tighter': '-0.05em',
                'tight': '-0.025em',
                'normal': '0em',
                'wide': '0.025em',
                'wider': '0.05em',
                'widest': '0.1em',
            },
            colors: {
                // Medical/Health Color Palette inspired by Pusdokkes logo
                primary: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',  // Main green from logo
                    600: '#059669',  // Logo green
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                    950: '#022c22',
                },
                secondary: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',  // Logo yellow/gold
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                    950: '#451a03',
                },
                accent: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',  // Deep professional color
                    950: '#020617',
                },
                medical: {
                    green: '#059669',    // Logo green
                    gold: '#fbbf24',     // Logo gold
                    dark: '#1f2937',     // Logo dark
                    light: '#f0fdf4',    // Light medical green
                },
                // Semantic colors
                success: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
                info: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
            spacing: {
                'pd-1': 'var(--pd-spacing-1)',
                'pd-2': 'var(--pd-spacing-2)',
                'pd-3': 'var(--pd-spacing-3)',
                'pd-4': 'var(--pd-spacing-4)',
                'pd-5': 'var(--pd-spacing-5)',
                'pd-6': 'var(--pd-spacing-6)',
                'pd-8': 'var(--pd-spacing-8)',
                'pd-10': 'var(--pd-spacing-10)',
                'pd-12': 'var(--pd-spacing-12)',
            },
            borderRadius: {
                'pd-sm': 'var(--pd-radius-sm)',
                'pd-md': 'var(--pd-radius-md)',
                'pd-lg': 'var(--pd-radius-lg)',
                'pd-xl': 'var(--pd-radius-xl)',
            },
            boxShadow: {
                'pd-sm': 'var(--pd-shadow-sm)',
                'pd-md': 'var(--pd-shadow-md)',
                'pd-lg': 'var(--pd-shadow-lg)',
            },
            zIndex: {
                'pd-nav': 'var(--pd-z-nav)',
                'pd-overlay': 'var(--pd-z-overlay)',
                'pd-toast': 'var(--pd-z-toast)',
                'pd-modal': 'var(--pd-z-modal)',
            },
            screens: {
                'pd-sm': 'var(--pd-breakpoints-sm)',
                'pd-md': 'var(--pd-breakpoints-md)',
                'pd-lg': 'var(--pd-breakpoints-lg)',
                'pd-xl': 'var(--pd-breakpoints-xl)',
                'pd-2xl': 'var(--pd-breakpoints-2xl)',
            },
            animation: {
                'pd-fast': 'var(--pd-motion-fast)',
                'pd-base': 'var(--pd-motion-base)',
                'pd-slow': 'var(--pd-motion-slow)',
            },
            transitionDuration: {
                'pd-fast': 'var(--pd-motion-fast)',
                'pd-base': 'var(--pd-motion-base)',
                'pd-slow': 'var(--pd-motion-slow)',
            },
            transitionTimingFunction: {
                'pd': 'var(--pd-motion-easing)',
            },
        },
    },

    plugins: [
        forms,
        // Custom plugin to add Pusdokkes Design System utilities
        function({ addUtilities, theme }) {
            const newUtilities = {
                // Focus ring utilities
                '.focus-pd': {
                    '&:focus': {
                        outline: '2px solid var(--pd-color-primary)',
                        'outline-offset': '2px',
                    },
                    '&:focus:not(:focus-visible)': {
                        outline: 'none',
                    },
                    '&:focus-visible': {
                        outline: '2px solid var(--pd-color-primary)',
                        'outline-offset': '2px',
                        'border-radius': 'var(--pd-radius-sm)',
                    },
                },
                // Surface utilities
                '.surface-pd': {
                    'background-color': 'var(--pd-color-surface)',
                    border: '1px solid var(--pd-color-border)',
                },
                // Card utilities
                '.card-pd': {
                    'background-color': 'var(--pd-color-surface)',
                    border: '1px solid var(--pd-color-border)',
                    'border-radius': 'var(--pd-radius-lg)',
                    padding: 'var(--pd-spacing-6)',
                    'box-shadow': 'var(--pd-shadow-sm)',
                    transition: 'box-shadow var(--pd-motion-base) var(--pd-motion-easing), transform var(--pd-motion-base) var(--pd-motion-easing)',
                },
                '.card-pd:hover': {
                    'box-shadow': 'var(--pd-shadow-md)',
                    transform: 'translateY(-2px)',
                },
                // Button utilities
                '.btn-pd': {
                    display: 'inline-flex',
                    'align-items': 'center',
                    'justify-content': 'center',
                    gap: 'var(--pd-spacing-2)',
                    padding: 'var(--pd-spacing-3) var(--pd-spacing-5)',
                    border: 'none',
                    'border-radius': 'var(--pd-radius-md)',
                    'font-size': '1rem',
                    'font-weight': '500',
                    'text-decoration': 'none',
                    cursor: 'pointer',
                    transition: 'all var(--pd-motion-fast) var(--pd-motion-easing)',
                    'white-space': 'nowrap',
                },
                '.btn-pd-primary': {
                    'background-color': 'var(--pd-color-primary)',
                    color: 'white',
                },
                '.btn-pd-primary:hover': {
                    'background-color': 'rgba(var(--pd-color-primary-rgb), 0.9)',
                    transform: 'translateY(-1px)',
                    'box-shadow': 'var(--pd-shadow-md)',
                },
                // Form utilities
                '.input-pd': {
                    width: '100%',
                    padding: 'var(--pd-spacing-3)',
                    border: '1px solid var(--pd-color-border)',
                    'border-radius': 'var(--pd-radius-md)',
                    'background-color': 'var(--pd-color-bg)',
                    color: 'var(--pd-color-text)',
                    'font-size': '1rem',
                    transition: 'border-color var(--pd-motion-fast) var(--pd-motion-easing), box-shadow var(--pd-motion-fast) var(--pd-motion-easing)',
                },
                '.input-pd:focus': {
                    outline: 'none',
                    'border-color': 'var(--pd-color-primary)',
                    'box-shadow': 'var(--pd-focus-ring)',
                },
            };
            addUtilities(newUtilities);
        },
    ],
};
