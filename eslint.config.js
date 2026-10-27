import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx,js}'],
        languageOptions: {
            globals: {
                window: 'readonly',
                document: 'readonly',
            },
        },
        rules: {
            '@typescript-eslint/no-unused-vars': 'off',
            '@typescript-eslint/no-explicit-any': 'warn',
        },
    },
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**'],
    }
);
