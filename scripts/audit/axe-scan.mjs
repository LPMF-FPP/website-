#!/usr/bin/env node
/**
 * Accessibility Audit using axe-core
 * Scans specified URLs for WCAG violations
 */

import { readFile, writeFile, mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import puppeteer from 'puppeteer';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const axeCore = require('axe-core');

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

// Default URLs to scan
const DEFAULT_URLS = [
  'http://127.0.0.1:8000',
  'http://127.0.0.1:8000/dashboard'
];

// Get URLs from env or use defaults
const URLS = process.env.AUDIT_URLS
  ? process.env.AUDIT_URLS.split(',').map(u => u.trim())
  : DEFAULT_URLS;

// Severity colors for console
const SEVERITY_EMOJI = {
  critical: '🔴',
  serious: '🟠',
  moderate: '🟡',
  minor: '🔵'
};

async function scanPage(page, url) {
  console.log(`\n📄 Scanning: ${url}`);
  
  try {
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
    
    // Inject axe-core
    await page.evaluate(axeCore.source);
    
    // Run axe
    const results = await page.evaluate(() => {
      return window.axe.run({
        resultTypes: ['violations', 'incomplete']
      });
    });
    
    console.log(`   ✅ Scan complete: ${results.violations.length} violations found`);
    
    return {
      url,
      timestamp: new Date().toISOString(),
      violations: results.violations,
      incomplete: results.incomplete,
      passes: results.passes.length
    };
    
  } catch (error) {
    console.error(`   ❌ Failed to scan ${url}:`, error.message);
    return {
      url,
      timestamp: new Date().toISOString(),
      error: error.message,
      violations: [],
      incomplete: [],
      passes: 0
    };
  }
}

function generateMarkdownReport(results) {
  let md = '# Accessibility Audit Report\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  md += `**URLs Scanned:** ${results.length}\n\n`;
  
  // Summary
  const totalViolations = results.reduce((sum, r) => sum + r.violations.length, 0);
  const totalIncomplete = results.reduce((sum, r) => sum + r.incomplete.length, 0);
  
  md += '## Summary\n\n';
  md += `- 🔴 **Total Violations:** ${totalViolations}\n`;
  md += `- 🟡 **Incomplete Tests:** ${totalIncomplete}\n\n`;
  
  // Severity breakdown
  const severityCounts = { critical: 0, serious: 0, moderate: 0, minor: 0 };
  results.forEach(r => {
    r.violations.forEach(v => {
      severityCounts[v.impact] = (severityCounts[v.impact] || 0) + 1;
    });
  });
  
  md += '### Violations by Severity\n\n';
  Object.entries(severityCounts).forEach(([severity, count]) => {
    if (count > 0) {
      md += `- ${SEVERITY_EMOJI[severity]} **${severity.toUpperCase()}:** ${count}\n`;
    }
  });
  md += '\n';
  
  // Detailed results per page
  results.forEach(result => {
    md += `## ${result.url}\n\n`;
    
    if (result.error) {
      md += `❌ **Error:** ${result.error}\n\n`;
      return;
    }
    
    md += `- **Violations:** ${result.violations.length}\n`;
    md += `- **Incomplete:** ${result.incomplete.length}\n`;
    md += `- **Passes:** ${result.passes}\n\n`;
    
    if (result.violations.length > 0) {
      md += '### Violations\n\n';
      
      result.violations.forEach((violation, idx) => {
        md += `#### ${idx + 1}. ${SEVERITY_EMOJI[violation.impact]} ${violation.help}\n\n`;
        md += `**Impact:** ${violation.impact.toUpperCase()}\n\n`;
        md += `**Description:** ${violation.description}\n\n`;
        md += `**WCAG:** ${violation.tags.filter(t => t.startsWith('wcag')).join(', ')}\n\n`;
        md += `**Affected Elements:** ${violation.nodes.length}\n\n`;
        
        // Show first 3 affected elements
        violation.nodes.slice(0, 3).forEach((node, nIdx) => {
          md += `${nIdx + 1}. \`${node.html.substring(0, 100)}${node.html.length > 100 ? '...' : ''}\`\n`;
          md += `   - Target: \`${node.target.join(' > ')}\`\n`;
          if (node.failureSummary) {
            md += `   - Issue: ${node.failureSummary.split('\n')[0]}\n`;
          }
        });
        
        if (violation.nodes.length > 3) {
          md += `\n...and ${violation.nodes.length - 3} more\n`;
        }
        
        md += `\n**How to fix:**\n`;
        md += `${violation.helpUrl}\n\n`;
        md += '---\n\n';
      });
    }
    
    if (result.incomplete.length > 0) {
      md += '### Incomplete Tests (needs manual review)\n\n';
      result.incomplete.forEach((item, idx) => {
        md += `${idx + 1}. **${item.help}** - ${item.nodes.length} elements\n`;
      });
      md += '\n';
    }
  });
  
  // Recommendations
  md += '## Recommendations\n\n';
  md += '1. **Critical & Serious Issues**: Fix immediately - these prevent users from accessing content\n';
  md += '2. **Moderate Issues**: Plan fixes in next sprint - impacts usability\n';
  md += '3. **Minor Issues**: Address during routine maintenance\n';
  md += '4. **Incomplete Tests**: Manually verify these elements\n\n';
  
  md += '## Tools Used\n\n';
  md += '- **axe-core**: Industry-standard accessibility testing\n';
  md += '- **Puppeteer**: Headless browser automation\n';
  md += '- **WCAG Standards**: 2.1 Level AA\n\n';
  
  return md;
}

async function main() {
  console.log('🔍 Starting Accessibility Audit...\n');
  console.log(`URLs to scan: ${URLS.join(', ')}\n`);
  
  // Ensure report directory exists
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  // Launch browser
  console.log('🚀 Launching browser...');
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 720 });
    
    // Scan all URLs
    const results = [];
    for (const url of URLS) {
      const result = await scanPage(page, url);
      results.push(result);
    }
    
    // Save JSON
    const jsonPath = join(ROOT, 'report/axe.json');
    await writeFile(jsonPath, JSON.stringify(results, null, 2));
    console.log(`\n💾 JSON report saved: report/axe.json`);
    
    // Generate Markdown
    const markdown = generateMarkdownReport(results);
    const mdPath = join(ROOT, 'report/axe.md');
    await writeFile(mdPath, markdown);
    console.log(`📄 Markdown report saved: report/axe.md`);
    
    // Summary
    const totalViolations = results.reduce((sum, r) => sum + r.violations.length, 0);
    const criticalCount = results.reduce((sum, r) => {
      return sum + r.violations.filter(v => v.impact === 'critical').length;
    }, 0);
    
    console.log('\n' + '='.repeat(60));
    console.log('ACCESSIBILITY AUDIT SUMMARY');
    console.log('='.repeat(60));
    console.log(`Total Violations: ${totalViolations}`);
    console.log(`Critical Issues: ${criticalCount}`);
    console.log('='.repeat(60) + '\n');
    
    if (criticalCount > 0) {
      console.log('⚠️  Critical accessibility issues found!');
      console.log('   Review report/axe.md for details.\n');
    }
    
  } finally {
    await browser.close();
  }
}

main().catch(error => {
  console.error('❌ Audit failed:', error);
  process.exit(1);
});
