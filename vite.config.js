import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// k-core is subsplit into its own standalone repo (kopl.ing/core), so its source assets
// live inside k-core/src/Ux (alongside Ux/views -- Ux is core's UX/theming domain) -- not
// at the monorepo root -- the same way its PHP/Blade does. This config is dev-only
// (npm run dev / npm run build -> public/build, consumed by @vite()); the release-time
// build that k-core actually ships is vite.core-dist.config.js.
export default defineConfig({
    // Native filesystem events (inotify) have repeatedly failed to fire for edits to k-core's
    // own JS/CSS source in this WSL2 environment -- the dev server keeps serving a stale
    // transform of a changed file until restarted. Polling instead of relying on native events
    // is the standard fix for this class of problem (WSL2/network filesystems/some containers).
    server: {
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: [
                'k-core/src/Ux/css/app.css',
                'k-core/src/Ux/js/app.js',
                'k-core/src/Ux/css/editor.css',
                'k-core/src/Ux/js/editor.js',
                'k-core/src/Ux/css/emoji-picker.css',
                'k-core/src/Ux/js/emoji-picker.js',
                'k-core/src/Ux/css/tag-input.css',
                'k-core/src/Ux/js/tag-input.js',
                'k-core/src/Ux/css/icon-picker.css',
                'k-core/src/Ux/js/icon-picker.js',
            ],
            refresh: [
                'k-core/src/**/*.blade.php',
                'k-extensions/**/*.blade.php',
            ],
        }),
        tailwindcss(),
    ],
});
