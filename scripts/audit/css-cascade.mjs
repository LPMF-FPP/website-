#!/usr/bin/env node
/**
 * CSS Cascade & Specificity Analysis
 * Detects specificity issues, conflicts, and layer inconsistencies
 */

import { readFile, writeFile, mkdir } from 'fs/promises';
import { readdir, stat } from 'fs/promises';
import { dirname, join, extname } from 'path';
import { fileURLToPath } from 'url';
import postcss from 'postcss';
import { parse as cssTreeParse, walk } from 'css-tree';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../..');

// Files/directories to scan
const CSS_PATHS = [
  'resources/css',
  'public/css',
  'styles'
];

// Layout properties that shouldn't be in overlays
const LAYOUT_PROPS = new Set([
  'display', 'position', 'float', 'flex', 'flex-direction', 'flex-wrap', 
  'grid', 'grid-template', 'inset', 'top', 'right', 'bottom', 'left',
  'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'gap', 'transform', 'column-gap', 'row-gap'
]);

// Calculate specificity [inline, id, class/attr/pseudo, element/pseudo-element]
function calculateSpecificity(selector) {
  let a = 0; // inline (not applicable for CSS)
  let b = 0; // IDs
  let c = 0; // classes, attributes, pseudo-classes
  let d = 0; // elements, pseudo-elements
  
  try {
    const ast = cssTreeParse(selector, { context: 'selector' });
    
    walk(ast, {
      visit: 'IdSelector',
      enter() { b++; }
    });
    
    walk(ast, {
      visit: 'ClassSelector',
      enter() { c++; }
    });
    
    walk(ast, {
      visit: 'AttributeSelector',
      enter() { c++; }
    });
    
    walk(ast, {
      visit: 'PseudoClassSelector',
      enter(node) {
        // :not(), :is(), :where() don't add specificity themselves
        if (!['not', 'is', 'where'].includes(node.name)) {
          c++;
        }
      }
    });
    
    walk(ast, {
      visit: 'TypeSelector',
      enter() { d++; }
    });
    
    walk(ast, {
      visit: 'PseudoElementSelector',
      enter() { d++; }
    });
    
  } catch (error) {
    // Fallback to simple regex if parsing fails
    b = (selector.match(/#/g) || []).length;
    c = (selector.match(/\./g) || []).length + 
        (selector.match(/\[/g) || []).length +
        (selector.match(/:/g) || []).length;
    d = (selector.match(/^[a-z]+|[\s>+~][a-z]+/gi) || []).length;
  }
  
  return { a, b, c, d };
}

function specificityToScore(spec) {
  return spec.a * 1000 + spec.b * 100 + spec.c * 10 + spec.d;
}

function specificityToString(spec) {
  return `${spec.a},${spec.b},${spec.c},${spec.d}`;
}

function getSelectorDepth(selector) {
  const cleaned = selector.replace(/:[^\s>+~[]+/g, '').replace(/\[[^\]]+\]/g, '');
  const parts = cleaned.split(/[\s>+~]+/).filter(p => p.trim());
  return parts.length;
}

async function findCSSFiles(paths) {
  const files = [];
  
  for (const path of paths) {
    const fullPath = join(ROOT, path);
    try {
      const statInfo = await stat(fullPath);
      
      if (statInfo.isFile() && /\.css$/.test(fullPath)) {
        files.push(fullPath);
      } else if (statInfo.isDirectory()) {
        const entries = await readdir(fullPath, { withFileTypes: true });
        for (const entry of entries) {
          const entryPath = join(path, entry.name);
          if (entry.isFile() && /\.css$/.test(entry.name)) {
            files.push(join(ROOT, entryPath));
          } else if (entry.isDirectory() && !entry.name.includes('node_modules')) {
            // Recursively search subdirectories
            const subFiles = await findCSSFiles([entryPath]);
            files.push(...subFiles);
          }
        }
      }
    } catch (error) {
      // Path doesn't exist, skip
    }
  }
  
  return files;
}

async function analyzeCSS(filePath) {
  const content = await readFile(filePath, 'utf-8');
  const relativePath = filePath.replace(ROOT, '').replace(/\\/g, '/');
  
  const issues = [];
  const rules = [];
  const variables = new Map();
  const layers = new Set();
  
  try {
    const root = postcss.parse(content);
    
    root.walkAtRules('layer', (atRule) => {
      layers.add(atRule.params);
    });
    
    root.walkDecls((decl) => {
      // Collect CSS variables
      if (decl.prop.startsWith('--')) {
        variables.set(decl.prop, {
          file: relativePath,
          value: decl.value,
          line: decl.source?.start?.line
        });
      }
      
      // Check for layout properties in overlay files
      if (relativePath.includes('pd-') || relativePath.includes('pd.')) {
        if (LAYOUT_PROPS.has(decl.prop)) {
          issues.push({
            type: 'layout-in-overlay',
            severity: 'critical',
            file: relativePath,
            line: decl.source?.start?.line,
            property: decl.prop,
            message: `Layout property "${decl.prop}" found in overlay file (should be visual-only)`
          });
        }
      }
    });
    
    root.walkRules((rule) => {
      const selectors = rule.selector.split(',').map(s => s.trim());
      
      selectors.forEach(selector => {
        const spec = calculateSpecificity(selector);
        const score = specificityToScore(spec);
        const depth = getSelectorDepth(selector);
        
        const ruleData = {
          selector,
          specificity: spec,
          specificityString: specificityToString(spec),
          score,
          depth,
          file: relativePath,
          line: rule.source?.start?.line,
          declarations: []
        };
        
        rule.walkDecls((decl) => {
          ruleData.declarations.push({
            property: decl.prop,
            value: decl.value,
            important: decl.important
          });
          
          // Check for !important
          if (decl.important && !relativePath.includes('utility')) {
            issues.push({
              type: 'important-abuse',
              severity: 'major',
              file: relativePath,
              line: decl.source?.start?.line,
              selector,
              property: decl.prop,
              message: '!important used (consider refactoring specificity)'
            });
          }
        });
        
        rules.push(ruleData);
        
        // Check high specificity
        if (score > 40 && spec.b === 0) { // High class/element specificity without IDs
          issues.push({
            type: 'high-specificity',
            severity: 'major',
            file: relativePath,
            line: rule.source?.start?.line,
            selector,
            specificity: specificityToString(spec),
            score,
            message: `High specificity (${specificityToString(spec)}) - difficult to override`
          });
        }
        
        // Check deep nesting
        if (depth > 4) {
          issues.push({
            type: 'deep-nesting',
            severity: 'minor',
            file: relativePath,
            line: rule.source?.start?.line,
            selector,
            depth,
            message: `Selector too deep (${depth} levels) - consider flattening`
          });
        }
        
        // Check ID selectors
        if (spec.b > 0) {
          issues.push({
            type: 'id-selector',
            severity: 'minor',
            file: relativePath,
            line: rule.source?.start?.line,
            selector,
            message: 'ID selector used - prefer classes for styling'
          });
        }
      });
    });
    
  } catch (error) {
    console.error(`   ⚠️  Parse error in ${relativePath}:`, error.message);
  }
  
  return { rules, issues, variables, layers: Array.from(layers) };
}

function detectConflicts(allRules) {
  const conflicts = [];
  const propertyMap = new Map();
  
  // Group rules by property
  allRules.forEach(rule => {
    rule.declarations.forEach(decl => {
      const key = decl.property;
      if (!propertyMap.has(key)) {
        propertyMap.set(key, []);
      }
      propertyMap.get(key).push({
        selector: rule.selector,
        value: decl.value,
        file: rule.file,
        line: rule.line,
        score: rule.score
      });
    });
  });
  
  // Find conflicts (same property, different values, similar specificity)
  propertyMap.forEach((rules, property) => {
    if (rules.length < 2) return;
    
    for (let i = 0; i < rules.length; i++) {
      for (let j = i + 1; j < rules.length; j++) {
        const r1 = rules[i];
        const r2 = rules[j];
        
        // Similar specificity but different values
        if (Math.abs(r1.score - r2.score) <= 10 && r1.value !== r2.value) {
          conflicts.push({
            property,
            rule1: { selector: r1.selector, value: r1.value, file: r1.file, line: r1.line },
            rule2: { selector: r2.selector, value: r2.value, file: r2.file, line: r2.line },
            message: `Potential conflict: "${property}" has different values in similar-specificity selectors`
          });
        }
      }
    }
  });
  
  return conflicts;
}

function generateMarkdownReport(allRules, allIssues, allVariables, conflicts, layers) {
  let md = '# CSS Cascade & Specificity Report\n\n';
  md += `**Generated:** ${new Date().toLocaleString()}\n\n`;
  
  // Summary
  md += '## Summary\n\n';
  md += `- **Total Rules:** ${allRules.length}\n`;
  md += `- **Total Issues:** ${allIssues.length}\n`;
  md += `- **CSS Variables:** ${allVariables.size}\n`;
  md += `- **Detected Conflicts:** ${conflicts.length}\n`;
  md += `- **@layer declarations:** ${layers.length}\n\n`;
  
  // Issues by severity
  const critical = allIssues.filter(i => i.severity === 'critical');
  const major = allIssues.filter(i => i.severity === 'major');
  const minor = allIssues.filter(i => i.severity === 'minor');
  
  md += '### Issues by Severity\n\n';
  md += `- 🔴 **Critical:** ${critical.length}\n`;
  md += `- 🟠 **Major:** ${major.length}\n`;
  md += `- 🟡 **Minor:** ${minor.length}\n\n`;
  
  // Critical issues
  if (critical.length > 0) {
    md += '## 🔴 Critical Issues\n\n';
    critical.forEach(issue => {
      md += `### ${issue.file}:${issue.line || '?'}\n\n`;
      md += `**Type:** ${issue.type}\n\n`;
      md += `**Message:** ${issue.message}\n\n`;
      if (issue.selector) md += `**Selector:** \`${issue.selector}\`\n\n`;
      if (issue.property) md += `**Property:** \`${issue.property}\`\n\n`;
      md += '---\n\n';
    });
  }
  
  // Major issues
  if (major.length > 0) {
    md += '## 🟠 Major Issues\n\n';
    md += '| File | Line | Type | Selector | Message |\n';
    md += '|------|------|------|----------|----------|\n';
    major.slice(0, 20).forEach(issue => {
      const file = issue.file.split('/').pop();
      const selector = (issue.selector || '').substring(0, 30);
      md += `| ${file} | ${issue.line || '?'} | ${issue.type} | \`${selector}\` | ${issue.message.substring(0, 50)} |\n`;
    });
    if (major.length > 20) {
      md += `\n...and ${major.length - 20} more\n`;
    }
    md += '\n';
  }
  
  // Conflicts
  if (conflicts.length > 0) {
    md += '## ⚠️ Potential Conflicts\n\n';
    conflicts.slice(0, 10).forEach((conflict, idx) => {
      md += `### ${idx + 1}. Property: \`${conflict.property}\`\n\n`;
      md += `**Rule 1:** \`${conflict.rule1.selector}\` → \`${conflict.rule1.value}\`\n`;
      md += `  - File: ${conflict.rule1.file}:${conflict.rule1.line}\n\n`;
      md += `**Rule 2:** \`${conflict.rule2.selector}\` → \`${conflict.rule2.value}\`\n`;
      md += `  - File: ${conflict.rule2.file}:${conflict.rule2.line}\n\n`;
      md += `**Impact:** ${conflict.message}\n\n`;
      md += '---\n\n';
    });
    if (conflicts.length > 10) {
      md += `...and ${conflicts.length - 10} more conflicts\n\n`;
    }
  }
  
  // Top specificity offenders
  md += '## 📊 Highest Specificity Selectors\n\n';
  const sorted = [...allRules].sort((a, b) => b.score - a.score).slice(0, 15);
  md += '| Specificity | Score | Selector | File |\n';
  md += '|-------------|-------|----------|------|\n';
  sorted.forEach(rule => {
    const sel = rule.selector.substring(0, 40);
    const file = rule.file.split('/').pop();
    md += `| ${rule.specificityString} | ${rule.score} | \`${sel}\` | ${file}:${rule.line} |\n`;
  });
  md += '\n';
  
  // CSS Variables check
  md += '## 🎨 CSS Variables\n\n';
  const varArray = Array.from(allVariables.entries());
  md += `Total custom properties: ${varArray.length}\n\n`;
  
  // Check for variables without fallback
  const noFallback = allRules.filter(rule => {
    return rule.declarations.some(d => 
      d.value.includes('var(') && !d.value.includes(',')
    );
  });
  
  if (noFallback.length > 0) {
    md += '### ⚠️ Variables without fallback\n\n';
    md += 'These declarations use CSS variables without fallback values:\n\n';
    noFallback.slice(0, 10).forEach(rule => {
      const decl = rule.declarations.find(d => d.value.includes('var(') && !d.value.includes(','));
      md += `- \`${rule.selector}\` { ${decl.property}: ${decl.value} } (${rule.file})\n`;
    });
    md += '\n';
  }
  
  // @layer analysis
  if (layers.length > 0) {
    md += '## 📦 CSS Layers\n\n';
    md += 'Detected @layer declarations:\n\n';
    layers.forEach(layer => {
      md += `- \`@layer ${layer}\`\n`;
    });
    md += '\n';
  }
  
  // Recommendations
  md += '## Recommendations\n\n';
  md += '### Critical:\n';
  md += '1. **Remove layout properties from overlay files** (pd-*.css)\n';
  md += '2. **Resolve property conflicts** - ensure consistent values\n\n';
  
  md += '### Major:\n';
  md += '1. **Refactor high-specificity selectors** - use classes instead of deep nesting\n';
  md += '2. **Remove !important** - fix specificity instead\n';
  md += '3. **Flatten deep selectors** - prefer flat BEM-style classes\n\n';
  
  md += '### Minor:\n';
  md += '1. **Replace ID selectors with classes**\n';
  md += '2. **Add fallback values to CSS variables**\n';
  md += '3. **Use @layer consistently** for cascade control\n\n';
  
  return md;
}

async function main() {
  console.log('🔍 Starting CSS Cascade Analysis...\n');
  
  await mkdir(join(ROOT, 'report'), { recursive: true });
  
  console.log('📂 Finding CSS files...');
  const files = await findCSSFiles(CSS_PATHS);
  console.log(`   Found ${files.length} CSS files\n`);
  
  if (files.length === 0) {
    console.log('⚠️  No CSS files found in specified paths');
    return;
  }
  
  const allRules = [];
  const allIssues = [];
  const allVariables = new Map();
  const allLayers = new Set();
  
  for (const file of files) {
    const relativePath = file.replace(ROOT, '').replace(/\\/g, '/');
    console.log(`   Analyzing: ${relativePath}`);
    
    const result = await analyzeCSS(file);
    allRules.push(...result.rules);
    allIssues.push(...result.issues);
    result.variables.forEach((v, k) => allVariables.set(k, v));
    result.layers.forEach(l => allLayers.add(l));
  }
  
  console.log('\n🔬 Detecting conflicts...');
  const conflicts = detectConflicts(allRules);
  
  // Generate report
  const markdown = generateMarkdownReport(
    allRules,
    allIssues,
    allVariables,
    conflicts,
    Array.from(allLayers)
  );
  
  const mdPath = join(ROOT, 'report/cascade-map.md');
  await writeFile(mdPath, markdown);
  console.log(`\n📄 Report saved: report/cascade-map.md`);
  
  // Save detailed JSON
  const jsonPath = join(ROOT, 'report/cascade-map.json');
  await writeFile(jsonPath, JSON.stringify({
    rules: allRules,
    issues: allIssues,
    conflicts,
    variables: Array.from(allVariables.entries()),
    layers: Array.from(allLayers)
  }, null, 2));
  console.log(`💾 JSON data saved: report/cascade-map.json`);
  
  // Summary
  const critical = allIssues.filter(i => i.severity === 'critical').length;
  const major = allIssues.filter(i => i.severity === 'major').length;
  
  console.log('\n' + '='.repeat(60));
  console.log('CSS CASCADE ANALYSIS SUMMARY');
  console.log('='.repeat(60));
  console.log(`Total Rules: ${allRules.length}`);
  console.log(`Critical Issues: ${critical}`);
  console.log(`Major Issues: ${major}`);
  console.log(`Conflicts: ${conflicts.length}`);
  console.log('='.repeat(60) + '\n');
  
  if (critical > 0) {
    console.log('🔴 Critical issues found! Review report immediately.\n');
  }
}

main().catch(error => {
  console.error('❌ Analysis failed:', error);
  process.exit(1);
});
