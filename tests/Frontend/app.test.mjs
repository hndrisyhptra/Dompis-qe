import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

test('entry point aplikasi valid sebagai modul JavaScript', () => {
    execFileSync(process.execPath, [
        '--check',
        fileURLToPath(new URL('../../resources/js/app.js', import.meta.url)),
    ], { stdio: 'pipe' });
});
