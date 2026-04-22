#!/usr/bin/env node
/**
 * Gera 1 PDF por arquivo .md em docs/dre/ e docs/budgets/.
 *
 * Suporta blocos ```mermaid (renderizados como diagrama).
 * Output ao lado do .md (mesmo nome, extensão .pdf).
 *
 * Uso:
 *   npm run docs:pdf            # gera todos
 *   npm run docs:pdf -- dre     # gera só docs/dre/
 *   npm run docs:pdf -- budgets # gera só docs/budgets/
 */

import { mdToPdf } from 'md-to-pdf';
import { readdirSync, statSync } from 'node:fs';
import { join, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));
const projectRoot = join(__dirname, '..');

// -----------------------------------------------------------------------------
// Marked extension: ```mermaid blocks → <div class="mermaid">
// -----------------------------------------------------------------------------

const mermaidExtension = {
    name: 'mermaid',
    level: 'block',
    start(src) {
        return src.match(/^```mermaid/)?.index;
    },
    tokenizer(src) {
        const match = /^```mermaid\n([\s\S]+?)\n```/.exec(src);
        if (match) {
            return {
                type: 'mermaid',
                raw: match[0],
                text: match[1].trim(),
            };
        }
    },
    renderer(token) {
        return `<div class="mermaid">${token.text}</div>\n`;
    },
};

// -----------------------------------------------------------------------------
// CSS de impressão A4 + estilo agradável para markdown
// -----------------------------------------------------------------------------

const css = `
    @page { size: A4; margin: 18mm 14mm 18mm 14mm; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 10.5pt;
        line-height: 1.5;
        color: #222;
    }
    h1 { font-size: 22pt; border-bottom: 2px solid #333; padding-bottom: 6px; margin-top: 0; }
    h2 { font-size: 16pt; border-bottom: 1px solid #999; padding-bottom: 4px; margin-top: 24px; }
    h3 { font-size: 13pt; margin-top: 18px; }
    h4 { font-size: 11pt; margin-top: 14px; }
    p, li { font-size: 10.5pt; }
    code {
        font-family: 'Consolas', 'Courier New', monospace;
        background: #f4f4f4;
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 9.5pt;
    }
    pre {
        background: #f6f8fa;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
        font-size: 9pt;
        page-break-inside: avoid;
    }
    pre code { background: transparent; padding: 0; }
    table {
        border-collapse: collapse;
        width: 100%;
        margin: 10px 0;
        font-size: 9.5pt;
        page-break-inside: avoid;
    }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
    th { background: #eee; font-weight: 600; }
    blockquote {
        border-left: 4px solid #4a90e2;
        background: #eef5fc;
        margin: 10px 0;
        padding: 8px 12px;
        color: #333;
    }
    blockquote p { margin: 4px 0; }
    a { color: #1a73e8; text-decoration: none; }
    .mermaid {
        text-align: center;
        margin: 14px 0;
        page-break-inside: avoid;
    }
    hr { border: none; border-top: 1px solid #ddd; margin: 18px 0; }
`;

// -----------------------------------------------------------------------------
// Config md-to-pdf
// -----------------------------------------------------------------------------

const config = {
    marked_extensions: [{ extensions: [mermaidExtension] }],
    css,
    body_class: ['markdown-body'],
    script: [
        { url: 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js' },
        {
            content: `
                mermaid.initialize({
                    startOnLoad: true,
                    theme: 'default',
                    themeVariables: { fontSize: '14px' },
                    flowchart: { useMaxWidth: true, htmlLabels: true },
                });
            `,
        },
    ],
    launch_options: { args: ['--no-sandbox'] },
    pdf_options: {
        format: 'A4',
        printBackground: true,
        // Espera Mermaid renderizar antes de imprimir
        timeout: 30_000,
    },
    // Necessário para Mermaid: dá tempo do JS renderizar antes do PDF imprimir
    wait_for: 2000,
};

// -----------------------------------------------------------------------------
// Discovery dos .md
// -----------------------------------------------------------------------------

function listMarkdownIn(dir) {
    try {
        return readdirSync(dir)
            .filter((f) => f.endsWith('.md'))
            .map((f) => join(dir, f));
    } catch {
        return [];
    }
}

const targetArg = process.argv[2]?.toLowerCase();
const dirs = [];

if (!targetArg || targetArg === 'dre') dirs.push(join(projectRoot, 'docs', 'dre'));
if (!targetArg || targetArg === 'budgets') dirs.push(join(projectRoot, 'docs', 'budgets'));

const files = dirs.flatMap(listMarkdownIn);

if (files.length === 0) {
    console.error('Nenhum arquivo .md encontrado em', dirs.join(', '));
    process.exit(1);
}

// -----------------------------------------------------------------------------
// Geração
// -----------------------------------------------------------------------------

console.log(`Gerando ${files.length} PDFs...\n`);

let okCount = 0;
let failCount = 0;

for (const file of files) {
    const outName = basename(file, '.md') + '.pdf';
    const outPath = file.replace(/\.md$/, '.pdf');
    process.stdout.write(`  → ${file.replace(projectRoot + '\\', '').replace(projectRoot + '/', '')} ... `);

    try {
        await mdToPdf({ path: file }, { ...config, dest: outPath });
        console.log('OK');
        okCount++;
    } catch (err) {
        console.log('FAIL');
        console.error(`    ${err.message}`);
        failCount++;
    }
}

console.log(`\n${okCount} gerados, ${failCount} falhas.`);
process.exit(failCount > 0 ? 1 : 0);
