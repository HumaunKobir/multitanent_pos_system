#!/usr/bin/env node
/**
 * After `php artisan wayfinder:generate --with-form`, transpile Wayfinder `.ts` outputs to `.js` and remove `.ts`.
 * Always use `--with-form` so `.form()` exists for Inertia <Form />. Prefer: npm run wayfinder:refresh
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import ts from 'typescript';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsRoot = path.join(__dirname, '..', 'resources', 'js');

const ROOTS = ['actions', 'routes', 'wayfinder'].map((d) => path.join(jsRoot, d));

function walk(dir, acc = []) {
    if (!fs.existsSync(dir)) {
        return acc;
    }

    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, ent.name);
        if (ent.isDirectory()) {
            walk(full, acc);
        } else if (ent.name.endsWith('.ts') && !ent.name.endsWith('.d.ts')) {
            acc.push(full);
        }
    }

    return acc;
}

function outPath(file) {
    return file.slice(0, -3) + '.js';
}

const files = ROOTS.flatMap((r) => walk(r));

for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');

    const result = ts.transpileModule(content, {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ESNext,
            jsx: ts.JsxEmit.Preserve,
            esModuleInterop: true,
            isolatedModules: true,
            skipLibCheck: true,
        },
        fileName: file,
    });

    const diag = result.diagnostics?.filter((d) => d.category === ts.DiagnosticCategory.Error) ?? [];
    if (diag.length > 0) {
        console.error('Failed:', file, diag.map((d) => d.messageText));

        continue;
    }

    const target = outPath(file);
    fs.writeFileSync(target, result.outputText, 'utf8');
    fs.unlinkSync(file);
    console.log(file, '->', target);
}

console.log('Wayfinder JS:', files.length, 'files.');
