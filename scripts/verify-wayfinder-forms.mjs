#!/usr/bin/env node
/**
 * Fails if Wayfinder was generated without --with-form (Inertia <Form {...x.form()} /> breaks).
 * Run after: php artisan wayfinder:generate --with-form && npm run wayfinder:strip
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const mustContain = [
    ['resources/js/routes/login/index.js', 'store.form'],
    ['resources/js/routes/register/index.js', 'store.form'],
    ['resources/js/routes/password/index.js', 'email.form'],
    ['resources/js/routes/password/index.js', 'update.form'],
    ['resources/js/routes/verification/index.js', 'send.form'],
    ['resources/js/routes/two-factor/index.js', 'confirm.form'],
    ['resources/js/actions/App/Http/Controllers/Settings/ProfileController.js', 'update.form'],
    ['resources/js/actions/App/Http/Controllers/Settings/SecurityController.js', 'update.form'],
];

let failed = false;

for (const [rel, needle] of mustContain) {
    const file = path.join(root, rel);
    const text = fs.readFileSync(file, 'utf8');
    if (!text.includes(needle)) {
        console.error(`[wayfinder] Missing "${needle}" in ${rel}. Regenerate with form helpers:\n  npm run wayfinder:refresh\n`);
        failed = true;
    }
}

if (failed) {
    process.exit(1);
}

console.log('Wayfinder form helpers OK (', mustContain.length, 'checks).');
