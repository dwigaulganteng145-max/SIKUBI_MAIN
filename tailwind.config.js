import defaultTheme from 'tailwindcss-preset-carbon';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Outfit', 'Inter', 'system-ui', 'sans-serif'],
                serif: ['Outfit', 'Inter', 'system-ui', 'sans-serif'],
                display: ['Outfit', 'Inter', 'system-ui', 'sans-serif'],
                body: ['Outfit', 'Inter', 'system-ui', 'sans-serif'],
            },
            colors: {
                rose: {
                    50: '#FFF5F6',
                    100: '#FFE8EB',
                    200: '#FFD0D6',
                    300: '#FFB0BA',
                    400: '#F4899A',
                    500: '#E8637A',
                    600: '#D44A62',
                    700: '#B03550',
                    800: '#862840',
                    900: '#5E1C30',
                    gold: '#E8637A',
                },
                champagne: {
                    50: '#FFF9F0',
                    100: '#FFF2DD',
                    200: '#FFE5BC',
                    300: '#F2C97A',
                    400: '#DDB05E',
                    500: '#C49A4A',
                    600: '#A07D38',
                    700: '#7C6028',
                    800: '#5E4820',
                    900: '#403018',
                },
                cream: {
                    DEFAULT: '#FDF8F3',
                    50: '#FFFFFF',
                    100: '#FEFBF8',
                    200: '#FDF6EF',
                    300: '#F8EDE0',
                    400: '#F0E0CE',
                    500: '#E4D0BA',
                },
                plum: {
                    DEFAULT: '#2C1929',
                    50: '#F8EFF5',
                    100: '#EDDCE8',
                    200: '#D4B0CA',
                    300: '#B882A8',
                    400: '#8C5878',
                    500: '#6B3C5C',
                    600: '#4E2844',
                    700: '#3A1E34',
                    800: '#2C1929',
                    900: '#1A0F1A',
                },
                surface: {
                    50: '#FFFFFF',
                    100: '#FEFBF8',
                    200: '#F8F2EC',
                    300: '#EDE4DB',
                    400: '#DDD2C6',
                    500: '#B5A899',
                    600: '#8A7E70',
                    700: '#635850',
                    800: '#443C34',
                    900: '#2A2520',
                },
            },
            boxShadow: {
                soft: '0 1px 3px rgba(232, 99, 122, 0.08), 0 4px 12px rgba(0,0,0,0.03)',
                card: '0 2px 8px rgba(232, 99, 122, 0.06), 0 1px 3px rgba(0,0,0,0.04)',
                'card-hover': '0 8px 24px rgba(232, 99, 122, 0.12), 0 2px 8px rgba(0,0,0,0.05)',
                elevated: '0 12px 40px rgba(232, 99, 122, 0.14), 0 4px 12px rgba(0,0,0,0.06)',
                glow: '0 0 20px rgba(232, 99, 122, 0.18)',
                'glow-lg': '0 0 40px rgba(232, 99, 122, 0.24)',
                inner: 'inset 0 1px 3px rgba(0,0,0,0.04)',
            },
            backgroundImage: {
                'gradient-rose': 'linear-gradient(135deg, #E8637A 0%, #F4899A 100%)',
                'gradient-gold': 'linear-gradient(135deg, #C49A4A 0%, #DDB05E 100%)',
                'gradient-plum': 'linear-gradient(135deg, #2C1929 0%, #4E2844 100%)',
                'gradient-cream': 'linear-gradient(135deg, #FDF8F3 0%, #FFF5F6 50%, #FFF9F0 100%)',
                'gradient-subtle': 'linear-gradient(180deg, rgba(253,248,243,0) 0%, rgba(232,99,122,0.03) 100%)',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.25rem',
                '4xl': '1.5rem',
            },
            animation: {
                'fade-in': 'fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both',
                'slide-up': 'slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both',
                'slide-right': 'slideRight 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
                'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
                shimmer: 'shimmer 2s linear infinite',
                float: 'float 6s ease-in-out infinite',
                'scale-in': 'scaleIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideRight: {
                    '0%': { opacity: '0', transform: 'translateX(-20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                pulseSoft: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.6' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
                scaleIn: {
                    '0%': { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
            },
        },
    },
    plugins: [forms],
};
