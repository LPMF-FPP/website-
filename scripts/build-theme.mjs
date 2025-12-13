#!/usr/bin/env node

/**
 * Build Theme System
 *
 * This script reads raw tokens from extract-tokens.mjs and maps them
 * to a standardized design system schema, generating CSS variables
 * with light and dark theme support.
 */

import { readFile, writeFile, mkdir } from 'fs/promises';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Standard design system schema
 */
const DESIGN_SCHEMA = {
  color: {
    primary: '',
    secondary: '',
    accent: '',
    success: '',
    warning: '',
    danger: '',
    info: '',
    bg: '',
    surface: '',
    text: '',
    muted: '',
    border: ''
  },
  radius: {
    sm: '',
    md: '',
    lg: '',
    xl: ''
  },
  shadow: {
    sm: '',
    md: '',
    lg: ''
  },
  spacing: {
    1: '4px',
    2: '8px',
    3: '12px',
    4: '16px',
    5: '24px',
    6: '32px',
    8: '48px',
    10: '64px',
    12: '96px'
  },
  z: {
    nav: '10',
    overlay: '1000',
    toast: '1100',
    modal: '1200'
  },
  breakpoints: {
    sm: '640px',
    md: '768px',
    lg: '1024px',
    xl: '1280px',
    '2xl': '1536px'
  },
  motion: {
    fast: '150ms',
    base: '200ms',
    slow: '300ms',
    easing: 'cubic-bezier(.2,.8,.2,1)'
  },
  font: {
    sans: '',
    mono: ''
  }
};

/**
 * Color utility functions
 */
