#!/usr/bin/env node
/**
 * One-off migration: transpile hand-written resources/js TS/TSX to JS/JSX.
 * Skips resources/js/actions, routes, wayfinder (Wayfinder uses separate script).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import ts from 'typescript';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsRoot = path.join(__dirname, '..', 'resources', 'js');

const SKIP_DIRS = new Set(['actions', 'routes', 'wayfinder']);

function walk(dir, base = dir, acc = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    const rel = path.relative(base, dir);
    const firstSeg = rel.split(path.sep)[0];

    if (rel !== '' && SKIP_DIRS.has(firstSeg)) {
        return acc;
    }

    for (const ent of entries) {
        const full = path.join(dir, ent.name);
        if (ent.isDirectory()) {
            walk(full, base, acc);
        } else if (/\.(tsx|ts)$/.test(ent.name) && !ent.name.endsWith('.d.ts')) {
            acc.push(full);
        }
    }

    return acc;
}

function outPath(file) {
    if (file.endsWith('.tsx')) {
        return file.slice(0, -4) + '.jsx';
    }

    return file.slice(0, -3) + '.js';
}

const files = walk(jsRoot);
for (const file of files) {
    const content = fs.readFileSync(file, 'utf8');
    const isTsx = file.endsWith('.tsx');

    const result = ts.transpileModule(content, {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ESNext,
            jsx: isTsx ? ts.JsxEmit.ReactJSX : ts.JsxEmit.Preserve,
            esModuleInterop: true,
            isolatedModules: true,
            skipLibCheck: true,
            verbatimModuleSyntax: false,
        },
        fileName: file,
        reportDiagnostics: true,
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

console.log('Done. Migrated', files.length, 'files.');
