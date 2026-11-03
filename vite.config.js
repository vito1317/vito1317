import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import Prerenderer from 'vite-plugin-prerenderer';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'index.html'],
            refresh: true,
        }),
        vue(),
        Prerenderer({
            staticDir: 'public/build',

            indexPath: 'index.html',

            renderer: '@prerenderer/renderer-puppeteer',
            routes: [
                '/',
                '/news',
            ],
            rendererOptions: {
              maxConcurrentRoutes: 1,
              inject: {},
              renderAfterTime: 5000,
            }
        }),
    ],
    resolve: {
        alias: {
            // 從 gac-soc 搬來的 agent 動畫（thinking-orbs / border-beam / metal-fx，MIT）
            '@agentfx': fileURLToPath(new URL('./resources/js/vendor', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
    }
});