function hexToHsl(hex) {
  // Remove # if present
  hex = hex.replace('#', '');

  // Parse r, g, b values
  const r = parseInt(hex.substr(0, 2), 16) / 255;
  const g = parseInt(hex.substr(2, 2), 16) / 255;
  const b = parseInt(hex.substr(4, 2), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;

  if (max === min) {
    h = s = 0;
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h /= 6;
  }

  return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
}

function adjustLightness(hex, amount) {
  const [h, s, l] = hexToHsl(hex);
  const newL = Math.max(0, Math.min(100, l + amount));
  return `hsl(${h}, ${s}%, ${newL}%)`;
}

/**
 * Intelligent color mapping based on common patterns
 */
function mapColors(colors) {
  const colorMap = { ...DESIGN_SCHEMA.color };

  // Sort colors by frequency and position in common design patterns
  const sortedColors = colors.sort();

  // Common blue variations for primary
  const blues = colors.filter(c =>
    c.match(/#[0-4][0-9a-f][4-9a-f][0-9a-f]{3}/) ||
    c.includes('rgb(30,') || c.includes('rgb(59,') || c.includes('rgb(37,')
  );

  // Common green variations for success
  const greens = colors.filter(c =>
    c.match(/#[0-4][4-9a-f][0-4][0-9a-f]{3}/) ||
    c.includes('rgb(5,') || c.includes('rgb(34,')
  );

  // Common red variations for danger
  const reds = colors.filter(c =>
    c.match(/#[d-f][0-4][0-4][0-9a-f]{3}/) ||
    c.includes('rgb(220,') || c.includes('rgb(239,')
  );

  // Common orange/yellow for warning
  const oranges = colors.filter(c =>
    c.match(/#[d-f][5-9a-f][0-4][0-9a-f]{3}/) ||
    c.includes('rgb(217,') || c.includes('rgb(245,')
  );

  // Common grays
  const grays = colors.filter(c =>
    c.match(/#[5-9][5-9][5-9][5-9]{3}/) ||
    c.includes('rgb(100,') || c.includes('rgb(148,')
  );

  // Map colors intelligently
  colorMap.primary = blues[0] || '#1e40af';
  colorMap.secondary = grays[0] || '#64748b';
  colorMap.success = greens[0] || '#059669';
  colorMap.warning = oranges[0] || '#d97706';
  colorMap.danger = reds[0] || '#dc2626';
  colorMap.info = blues[1] || '#0284c7';

  // Background and surface colors
  const lightColors = colors.filter(c => {
    if (c.startsWith('#')) {
      const [,, l] = hexToHsl(c);
      return l > 85;
    }
    return c.includes('rgb(248,') || c.includes('rgb(255,');
  });

  const darkColors = colors.filter(c => {
    if (c.startsWith('#')) {
      const [,, l] = hexToHsl(c);
      return l < 20;
    }
    return c.includes('rgb(15,') || c.includes('rgb(0,');
  });

  colorMap.bg = lightColors[0] || '#ffffff';
  colorMap.surface = lightColors[1] || '#f8fafc';
  colorMap.text = darkColors[0] || '#1e293b';
  colorMap.muted = grays[1] || '#64748b';
  colorMap.border = grays[2] || '#e2e8f0';

  return colorMap;
}

/**
 * Map raw tokens to design schema
 */
function mapTokens(rawTokens) {
  const schema = JSON.parse(JSON.stringify(DESIGN_SCHEMA));

  // Map colors
  schema.color = mapColors(rawTokens.colors);

  // Map fonts
  const systemFonts = rawTokens.fonts.filter(f =>
    !f.includes('serif') && !f.includes('mono') && !f.includes('cursive')
  );
  const monoFonts = rawTokens.fonts.filter(f =>
    f.includes('mono') || f.includes('Consolas') || f.includes('Monaco')
  );

  schema.font.sans = systemFonts.slice(0, 3).join(', ') + ', system-ui, sans-serif';
  schema.font.mono = monoFonts.slice(0, 2).join(', ') + ', Consolas, monospace';

  // Map breakpoints
  if (rawTokens.breakpoints.length > 0) {
    const sortedBreakpoints = rawTokens.breakpoints.sort((a, b) => {
      return parseInt(a) - parseInt(b);
    });

    const breakpointKeys = ['sm', 'md', 'lg', 'xl', '2xl'];
    breakpointKeys.forEach((key, index) => {
      if (sortedBreakpoints[index]) {
        schema.breakpoints[key] = sortedBreakpoints[index];
      }
    });
  }

  // Map shadows
  if (rawTokens.shadows.length > 0) {
    schema.shadow.sm = rawTokens.shadows[0] || '0 1px 3px 0 rgb(0 0 0 / 0.1)';
    schema.shadow.md = rawTokens.shadows[1] || '0 4px 6px -1px rgb(0 0 0 / 0.1)';
    schema.shadow.lg = rawTokens.shadows[2] || '0 10px 15px -3px rgb(0 0 0 / 0.1)';
  }

  // Map border radius
  if (rawTokens.radius.length > 0) {
    const sortedRadius = rawTokens.radius.sort((a, b) => {
      return parseFloat(a) - parseFloat(b);
    });

    schema.radius.sm = sortedRadius[0] || '0.25rem';
    schema.radius.md = sortedRadius[1] || '0.375rem';
    schema.radius.lg = sortedRadius[2] || '0.5rem';
    schema.radius.xl = sortedRadius[3] || '0.75rem';
  }

  // Map motion
  if (rawTokens.motion.durations.length > 0) {
    const sortedDurations = rawTokens.motion.durations.sort((a, b) => {
      return parseInt(a) - parseInt(b);
    });

    schema.motion.fast = sortedDurations[0] || '150ms';
    schema.motion.base = sortedDurations[1] || '200ms';
    schema.motion.slow = sortedDurations[2] || '300ms';
  }

  if (rawTokens.motion.easings.length > 0) {
    schema.motion.easing = rawTokens.motion.easings[0] || 'cubic-bezier(.2,.8,.2,1)';
  }

  return schema;
}

/**
 * Generate dark theme variants
 */
function generateDarkTheme(lightTheme) {
  const darkTheme = JSON.parse(JSON.stringify(lightTheme));

  // Invert background colors
  darkTheme.color.bg = '#0f172a';
  darkTheme.color.surface = '#1e293b';
  darkTheme.color.text = '#f1f5f9';
  darkTheme.color.muted = '#94a3b8';
  darkTheme.color.border = '#334155';

  // Adjust primary colors for dark theme
  if (lightTheme.color.primary.startsWith('#')) {
    darkTheme.color.primary = adjustLightness(lightTheme.color.primary, 20);
  }

  if (lightTheme.color.secondary.startsWith('#')) {
    darkTheme.color.secondary = adjustLightness(lightTheme.color.secondary, 15);
  }

  return darkTheme;
}

/**
 * Generate CSS variables from theme object
 */
function generateCSSVariables(theme, prefix = 'pd') {
  const cssVars = [];

  function processObject(obj, path = []) {
    for (const [key, value] of Object.entries(obj)) {
      if (typeof value === 'object' && value !== null) {
        processObject(value, [...path, key]);
      } else {
        const varName = `--${prefix}-${[...path, key].join('-')}`;
        cssVars.push(`  ${varName}: ${value};`);
      }
    }
  }

  processObject(theme);
  return cssVars;
}

/**
 * Generate complete tokens.css file
 */
function generateTokensCSS(lightTheme, darkTheme) {
  const lightVars = generateCSSVariables(lightTheme);
  const darkVars = generateCSSVariables(darkTheme);

  return `/**
 * Design System Tokens
 * Generated from extracted design tokens
 * Prefix: --pd- (Pusdokkes Design)
 */

/* Light Theme (Default) */
:root {
${lightVars.join('\\n')}
}

/* Dark Theme */
html[data-theme="dark"] {
${darkVars.join('\\n')}
}

/* System Theme Support */
@media (prefers-color-scheme: dark) {
  html[data-theme="system"] {
${darkVars.join('\\n')}
  }
}

/* CSS Custom Properties Fallbacks */
:root {
  /* Ensure fallbacks for critical properties */
  --pd-color-primary-rgb: 30, 64, 175;
  --pd-color-bg-rgb: 255, 255, 255;
  --pd-color-text-rgb: 30, 41, 59;
}

html[data-theme="dark"] {
  --pd-color-primary-rgb: 59, 130, 246;
  --pd-color-bg-rgb: 15, 23, 42;
  --pd-color-text-rgb: 241, 245, 249;
}

/* Semantic Color Aliases */
:root {
  --pd-focus-ring: 0 0 0 2px var(--pd-color-primary);
  --pd-focus-ring-offset: 0 0 0 2px var(--pd-color-bg);
}`;
}

/**
 * Main build function
 */
async function buildTheme() {
  try {
    console.log('🔨 Building theme system...');

    // Read raw tokens
    const rawTokensPath = `${__dirname}/../temp/raw-tokens.json`;
    const rawTokensContent = await readFile(rawTokensPath, 'utf-8');
    const rawTokens = JSON.parse(rawTokensContent);

    console.log('📖 Raw tokens loaded successfully');

    // Map to design schema
    const lightTheme = mapTokens(rawTokens);
    const darkTheme = generateDarkTheme(lightTheme);

    console.log('🎨 Theme mapping completed');

    // Generate CSS
    const tokensCSS = generateTokensCSS(lightTheme, darkTheme);

    // Ensure styles directory exists
    await mkdir(`${__dirname}/../styles`, { recursive: true });

    // Write tokens.css
    const tokensPath = `${__dirname}/../styles/tokens.css`;
    await writeFile(tokensPath, tokensCSS);

    // Write theme objects for reference
    const themeDataPath = `${__dirname}/../temp/theme-data.json`;
    await writeFile(themeDataPath, JSON.stringify({
      light: lightTheme,
      dark: darkTheme,
      generatedAt: new Date().toISOString()
    }, null, 2));

    console.log(`✅ Theme system built successfully!`);
    console.log(`📁 CSS Variables: ${tokensPath}`);
    console.log(`📊 Theme Data: ${themeDataPath}`);

    // Print summary
    console.log(`\\n📋 Theme Summary:`);
    console.log(`   🎨 Primary: ${lightTheme.color.primary}`);
    console.log(`   🎨 Secondary: ${lightTheme.color.secondary}`);
    console.log(`   🔤 Font: ${lightTheme.font.sans.split(',')[0]}`);
    console.log(`   📱 Breakpoints: ${Object.values(lightTheme.breakpoints).join(', ')}`);

  } catch (error) {
    console.error('❌ Error building theme:', error);
    throw error;
  }
}

/**
 * Main execution
 */
async function main() {
  await buildTheme();
}

// Run the script
if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}

export { buildTheme, mapTokens, generateDarkTheme };
