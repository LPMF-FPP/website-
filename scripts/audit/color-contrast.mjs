#!/usr/bin/env node
/**
 * Color Contrast & Theme Parity Checker
 * Validates WCAG AA contrast ratios and dark/light theme consistency
 */

import { readFile, writeFile, mkdir } from 'fs/promises';
import { readdir } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import postcss from 'postcss';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

// WCAG 2.1 contrast ratio requirements
const WCAG_AA_NORMAL = 4.5;
const WCAG_AA_LARGE = 3.0;

// Relative luminance calculation
function getLuminance(r, g, b) {
  const [rs, gs, bs] = [r, g, b].map(c => {
    c = c / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

// Contrast ratio calculation
function getContrastRatio(rgb1, rgb2) {
  const lum1 = getLuminance(rgb1[0], rgb1[1], rgb1[2]);
  const lum2 = getLuminance(rgb2[0], rgb2[1], rgb2[2]);
  const lighter = Math.max(lum1, lum2);
  const darker = Math.min(lum1, lum2);
  return (lighter + 0.05) / (darker + 0.05);
}

// Parse hex color to RGB
function hexToRgb(hex) {
  hex = hex.replace('#', '');
  if (hex.length === 3) {
    hex = hex.split('').map(c => c + c).join('');
  }
  return [
    parseInt(hex.substring(0, 2), 16),
    parseInt(hex.substring(2, 4), 16),
    parseInt(hex.substring(4, 6), 16)
  ];
}

async function findStyleFiles() {
  const files = [];
  const stylesPath = join(ROOT, 'styles');
  
  try {
    const entries = await readdir(stylesPath);
    for (const entry of entries) {
      if (entry.endsWith('.css')) {
        files.push(join(stylesPath, entry));
      }
    }
  } catch (error) {
    // Ignore
  }
  
  return files;
}

async function analyzeFile(filePath) {
  const content = await readFile(filePath, 'utf-8');
  const relativePath = filePath.replace(ROOT, '').replace(/\\/g, '/');
  
  const variables = { light: {}, dark: {} };
  const pairings = [];
  
  try {
    const root = postcss.parse(content);
    let currentTheme = 'light';
    
    root.walkRules((rule) => {
      // Detect theme context
      if (rule.selector.includes('[data-theme="dark"]')) {
        currentTheme = 'dark';
      } else if (!rule.selector.includes('[data-theme=')) {
        currentTheme = 'light';
      }
      
      let color = null;
      let bgColor = null;
      
      rule.walkDecls((decl) => {
        if (decl.prop.startsWith('--')) {
          const value = decl.value.trim();
          if (value.match(/^#[0-9a-f]{3,6}$/i)) {
            variables[currentTheme][decl.prop] = value;
          }
        }
        
        if (decl.prop === 'color') {
          color = decl.value;
        }
        if (decl.prop === 'background-color' || decl.prop === 'background') {
          bgColor = decl.value.split(' ')[0]; // Take first value
        }
      });
      
      // If we have both color and bg, check contrast
      if (color && bgColor && !color.includes('var(') && !bgColor.includes('var(')) {
        if (color.match(/^#[0-9a-f]{3,6}$/i) && bgColor.match(/^#[0-9a-f]{3,6}$/i)) {
          try {
            const colorRgb = hexToRgb(color);
            const bgRgb = hexToRgb(bgColor);
            const ratio = getContrastRatio(colorRgb, bgRgb);
            
            pairings.push({
              selector: rule.selector,
              color,
              bgColor,
              ratio: Math.round(ratio * 100) / 100,
              passAA: ratio >= WCAG_AA_NORMAL,
              passAALarge: ratio >= WCAG_AA_LARGE,
              file: relativePath,
              line: rule.source?.start?.line
            });
          } catch (err) {
            // Skip invalid colors
          }
        }
      }
    });
    
  } catch (error) {
    console.error(`   ⚠️  Parse error in ${relativePath}:`, error.message);
  }
  
  return { file: relativePath, variables, pairings };
}

function generateMarkdownReport(results) {
  let md = '# Color Contrast & Theme Parity Report\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  
  // Collect all variables
  const allLightVars = {};
  const allDarkVars = {};
  
  results.forEach(r => {
    Object.assign(allLightVars, r.variables.light);
    Object.assign(allDarkVars, r.variables.dark);
  });
  
  md += '## Summary\n\n';
  md += `- **Light Theme Variables:** ${Object.keys(allLightVars).length}\n`;
  md += `- **Dark Theme Variables:** ${Object.keys(allDarkVars).length}\n\n`;
  
  // Check for missing dark theme counterparts
  const missingInDark = Object.keys(allLightVars).filter(key => !allDarkVars[key]);
  const missingInLight = Object.keys(allDarkVars).filter(key => !allLightVars[key]);
  
  if (missingInDark.length > 0) {
    md += '### ⚠️ Variables Missing in Dark Theme\n\n';
    missingInDark.forEach(key => {
      md += `- \`${key}\` (defined as ${allLightVars[key]} in light theme)\n`;
    });
    md += '\n';
  }
  
  if (missingInLight.length > 0) {
    md += '### ⚠️ Variables Missing in Light Theme\n\n';
    missingInLight.forEach(key => {
      md += `- \`${key}\` (defined as ${allDarkVars[key]} in dark theme)\n`;
    });
    md += '\n';
  }
  
  // Contrast analysis
  const allPairings = results.flatMap(r => r.pairings);
  const failures = allPairings.filter(p => !p.passAA);
  
  md += '## Contrast Analysis\n\n';
  md += `- **Total Pairings Analyzed:** ${allPairings.length}\n`;
  md += `- **WCAG AA Failures:** ${failures.length}\n\n`;
  
  if (failures.length > 0) {
    md += '### ❌ WCAG AA Failures\n\n';
    md += '| Selector | Color | BG Color | Ratio | Required | File |\n';
    md += '|----------|-------|----------|-------|----------|------|\n';
    
    failures.forEach(p => {
      const selector = p.selector.substring(0, 30);
      const file = p.file.split('/').pop();
      md += `| \`${selector}\` | ${p.color} | ${p.bgColor} | **${p.ratio}** | 4.5 | ${file}:${p.line} |\n`;
    });
    md += '\n';
  } else if (allPairings.length > 0) {
    md += '✅ All analyzed color pairs pass WCAG AA!\n\n';
  }
  
  // Color palette overview
  md += '## Color Palette\n\n';
  md += '### Light Theme\n\n';
  Object.entries(allLightVars).slice(0, 20).forEach(([key, value]) => {
    md += `- \`${key}\`: ${value}\n`;
  });
  
  md += '\n### Dark Theme\n\n';
  Object.entries(allDarkVars).slice(0, 20).forEach(([key, value]) => {
    md += `- \`${key}\`: ${value}\n`;
  });
  md += '\n';
  
  // Recommendations
  md += '## Recommendations\n\n';
  md += '1. **Add missing theme variables** - ensure parity between light/dark\n';
  md += '2. **Fix contrast failures** - minimum 4.5:1 for normal text, 3:1 for large text\n';
  md += '3. **Use fallback values** - always provide fallback for `var()`\n';
  md += '4. **Test with users** - automated checks don\'t catch everything\n\n';
  
  md += '## Tools\n\n';
  md += '- **Contrast Checker:** https://webaim.org/resources/contrastchecker/\n';
  md += '- **Color Palette Generator:** https://coolors.co/\n\n';
  
  return md;
}

async function main() {
  console.log('🎨 Starting Color Contrast Analysis...\n');
  
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  console.log('📂 Finding CSS files...');
  const files = await findStyleFiles();
  console.log(`   Found ${files.length} files\n`);
  
  if (files.length === 0) {
    console.log('⚠️  No CSS files found in styles/');
    return;
  }
  
  const results = [];
  for (const file of files) {
    const relativePath = file.replace(ROOT, '').replace(/\\/g, '/');
    console.log(`   Analyzing: ${relativePath}`);
    const result = await analyzeFile(file);
    results.push(result);
  }
  
  // Generate report
  const markdown = generateMarkdownReport(results);
  const mdPath = join(ROOT, 'report/contrast.md');
  await writeFile(mdPath, markdown);
  console.log(`\n📄 Report saved: report/contrast.md`);
  
  // Save JSON
  const jsonPath = join(ROOT, 'report/contrast.json');
  await writeFile(jsonPath, JSON.stringify(results, null, 2));
  console.log(`💾 JSON data saved: report/contrast.json`);
  
  const allPairings = results.flatMap(r => r.pairings);
  const failures = allPairings.filter(p => !p.passAA).length;
  
  console.log('\n' + '='.repeat(60));
  console.log('COLOR CONTRAST SUMMARY');
  console.log('='.repeat(60));
  console.log(`Pairings Analyzed: ${allPairings.length}`);
  console.log(`WCAG AA Failures: ${failures}`);
  console.log('='.repeat(60) + '\n');
  
  if (failures > 0) {
    console.log('⚠️  Contrast issues found. Review report/contrast.md\n');
  }
}

main().catch(error => {
  console.error('❌ Analysis failed:', error);
  process.exit(1);
});
