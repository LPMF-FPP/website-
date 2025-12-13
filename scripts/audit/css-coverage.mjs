#!/usr/bin/env node
/**
 * CSS Coverage Analysis
 * Detects unused CSS rules using Puppeteer Coverage API
 */

import { writeFile, mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import puppeteer from 'puppeteer';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

const DEFAULT_URLS = [
  'http://127.0.0.1:8000',
  'http://127.0.0.1:8000/dashboard'
];

const URLS = process.env.AUDIT_URLS
  ? process.env.AUDIT_URLS.split(',').map(u => u.trim())
  : DEFAULT_URLS;

function formatBytes(bytes) {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

async function analyzeCoverage(page, url) {
  console.log(`\n📄 Analyzing CSS coverage: ${url}`);
  
  try {
    // Start CSS coverage
    await page.coverage.startCSSCoverage();
    
    await page.goto(url, { 
      waitUntil: 'networkidle2', 
      timeout: 30000 
    });
    
    // Stop coverage and get results
    const coverage = await page.coverage.stopCSSCoverage();
    
    // Process coverage data
    const cssFiles = coverage.map(entry => {
      const total = entry.text.length;
      const used = entry.ranges.reduce((acc, range) => {
        return acc + (range.end - range.start);
      }, 0);
      const unused = total - used;
      const usedPercent = total > 0 ? (used / total) * 100 : 0;
      
      return {
        url: entry.url,
        total,
        used,
        unused,
        usedPercent: Math.round(usedPercent * 100) / 100,
        unusedPercent: Math.round((100 - usedPercent) * 100) / 100
      };
    });
    
    // Filter out inline styles and data URIs
    const externalCss = cssFiles.filter(f => 
      f.url.startsWith('http') && 
      !f.url.includes('data:') &&
      f.total > 100 // Ignore tiny files
    );
    
    console.log(`   ✅ Analyzed ${externalCss.length} CSS files`);
    
    return {
      url,
      files: externalCss,
      summary: {
        totalFiles: externalCss.length,
        totalBytes: externalCss.reduce((sum, f) => sum + f.total, 0),
        usedBytes: externalCss.reduce((sum, f) => sum + f.used, 0),
        unusedBytes: externalCss.reduce((sum, f) => sum + f.unused, 0)
      }
    };
    
  } catch (error) {
    console.error(`   ❌ Failed to analyze ${url}:`, error.message);
    return {
      url,
      error: error.message,
      files: [],
      summary: { totalFiles: 0, totalBytes: 0, usedBytes: 0, unusedBytes: 0 }
    };
  }
}

function generateMarkdownReport(results) {
  let md = '# CSS Coverage Report\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  
  // Overall summary
  const totalBytes = results.reduce((sum, r) => sum + r.summary.totalBytes, 0);
  const unusedBytes = results.reduce((sum, r) => sum + r.summary.unusedBytes, 0);
  const unusedPercent = totalBytes > 0 ? (unusedBytes / totalBytes) * 100 : 0;
  
  md += '## Overall Summary\n\n';
  md += `- **Total CSS Size:** ${formatBytes(totalBytes)}\n`;
  md += `- **Used CSS:** ${formatBytes(totalBytes - unusedBytes)}\n`;
  md += `- **Unused CSS:** ${formatBytes(unusedBytes)} (${unusedPercent.toFixed(1)}%)\n\n`;
  
  if (unusedPercent > 50) {
    md += '⚠️ **Warning:** More than 50% of CSS is unused! Consider purging.\n\n';
  } else if (unusedPercent > 30) {
    md += '⚡ **Suggestion:** Significant unused CSS detected. Review candidates for removal.\n\n';
  } else {
    md += '✅ CSS usage is reasonable.\n\n';
  }
  
  // Per-page analysis
  results.forEach(result => {
    md += `## ${result.url}\n\n`;
    
    if (result.error) {
      md += `❌ **Error:** ${result.error}\n\n`;
      return;
    }
    
    if (result.files.length === 0) {
      md += 'No external CSS files detected.\n\n';
      return;
    }
    
    // Sort by unused bytes (largest first)
    const sorted = [...result.files].sort((a, b) => b.unused - a.unused);
    
    md += '| File | Total | Used | Unused | % Unused |\n';
    md += '|------|-------|------|--------|----------|\n';
    
    sorted.forEach(file => {
      const filename = file.url.split('/').pop() || file.url;
      md += `| ${filename} | ${formatBytes(file.total)} | ${formatBytes(file.used)} | ${formatBytes(file.unused)} | ${file.unusedPercent}% |\n`;
    });
    
    md += '\n';
    
    // Candidates for purging
    const purgeCandidates = sorted.filter(f => f.unusedPercent > 50 && f.total > 5000);
    if (purgeCandidates.length > 0) {
      md += '### 🎯 Purge Candidates (>50% unused, >5KB)\n\n';
      purgeCandidates.forEach(file => {
        const filename = file.url.split('/').pop() || file.url;
        md += `- **${filename}**: ${file.unusedPercent}% unused (${formatBytes(file.unused)} waste)\n`;
      });
      md += '\n';
    }
  });
  
  // Recommendations
  md += '## Recommendations\n\n';
  md += '### Short-term:\n';
  md += '1. **PurgeCSS**: Integrate with Tailwind/Vite to remove unused utility classes\n';
  md += '2. **Code Splitting**: Load page-specific CSS only when needed\n';
  md += '3. **Defer Non-Critical**: Use `media="print"` trick for non-critical CSS\n\n';
  
  md += '### Long-term:\n';
  md += '1. **Component-scoped CSS**: Use CSS Modules or Scoped Styles\n';
  md += '2. **Tree-shaking**: Ensure build tool eliminates unused imports\n';
  md += '3. **Audit regularly**: Run this report before major releases\n\n';
  
  md += '## Notes\n\n';
  md += '- Coverage is measured on initial page load only\n';
  md += '- Interactive states (hover, modals) may show as "unused"\n';
  md += '- Verify manually before removing CSS\n\n';
  
  return md;
}

async function main() {
  console.log('🔍 Starting CSS Coverage Analysis...\n');
  console.log(`URLs to analyze: ${URLS.join(', ')}\n`);
  
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  console.log('🚀 Launching browser...');
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 720 });
    
    const results = [];
    for (const url of URLS) {
      const result = await analyzeCoverage(page, url);
      results.push(result);
    }
    
    // Save JSON
    const jsonPath = join(ROOT, 'report/coverage-css.json');
    await writeFile(jsonPath, JSON.stringify(results, null, 2));
    console.log(`\n💾 JSON report saved: report/coverage-css.json`);
    
    // Generate Markdown
    const markdown = generateMarkdownReport(results);
    const mdPath = join(ROOT, 'report/coverage-css.md');
    await writeFile(mdPath, markdown);
    console.log(`📄 Markdown report saved: report/coverage-css.md`);
    
    // Summary
    const totalUnused = results.reduce((sum, r) => sum + r.summary.unusedBytes, 0);
    const totalSize = results.reduce((sum, r) => sum + r.summary.totalBytes, 0);
    const percent = totalSize > 0 ? (totalUnused / totalSize) * 100 : 0;
    
    console.log('\n' + '='.repeat(60));
    console.log('CSS COVERAGE SUMMARY');
    console.log('='.repeat(60));
    console.log(`Total CSS: ${formatBytes(totalSize)}`);
    console.log(`Unused CSS: ${formatBytes(totalUnused)} (${percent.toFixed(1)}%)`);
    console.log('='.repeat(60) + '\n');
    
    if (percent > 50) {
      console.log('⚠️  High CSS waste detected! Consider PurgeCSS.\n');
    }
    
  } finally {
    await browser.close();
  }
}

main().catch(error => {
  console.error('❌ Analysis failed:', error);
  process.exit(1);
});
