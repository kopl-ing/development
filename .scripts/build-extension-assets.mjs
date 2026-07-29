#!/usr/bin/env node
import { existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

// Discovers every k-extensions/*/resources/js/app.js (directory-convention opt-in, same
// philosophy migrations/, views/, lang/ already use for core -- see
// Manager::conventions()'s own docblock, no interface required) and runs
// vite.extension-dist.config.js once per extension found, each into its own
// k-extensions/{name}/dist/. Run at release time only (.github/workflows/release.yml), same
// as `npm run build:core-dist` -- never on a Kopling site's host.
const root = fileURLToPath(new URL('..', import.meta.url));
const extensionsDir = `${root}/k-extensions`;

const extensions = readdirSync(extensionsDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .filter((name) => existsSync(`${extensionsDir}/${name}/resources/js/app.js`));

if (extensions.length === 0) {
    console.log('No extensions with resources/js/app.js found -- nothing to build.');
}

for (const extension of extensions) {
    console.log(`Building compiled assets for k-extensions/${extension}...`);

    const result = spawnSync('npx', ['vite', 'build', '--config', 'vite.extension-dist.config.js'], {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env, KOPLING_EXTENSION: extension },
    });

    if (result.status !== 0) {
        process.exit(result.status ?? 1);
    }
}
