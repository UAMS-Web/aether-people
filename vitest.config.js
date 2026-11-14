import { configDefaults, defineConfig } from 'vitest/config';

// Standalone Vitest config for the repository's own Node scripts (the guards in `scripts/`) and the
// framework-free JavaScript under `resources/js/` (the person-name engine). The add-on's PHP tests
// run under Pest, not here.
export default defineConfig({
    test: {
        // `.claude/worktrees/` holds full checkouts of other branches, each carrying its own copy
        // of every test file; without the exclude the primary collects them all.
        include: ['scripts/**/*.test.mjs', 'resources/js/**/*.test.js'],
        exclude: [...configDefaults.exclude, '.claude/worktrees/**'],
        environment: 'node',
    },
});
