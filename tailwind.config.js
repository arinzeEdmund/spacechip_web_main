import { readFileSync } from 'node:fs';
import defaultTheme from 'tailwindcss/defaultTheme';

// Theme-aware colours (light/dark via CSS variables): resources/theme/generate-palette.cjs
const colors = JSON.parse(readFileSync(new URL('./resources/theme/tailwind-colors.json', import.meta.url), 'utf8'));

/**
 * Mirrors the Play CDN configuration the views were written against
 * (class-based dark mode, no forms plugin) so compiled output matches.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['-apple-system', 'BlinkMacSystemFont', '"SF Pro Text"', '"Instrument Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors,
            boxShadow: {
                card: 'var(--shadow-2)',
                lift: 'var(--shadow-3)',
            },
        },
    },

    plugins: [],
};
