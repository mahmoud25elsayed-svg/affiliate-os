/**
 * Utilities for the hand-written Blade views rendered inside the Filament panels
 * (resources/views/filament/**). Filament v4 ships CSS only for its own classes, so any
 * Tailwind class used in these views was unstyled. Preflight is off: Filament owns the base.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: ['./resources/views/filament/**/*.blade.php', './app/Filament/**/*.php'],
    darkMode: 'class',
    corePlugins: { preflight: false },
    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
