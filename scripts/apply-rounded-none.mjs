#!/usr/bin/env node
/**
 * Replace Tailwind rounded-* utilities with rounded-none (sharp UI policy).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsRoot = path.join(__dirname, '..', 'resources', 'js');

const SKIP_FIRST = new Set(['actions', 'routes', 'wayfinder']);

const ROUNDED_RE = /\brounded-(?:xs|sm|md|lg|xl|2xl|3xl|4xl|full|\[[^\]]+\])\b/g;

function walk(dir, base, acc = []) {
    const rel = path.relative(base, dir);
    const first = rel.split(path.sep)[0];
    if (rel !== '' && SKIP_FIRST.has(first)) {
        return acc;
    }

    for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, ent.name);
        if (ent.isDirectory()) {
            walk(full, base, acc);
        } else if (/\.(jsx|js)$/.test(ent.name)) {
            acc.push(full);
        }
    }

    return acc;
}

for (const file of walk(jsRoot, jsRoot)) {
    let content = fs.readFileSync(file, 'utf8');
    const next = content.replace(ROUNDED_RE, 'rounded-none');
    if (next !== content) {
        fs.writeFileSync(file, next, 'utf8');
        console.log('updated', path.relative(jsRoot, file));
    }
}
