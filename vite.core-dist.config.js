import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

// Release-only build target: compiles k-core's assets into k-core/dist with fixed,
// unhashed filenames so a Composer-installed (non-Vite) consumer can reference them
// directly. Committed to git as part of a tagged release, then carried into
// kopl.ing/core by the existing subsplit workflow -- see .github/workflows/release.yml.
// Node/Vite only ever run here, in CI, never on a Kopling site's host.
export default defineConfig({
    plugins: [tailwindcss()],
    // Vite copies publicDir into outDir by default -- k-core/dist must contain only its
    // own compiled assets, never the monorepo's public/ (index.php, .htaccess, etc).
    publicDir: false,
    build: {
        outDir: 'k-core/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: fileURLToPath(new URL('./k-core/src/Ux/js/app.js', import.meta.url)),
                style: fileURLToPath(new URL('./k-core/src/Ux/css/app.css', import.meta.url)),
                editor: fileURLToPath(new URL('./k-core/src/Ux/js/editor.js', import.meta.url)),
                'editor-style': fileURLToPath(new URL('./k-core/src/Ux/css/editor.css', import.meta.url)),
                'emoji-picker': fileURLToPath(new URL('./k-core/src/Ux/js/emoji-picker.js', import.meta.url)),
                'emoji-picker-style': fileURLToPath(new URL('./k-core/src/Ux/css/emoji-picker.css', import.meta.url)),
            },
            output: {
                // editor.js/emoji-picker.js each dynamically import() their own real payload
                // (editor-tiptap.js / emoji-picker-mart.js) so pages without a mount point, or
                // whose picker nobody ever opened, never load it -- chunkFileNames keeps each
                // split chunk's own name fixed/unhashed too, same reasoning entryFileNames
                // already applies to the real entries.
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: (asset) => {
                    const name = asset.names?.[0] ?? asset.name;

                    if (name === 'style.css') return 'app.css';
                    if (name === 'editor-style.css') return 'editor.css';
                    if (name === 'emoji-picker-style.css') return 'emoji-picker.css';

                    return '[name][extname]';
                },
            },
        },
    },
});
