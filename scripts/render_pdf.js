#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const puppeteerModulePath = process.env.HGA_PUPPETEER_MODULE_PATH;
const puppeteer = puppeteerModulePath ? require(puppeteerModulePath) : require('puppeteer');

async function main() {
  const [inputPath, outputPath] = process.argv.slice(2);
  if (!inputPath || !outputPath) {
    console.error('Usage: render_pdf.js <input.html> <output.pdf>');
    process.exit(1);
  }

  const resolvedInput = path.resolve(inputPath);
  const resolvedOutput = path.resolve(outputPath);

  if (!fs.existsSync(resolvedInput)) {
    console.error(`Input HTML not found: ${resolvedInput}`);
    process.exit(1);
  }

  const footerText = process.env.HGA_PDF_FOOTER_TEXT || '';
  const footerTemplate = `
    <div style="font-size:7pt; width:100%; text-align:center; border-top:1px solid #ddd; padding-top:4px; color:#666;">
      ${escapeHtml(footerText)} - Seite <span class="pageNumber"></span>
    </div>
  `;

  const browser = await puppeteer.launch({
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-crashpad',
      '--no-zygote',
      '--disable-features=Crashpad',
      '--crashpad-handler-pid=0',
      '--user-data-dir=/tmp/puppeteer',
      '--disable-gpu',
    ],
    env: {
      ...process.env,
      CHROME_HEADLESS: '1',
      BREAKPAD_DUMP_LOCATION: '/tmp',
    },
  });

  try {
    const page = await browser.newPage();
    const fileUrl = pathToFileURL(resolvedInput).toString();
    await page.goto(fileUrl, { waitUntil: 'networkidle0' });
    await page.pdf({
      path: resolvedOutput,
      format: 'A4',
      displayHeaderFooter: true,
      headerTemplate: '<div></div>',
      footerTemplate,
      margin: {
        top: '80px',
        right: '25px',
        bottom: '40px',
        left: '25px',
      },
      printBackground: true,
      preferCSSPageSize: true,
    });
  } finally {
    await browser.close();
  }
}

function escapeHtml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
