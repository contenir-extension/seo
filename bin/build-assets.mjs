import { readdir, rm } from 'node:fs/promises'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { build } from 'esbuild'

/**
 * Build the add-on's dashboard scripts into public/js, which is committed:
 * one ES module per component in assets/js/components, each
 * default-exporting its component class for the host's registry to mount,
 * with the code they share (BaseComponent) split into
 * public/js/chunks/. The stylesheet in public/css is written by hand.
 *
 * The output must depend on the sources alone, so CI can rebuild and diff
 * it: public/js is emptied first so a chunk whose hash changed leaves no
 * stale file behind, paths are taken relative to the package root, and no
 * source map is written.
 */
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const componentsDir = join(root, 'assets/js/components')
const jsOut = join(root, 'public/js')

const entryPoints = (await readdir(componentsDir))
    .filter((file) => file.endsWith('.js'))
    .sort()
    .map((file) => join(componentsDir, file))

await rm(jsOut, { recursive: true, force: true })

await build({
    absWorkingDir: root,
    entryPoints,
    outdir: jsOut,
    entryNames: '[name]',
    chunkNames: 'chunks/[name]-[hash]',
    format: 'esm',
    bundle: true,
    splitting: true,
    minify: true,
    target: 'es2020',
    sourcemap: false,
    legalComments: 'eof',
    logLevel: 'warning',
})
