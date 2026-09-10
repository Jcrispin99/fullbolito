import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    server: {
        host: 'fullbolito.test',
        port: 5173,
        hmr: {
            host: 'fullbolito.test',
        },
        watch: {
            // Ignore tenant SQLite DBs and storage churn — Pest creates and
            // deletes per-test tenant DB files in database/ which would
            // otherwise trigger ENOENT in vite:css-analysis.
            ignored: [
                '**/database/**',
                '**/storage/**',
                '**/.git/**',
                '**/node_modules/**',
            ],
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/js/central/main.ts',
                'resources/js/tenant/main.ts',
            ],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@central': path.resolve(__dirname, './resources/js/central'),
            '@tenant': path.resolve(__dirname, './resources/js/tenant'),
        },
    },
})
