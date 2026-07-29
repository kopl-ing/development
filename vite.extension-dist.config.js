import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';
import { existsSync, readdirSync } from 'node:fs';

// Release-only build target for ONE extension's compiled assets, mirroring
// vite.core-dist.config.js's own shape. Parameterized via KOPLING_EXTENSION (the directory
// name under k-extensions/) rather than one hand-copied config per extension --
// scripts/build-extension-assets.mjs invokes this once per extension that opts in (has at
// least one resources/js/*.js file). Node/Vite only ever run here, in CI, at release time --
// never on a Kopling site's host, same rule as k-core's own dist build.
const extension = process.env.KOPLING_EXTENSION;

if (!extension) {
    throw new Error('vite.extension-dist.config.js requires the KOPLING_EXTENSION env var to be set.');
}

const root = fileURLToPath(new URL(`./k-extensions/${extension}/`, import.meta.url));
const jsDir = `${root}resources/js`;

// Every resources/js/{name}.js is its own bundle, not a single hardcoded "app" entry --
// PortalExtension::compiledAssets() lets an extension attaching to multiple Portals ship a
// different one per Portal (auto-derived from the Portal's own id, or an explicit override),
// so a fixed single entry can't express that. A matching resources/css/{name}.css is optional.
const names = readdirSync(jsDir)
    .filter((file) => file.endsWith('.js'))
    .map((file) => file.slice(0, -'.js'.length));

const input = {};

for (const name of names) {
    input[name] = `${jsDir}/${name}.js`;

    const stylePath = `${root}resources/css/${name}.css`;

    if (existsSync(stylePath)) {
        // Suffixed so this CSS entry's own output chunk doesn't collide with the JS entry's --
        // assetFileNames below strips it back off, same convention vite.core-dist.config.js
        // already established for k-core's own multi-bundle output.
        input[`${name}-style`] = stylePath;
    }
}

export default defineConfig({
    plugins: [tailwindcss()],
    // Same publicDir gotcha vite.core-dist.config.js already caught -- an extension's dist
    // must contain only its own compiled assets, never the monorepo's public/.
    publicDir: false,
    build: {
        outDir: `${root}dist`,
        emptyOutDir: true,
        rollupOptions: {
            input,
            output: {
                // Fixed, unhashed filenames -- a Composer-installed (non-Vite) consumer
                // references dist/{name}.css + dist/{name}.js directly, same convention
                // k-core's own dist output already established.
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: (asset) => {
                    const name = asset.names?.[0] ?? asset.name;

                    return name.endsWith('-style.css')
                        ? name.slice(0, -'-style.css'.length) + '.css'
                        : '[name][extname]';
                },
            },
        },
    },
});
