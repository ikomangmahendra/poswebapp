import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/categories.js',
                'resources/js/categories-form.js',
                'resources/js/products.js',
                'resources/js/products-form.js',
                'resources/js/dashboard.js',
                'resources/js/transactions.js',
                'resources/js/transactions-show.js',
                'resources/js/transactions-create.js',
                'resources/js/users.js',
                'resources/js/users-form.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
