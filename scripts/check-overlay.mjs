#!/usr/bin/env node
/**
 * Build Guard: Prevent layout properties in safe CSS overlay
 * Usage: node scripts/check-overlay.mjs [css-file]
 */

import { readFile } from 'fs/promises';
import { join } from 'path';

const DANGEROUS_PROPERTIES = [
  // Layout positioning
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

  // Box model (dimensions & spacing)
  'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',

  // Transform & overflow
  'transform', 'transform-origin', 'overflow', 'overflow-x', 'overflow-y',
  'clip', 'clip-path'
];

const SAFE_PROPERTIES = [
  // Colors & backgrounds
  'color', 'background', 'background-color', 'background-image', 'background-position',
  'background-size', 'background-repeat', 'background-attachment', 'background-clip',

  // Borders & shapes
  'border', 'border-width', 'border-style', 'border-color', 'border-radius',
  'border-top', 'border-right', 'border-bottom', 'border-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
  'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
  'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
  'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',

  // Shadows & effects
  'box-shadow', 'text-shadow', 'filter', 'backdrop-filter', 'opacity',

  // Typography
  'font', 'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
  'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-decoration',
  'text-transform', 'text-indent', 'white-space', 'word-break', 'word-wrap',
  'hyphens', 'text-overflow', 'vertical-align',

  // Visual enhancements
  'outline', 'outline-width', 'outline-style', 'outline-color', 'outline-offset',
  'cursor', 'user-select', 'pointer-events',

  // Transitions & animations (visual only)
  'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
  'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay',
  'animation-iteration-count', 'animation-direction', 'animation-fill-mode', 'animation-play-state',

  // Misc safe properties
  'list-style', 'list-style-type', 'list-style-position', 'list-style-image',
  'table-layout', 'border-collapse', 'border-spacing', 'caption-side', 'empty-cells',
  'quotes', 'content', 'counter-reset', 'counter-increment'
];

async function checkCSSFile(filePath) {
  try {
    const content = await readFile(filePath, 'utf-8');
    const violations = [];
    const lines = content.split('\n');

    let inSelector = false;
    let currentSelector = '';
    let braceDepth = 0;

    lines.forEach((line, index) => {
      const trimmed = line.trim();
      const lineNum = index + 1;

      // Track CSS structure
      if (trimmed.includes('{')) {
        braceDepth += (trimmed.match(/{/g) || []).length;
        if (braceDepth === 1 && !inSelector) {
          inSelector = true;
          currentSelector = trimmed.split('{')[0].trim();
        }
      }

      if (trimmed.includes('}')) {
        braceDepth -= (trimmed.match(/}/g) || []).length;
        if (braceDepth === 0) {
          inSelector = false;
          currentSelector = '';
        }
      }

      // Check for dangerous properties within selectors
      if (inSelector && braceDepth > 0) {
        const propertyMatch = trimmed.match(/^\s*([a-z-]+)\s*:/);
        if (propertyMatch) {
          const property = propertyMatch[1];

          if (DANGEROUS_PROPERTIES.includes(property)) {
            violations.push({
              property,
              selector: currentSelector,
              line: lineNum,
              content: trimmed
            });
          }
        }
      }
    });

    return violations;

  } catch (error) {
    console.error(`Error reading file ${filePath}:`, error.message);
    return null;
  }
}

function reportViolations(violations, filePath) {
  if (violations.length === 0) {
    console.log(`✅ Safe overlay check passed for ${filePath}`);
    console.log('   No layout-affecting properties found.\n');
    return true;
  }

  console.log(`❌ Layout violations found in ${filePath}:\n`);

  violations.forEach(({ property, selector, line, content }) => {
    console.log(`   Line ${line}: ${property}`);
    console.log(`   Selector: ${selector}`);
    console.log(`   Content: ${content}`);
    console.log('   ↳ This property can cause layout shifts!\n');
  });

  console.log('Safe alternatives:');
  console.log('• Use only: color, background-color, border-color, border-radius, box-shadow, opacity');
  console.log('• For spacing: use outline-offset instead of margin/padding adjustments');
  console.log('• For positioning: use transform: translate() or position: sticky only\n');

  return false;
}

function showHelp() {
  console.log('Pusdokkes Safe Overlay Guard');
  console.log('============================');
  console.log('Prevents layout-affecting CSS properties in safe theme overlays.\n');
  console.log('Usage:');
  console.log('  node scripts/check-overlay.mjs [css-file]');
  console.log('  node scripts/check-overlay.mjs styles/pd-safe-layers.css\n');
  console.log('Safe properties (visual only):');
  console.log('  ✅ color, background-color, border-color, border-radius');
  console.log('  ✅ box-shadow, opacity, outline, filter');
  console.log('  ✅ font-*, text-*, transition, animation\n');
  console.log('Dangerous properties (layout affecting):');
  console.log('  ❌ display, position, width, height, margin, padding');
  console.log('  ❌ flex*, grid*, transform (except translate), overflow');
}

async function main() {
  const args = process.argv.slice(2);

  if (args.includes('--help') || args.includes('-h')) {
    showHelp();
    return;
  }

  // Default files to check (Safe Mode v2)
  const defaultFiles = [
    'styles/pd.ultrasafe.tokens.css',
    'styles/pd.framework-bridge.css'
  ];

  const filesToCheck = args.length > 0 ? args : defaultFiles;

  console.log('Checking safe overlay files...\n');

  let allPassed = true;

  for (const filePath of filesToCheck) {
    const fullPath = join(process.cwd(), filePath);

    console.log(`Checking: ${filePath}`);

    const violations = await checkCSSFile(fullPath);
    if (violations === null) {
      console.log(`   ⚠️  File not found or cannot be read\n`);
      allPassed = false;
      continue;
    }

    const passed = reportViolations(violations, filePath);
    if (!passed) {
      allPassed = false;
    }
  }

  if (!allPassed) {
    console.log('❌ Build guard failed. Please fix layout violations before deployment.');
    process.exit(1);
  }

  console.log('✅ Safe overlay validation complete! 🎉');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch(console.error);
}
