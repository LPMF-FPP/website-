#!/usr/bin/env node
/**
 * Non-Layout Guard for Overlay Files
 * Ensures overlay CSS files (pd-*.css) only contain visual properties
 */

import { readFile, writeFile, mkdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { readdir, stat } from 'fs/promises';
import postcss from 'postcss';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

// Dangerous layout properties
const LAYOUT_PROPERTIES = [
  // Positioning
  'position', 'top', 'right', 'bottom', 'left', 'z-index', 'inset',
  
  // Display & flow
  'display', 'visibility', 'float', 'clear',
  
  // Flexbox
  'flex', 'flex-direction', 'flex-wrap', 'flex-flow', 'justify-content',
  'align-items', 'align-content', 'align-self', 'order', 'flex-grow',
  'flex-shrink', 'flex-basis',
  
  // Grid
  'grid', 'grid-template', 'grid-template-rows', 'grid-template-columns',
  'grid-template-areas', 'grid-auto-rows', 'grid-auto-columns',
  'grid-auto-flow', 'grid-area', 'grid-row', 'grid-column',
  'justify-items', 'justify-self', 'place-items', 'place-self',
  'grid-gap', 'grid-row-gap', 'grid-column-gap',
  
  // Box model (dimensions & spacing)
  'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'margin-block', 'margin-inline', 'margin-block-start', 'margin-block-end',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'padding-block', 'padding-inline', 'padding-block-start', 'padding-block-end',
  
  // Gap
  'gap', 'column-gap', 'row-gap',
  
  // Transform & overflow
  'transform', 'transform-origin', 'overflow', 'overflow-x', 'overflow-y',
  'clip', 'clip-path',
  
  // Columns
  'columns', 'column-count', 'column-width'
];

// Safe visual-only properties (allowed)
const SAFE_PROPERTIES = [
  // Colors & backgrounds
  'color', 'background', 'background-color', 'background-image', 
  'background-position', 'background-size', 'background-repeat',
  
  // Borders
  'border', 'border-width', 'border-style', 'border-color', 'border-radius',
  'border-top', 'border-right', 'border-bottom', 'border-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
  'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
  'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
  'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
  
  // Shadows & effects
  'box-shadow', 'text-shadow', 'filter', 'backdrop-filter', 'opacity',
  
  // Typography
  'font', 'font-family', 'font-size', 'font-weight', 'font-style',
  'line-height', 'letter-spacing', 'word-spacing', 'text-align',
  'text-decoration', 'text-transform', 'text-indent',
  
  // Visual enhancements
  'outline', 'outline-width', 'outline-style', 'outline-color', 'outline-offset',
  'cursor',
  
  // Transitions & animations
  'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
  'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay'
];

async function findOverlayFiles() {
  const files = [];
  const stylesPath = join(ROOT, 'styles');
  
  try {
    const entries = await readdir(stylesPath);
    for (const entry of entries) {
      // Match pd-*.css or pd.*.css
      if (/^pd[-.].*\.css$/.test(entry)) {
        files.push(join(stylesPath, entry));
      }
    }
  } catch (error) {
    console.warn('⚠️  styles/ directory not found');
  }
  
  return files;
}

async function scanFile(filePath) {
  const content = await readFile(filePath, 'utf-8');
  const relativePath = filePath.replace(ROOT, '').replace(/\\/g, '/');
  const violations = [];
  
  try {
    const root = postcss.parse(content, { from: filePath });
    
    root.walkDecls((decl) => {
      const prop = decl.prop.toLowerCase();
      
      // Skip CSS variables
      if (prop.startsWith('--')) {
        return;
      }
      
      // Skip properties inside @keyframes (animations are visual-only)
      let parent = decl.parent;
      while (parent) {
        if (parent.type === 'atrule' && parent.name === 'keyframes') {
          return; // Safe: inside @keyframes
        }
        parent = parent.parent;
      }
      
      // Check if it's a layout property
      if (LAYOUT_PROPERTIES.includes(prop)) {
        violations.push({
          file: relativePath,
          line: decl.source?.start?.line || '?',
          column: decl.source?.start?.column || '?',
          property: prop,
          value: decl.value,
          selector: decl.parent.selector,
          severity: 'critical',
          message: `Layout property "${prop}" found in overlay (should be visual-only)`
        });
      }
    });
    
  } catch (error) {
    console.error(`   ⚠️  Parse error in ${relativePath}:`, error.message);
    return { file: relativePath, violations: [], error: error.message };
  }
  
  return { file: relativePath, violations, error: null };
}

function generateMarkdownReport(results) {
  let md = '# Non-Layout Guard Report\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  md += `**Purpose:** Ensure overlay files (pd-*.css) only contain visual properties\n\n`;
  
  const totalViolations = results.reduce((sum, r) => sum + r.violations.length, 0);
  
  md += '## Summary\n\n';
  md += `- **Files Scanned:** ${results.length}\n`;
  md += `- **Violations Found:** ${totalViolations}\n\n`;
  
  if (totalViolations === 0) {
    md += '✅ **All overlay files are safe!** No layout properties detected.\n\n';
  } else {
    md += '❌ **Layout violations detected!** These files modify layout and may cause shifts.\n\n';
  }
  
  // Per-file results
  results.forEach(result => {
    md += `## ${result.file}\n\n`;
    
    if (result.error) {
      md += `⚠️ **Parse Error:** ${result.error}\n\n`;
      return;
    }
    
    if (result.violations.length === 0) {
      md += '✅ **Safe** - No layout properties found\n\n';
      return;
    }
    
    md += `❌ **${result.violations.length} violation(s) found**\n\n`;
    
    md += '| Line | Property | Value | Selector |\n';
    md += '|------|----------|-------|----------|\n';
    
    result.violations.forEach(v => {
      const selector = v.selector.substring(0, 40);
      const value = v.value.substring(0, 30);
      md += `| ${v.line} | \`${v.property}\` | \`${value}\` | \`${selector}\` |\n`;
    });
    
    md += '\n### Why this matters:\n\n';
    md += 'Layout properties in overlay files can cause:\n';
    md += '- Elements "jumping" or shifting position\n';
    md += '- Broken responsive layouts\n';
    md += '- Conflicts with framework (Tailwind/Bootstrap)\n';
    md += '- Unpredictable behavior across different pages\n\n';
    
    md += '### How to fix:\n\n';
    md += '1. Remove the layout property\n';
    md += '2. Use only visual properties (color, background, border, shadow, etc.)\n';
    md += '3. If layout change is needed, modify the component/Blade file instead\n\n';
    md += '---\n\n';
  });
  
  // Safe properties reference
  md += '## ✅ Allowed Visual Properties\n\n';
  md += 'Overlay files should ONLY use these properties:\n\n';
  const categories = {
    'Colors': ['color', 'background-color', 'border-color'],
    'Shadows': ['box-shadow', 'text-shadow'],
    'Borders': ['border-radius', 'border-width', 'border-style'],
    'Effects': ['opacity', 'filter', 'backdrop-filter'],
    'Outlines': ['outline', 'outline-offset', 'outline-color'],
    'Transitions': ['transition', 'animation']
  };
  
  Object.entries(categories).forEach(([cat, props]) => {
    md += `**${cat}:** \`${props.join('`, `')}\`\n\n`;
  });
  
  // Dangerous properties reference
  md += '## ❌ Forbidden Layout Properties\n\n';
  md += 'These properties MUST NOT appear in overlay files:\n\n';
  md += '```\n';
  md += LAYOUT_PROPERTIES.join(', ');
  md += '\n```\n\n';
  
  md += '## Build Integration\n\n';
  md += 'Add this check to your CI/CD pipeline:\n\n';
  md += '```bash\n';
  md += 'npm run audit:guard\n';
  md += '```\n\n';
  md += 'This will fail the build if layout violations are detected.\n\n';
  
  return md;
}

async function main() {
  console.log('🛡️  Starting Non-Layout Guard...\n');
  
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  console.log('📂 Finding overlay files (pd-*.css, pd.*.css)...');
  const files = await findOverlayFiles();
  
  if (files.length === 0) {
    console.log('⚠️  No overlay files found (searched for pd-*.css in styles/)');
    console.log('   This guard checks theme overlay files to ensure they don\'t modify layout.\n');
    return;
  }
  
  console.log(`   Found ${files.length} overlay files\n`);
  
  const results = [];
  for (const file of files) {
    const relativePath = file.replace(ROOT, '').replace(/\\/g, '/');
    console.log(`   Scanning: ${relativePath}`);
    
    const result = await scanFile(file);
    results.push(result);
    
    if (result.violations.length > 0) {
      console.log(`      ❌ ${result.violations.length} violation(s) found`);
    } else if (!result.error) {
      console.log(`      ✅ Safe`);
    }
  }
  
  // Generate report
  const markdown = generateMarkdownReport(results);
  const mdPath = join(ROOT, 'report/nonlayout-violations.md');
  await writeFile(mdPath, markdown);
  console.log(`\n📄 Report saved: report/nonlayout-violations.md`);
  
  // Save JSON
  const jsonPath = join(ROOT, 'report/nonlayout-violations.json');
  await writeFile(jsonPath, JSON.stringify(results, null, 2));
  console.log(`💾 JSON data saved: report/nonlayout-violations.json`);
  
  // Summary
  const totalViolations = results.reduce((sum, r) => sum + r.violations.length, 0);
  
  console.log('\n' + '='.repeat(60));
  console.log('NON-LAYOUT GUARD SUMMARY');
  console.log('='.repeat(60));
  console.log(`Files Scanned: ${results.length}`);
  console.log(`Total Violations: ${totalViolations}`);
  console.log('='.repeat(60) + '\n');
  
  if (totalViolations > 0) {
    console.log('❌ GUARD FAILED: Layout violations detected!');
    console.log('   Overlay files should only modify visual properties.');
    console.log('   Review report/nonlayout-violations.md for details.\n');
    process.exit(1);
  } else {
    console.log('✅ GUARD PASSED: All overlay files are safe!\n');
  }
}

main().catch(error => {
  console.error('❌ Guard failed:', error);
  process.exit(1);
});
