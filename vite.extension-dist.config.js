import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

// Release-only build target for ONE extension's compiled assets, mirroring
// vite.core-dist.config.js's own shape. Parameterized via KOPLING_EXTENSION (the directory
// name under k-extensions/) rather than one hand-copied config per extension --
// scripts/build-extension-assets.mjs invokes this once per extension that opts in (has its
// own resources/js/app.js). Node/Vite only ever run here, in CI, at release time -- never on
// a Kopling site's host, same rule as k-core's own dist build.
const extension = process.env.KOPLING_EXTENSION;

if (!extension) {
    throw new Error('vite.extension-dist.config.js requires the KOPLING_EXTENSION env var to be set.');
}

const root = fileURLToPath(new URL(`./k-extensions/${extension}/`, import.meta.url));

export default defineConfig({
    plugins: [tailwindcss()],
    // Same publicDir gotcha vite.core-dist.config.js already caught -- an extension's dist
    // must contain only its own compiled assets, never the monorepo's public/.
    publicDir: false,
    build: {
        outDir: `${root}dist`,
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: `${root}resources/js/app.js`,
                style: `${root}resources/css/app.css`,
            },
            output: {
                // Fixed, unhashed filenames -- a Composer-installed (non-Vite) consumer
                // references dist/app.css + dist/app.js directly, same convention k-core's
                // own dist output already established.
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: (asset) => {
                    const name = asset.names?.[0] ?? asset.name;

                    return name === 'style.css' ? 'app.css' : '[name][extname]';
                },
            },
        },
    },
});
