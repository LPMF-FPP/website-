#!/usr/bin/env node
/**
 * Z-Index Topology Analyzer
 * Maps stacking contexts and detects potential z-index conflicts
 */

import { readFile, writeFile, mkdir, readdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import postcss from 'postcss';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

async function findCSSFiles() {
  const files = [];
  const paths = ['resources/css', 'public/css', 'styles'];
  
  for (const path of paths) {
    const fullPath = join(ROOT, path);
    try {
      const entries = await readdir(fullPath);
      for (const entry of entries) {
        if (entry.endsWith('.css') && !entry.includes('.min.')) {
          files.push(join(fullPath, entry));
        }
      }
    } catch (error) {
      // Path doesn't exist, skip
    }
  }
  
  return files;
}

async function analyzeFile(filePath) {
  const content = await readFile(filePath, 'utf-8');
  const relativePath = filePath.replace(ROOT, '').replace(/\\/g, '/');
  const zIndexes = [];
  
  try {
    const root = postcss.parse(content);
    
    root.walkDecls('z-index', (decl) => {
      const value = parseInt(decl.value, 10);
      
      if (!isNaN(value)) {
        zIndexes.push({
          selector: decl.parent.selector,
          value,
          file: relativePath,
          line: decl.source?.start?.line || '?'
        });
      }
    });
    
  } catch (error) {
    console.error(`   ⚠️  Parse error in ${relativePath}:`, error.message);
  }
  
  return zIndexes;
}

function detectConflicts(allZIndexes) {
  const conflicts = [];
  const ranges = [
    { name: 'Base', min: 0, max: 9 },
    { name: 'Content', min: 10, max: 99 },
    { name: 'Dropdowns', min: 100, max: 999 },
    { name: 'Modals', min: 1000, max: 9999 },
    { name: 'Tooltips/Toasts', min: 10000, max: 99999 }
  ];
  
  // Group by range
  const grouped = {};
  ranges.forEach(r => { grouped[r.name] = []; });
  
  allZIndexes.forEach(z => {
    let assigned = false;
    for (const range of ranges) {
      if (z.value >= range.min && z.value <= range.max) {
        grouped[range.name].push(z);
        assigned = true;
        break;
      }
    }
    if (!assigned) {
      if (!grouped['Extreme']) grouped['Extreme'] = [];
      grouped['Extreme'].push(z);
    }
  });
  
  // Detect potential conflicts (similar z-index values in same range)
  Object.entries(grouped).forEach(([rangeName, items]) => {
    if (items.length < 2) return;
    
    const sorted = items.sort((a, b) => a.value - b.value);
    for (let i = 0; i < sorted.length - 1; i++) {
      const diff = sorted[i + 1].value - sorted[i].value;
      
      // If z-indexes are very close (within 5), might conflict
      if (diff <= 5 && diff > 0) {
        conflicts.push({
          range: rangeName,
          item1: sorted[i],
          item2: sorted[i + 1],
          diff,
          message: `Close z-index values (${sorted[i].value} vs ${sorted[i + 1].value}) may cause stacking issues`
        });
      }
    }
  });
  
  return { grouped, conflicts };
}

function generateMarkdownReport(allZIndexes, grouped, conflicts) {
  let md = '# Z-Index Topology Map\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  
  md += '## Summary\n\n';
  md += `- **Total z-index declarations:** ${allZIndexes.length}\n`;
  md += `- **Potential conflicts:** ${conflicts.length}\n\n`;
  
  // Range distribution
  md += '## Distribution by Range\n\n';
  Object.entries(grouped).forEach(([range, items]) => {
    md += `### ${range} (${items.length} items)\n\n`;
    
    if (items.length === 0) {
      md += '_None_\n\n';
      return;
    }
    
    const sorted = items.sort((a, b) => b.value - a.value);
    md += '| z-index | Selector | File |\n';
    md += '|---------|----------|------|\n';
    
    sorted.forEach(item => {
      const sel = item.selector.substring(0, 40);
      const file = item.file.split('/').pop();
      md += `| **${item.value}** | \`${sel}\` | ${file}:${item.line} |\n`;
    });
    
    md += '\n';
  });
  
  // Conflicts
  if (conflicts.length > 0) {
    md += '## ⚠️ Potential Conflicts\n\n';
    conflicts.forEach((c, idx) => {
      md += `### ${idx + 1}. ${c.range} Range\n\n`;
      md += `**Difference:** Only ${c.diff} apart\n\n`;
      md += `**Item 1:** \`${c.item1.selector}\` (z-index: ${c.item1.value})\n`;
      md += `  - File: ${c.item1.file}:${c.item1.line}\n\n`;
      md += `**Item 2:** \`${c.item2.selector}\` (z-index: ${c.item2.value})\n`;
      md += `  - File: ${c.item2.file}:${c.item2.line}\n\n`;
      md += `**Impact:** ${c.message}\n\n`;
      md += '---\n\n';
    });
  }
  
  // Recommendations
  md += '## Recommended Z-Index Scale\n\n';
  md += 'Use a systematic scale to avoid conflicts:\n\n';
  md += '```css\n';
  md += '/* Base layer */\n';
  md += '--z-base: 0;\n';
  md += '--z-dropdown: 100;\n';
  md += '--z-sticky: 200;\n';
  md += '--z-fixed: 300;\n';
  md += '--z-modal-backdrop: 400;\n';
  md += '--z-modal: 500;\n';
  md += '--z-popover: 600;\n';
  md += '--z-tooltip: 700;\n';
  md += '--z-toast: 800;\n';
  md += '```\n\n';
  
  md += '## Tips\n\n';
  md += '1. **Use CSS variables** for z-index values (centralized control)\n';
  md += '2. **Leave gaps** between ranges (e.g., 100, 200, 300 instead of 1, 2, 3)\n';
  md += '3. **Document purpose** - comment why each z-index is needed\n';
  md += '4. **Avoid extreme values** - rarely need > 1000\n';
  md += '5. **Test stacking** - ensure modals appear above dropdowns, tooltips above modals\n\n';
  
  return md;
}

async function main() {
  console.log('🔢 Starting Z-Index Analysis...\n');
  
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  console.log('📂 Finding CSS files...');
  const files = await findCSSFiles();
  console.log(`   Found ${files.length} files\n`);
  
  if (files.length === 0) {
    console.log('⚠️  No CSS files found');
    return;
  }
  
  const allZIndexes = [];
  for (const file of files) {
    const relativePath = file.replace(ROOT, '').replace(/\\/g, '/');
    console.log(`   Analyzing: ${relativePath}`);
    const zIndexes = await analyzeFile(file);
    allZIndexes.push(...zIndexes);
  }
  
  console.log(`\n📊 Found ${allZIndexes.length} z-index declarations`);
  
  const { grouped, conflicts } = detectConflicts(allZIndexes);
  
  // Generate report
  const markdown = generateMarkdownReport(allZIndexes, grouped, conflicts);
  const mdPath = join(ROOT, 'report/zindex-map.md');
  await writeFile(mdPath, markdown);
  console.log(`📄 Report saved: report/zindex-map.md`);
  
  // Save JSON
  const jsonPath = join(ROOT, 'report/zindex-map.json');
  await writeFile(jsonPath, JSON.stringify({ allZIndexes, grouped, conflicts }, null, 2));
  console.log(`💾 JSON data saved: report/zindex-map.json`);
  
  console.log('\n' + '='.repeat(60));
  console.log('Z-INDEX ANALYSIS SUMMARY');
  console.log('='.repeat(60));
  console.log(`Total z-indexes: ${allZIndexes.length}`);
  console.log(`Potential conflicts: ${conflicts.length}`);
  console.log('='.repeat(60) + '\n');
  
  if (conflicts.length > 0) {
    console.log('⚠️  Stacking conflicts detected! Review report/zindex-map.md\n');
  }
}

main().catch(error => {
  console.error('❌ Analysis failed:', error);
  process.exit(1);
});
