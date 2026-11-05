import { defineConfig } from 'vitest/config'

export default defineConfig({
    test: {
        /**
         * The components under test manipulate the DOM directly, so they
         * need a document. jsdom is rebuilt per test file, which keeps
         * files from leaking DOM state into each other.
         */
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        restoreMocks: true,
        unstubGlobals: true,
        /**
         * Coverage is over the scripts alone (`npm run test:coverage`), and
         * every line, branch, function and statement of them must be run.
         */
        coverage: {
            provider: 'v8',
            include: ['assets/js/**/*.js'],
            reporter: ['text-summary', 'lcov'],
            reportsDirectory: 'build/coverage-js',
            thresholds: {
                lines: 100,
                branches: 100,
                functions: 100,
                statements: 100,
            },
        },
    },
})
