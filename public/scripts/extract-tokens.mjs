#!/usr/bin/env node

/**
 * Extract Design Tokens from Reference Sites
 *
 * This script extracts design tokens from public CSS files of reference sites
 * for the purpose of creating a consistent theme system. It respects copyright
 * by only extracting publicly available styling patterns, not content or assets.
 */

import { writeFile, mkdir } from 'fs/promises';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Reference URLs for token extraction
const REFERENCE_URLS = [
  'https://pusdokkes.polri.go.id/',
  'https://sso.pusdokkes.polri.go.id/',
  'https://pusdokkes.polri.go.id/Facility'
];

/**
 * Extract CSS variables from CSS text
 */
function extractCSSVariables(cssText) {
  const variables = new Map();
  const variableRegex = /--([\\w-]+):\\s*([^;]+);/g;
  let match;

  while ((match = variableRegex.exec(cssText)) !== null) {
    const [, name, value] = match;
    variables.set(name, value.trim());
  }

  return Object.fromEntries(variables);
}

/**
 * Extract color values from CSS text
 */
function extractColors(cssText) {
  const colors = new Set();

  // Extract hex colors
  const hexRegex = /#([0-9a-fA-F]{3,8})/g;
  let match;
  while ((match = hexRegex.exec(cssText)) !== null) {
    colors.add(match[0]);
  }

  // Extract rgb/rgba colors
  const rgbRegex = /rgba?\\([^)]+\\)/g;
  while ((match = rgbRegex.exec(cssText)) !== null) {
    colors.add(match[0]);
  }

  // Extract hsl/hsla colors
  const hslRegex = /hsla?\\([^)]+\\)/g;
  while ((match = hslRegex.exec(cssText)) !== null) {
    colors.add(match[0]);
  }

  return Array.from(colors);
}

/**
 * Extract font families from CSS text
 */
function extractFontFamilies(cssText) {
  const fonts = new Set();
  const fontRegex = /font-family:\\s*([^;]+);/g;
  let match;

  while ((match = fontRegex.exec(cssText)) !== null) {
    const fontValue = match[1].trim();
    // Clean up font declarations
    const cleanFont = fontValue.replace(/["']/g, '').split(',')[0].trim();
    if (cleanFont && !cleanFont.includes('inherit') && !cleanFont.includes('initial')) {
      fonts.add(cleanFont);
    }
  }

  return Array.from(fonts);
}

/**
 * Extract breakpoints from CSS text
 */
function extractBreakpoints(cssText) {
  const breakpoints = new Set();
  const mediaRegex = /@media[^{]*\\(min-width:\\s*([^)]+)\\)/g;
  let match;

  while ((match = mediaRegex.exec(cssText)) !== null) {
    const width = match[1].trim();
    if (width.includes('px')) {
      breakpoints.add(width);
    }
  }

  return Array.from(breakpoints).sort((a, b) => {
    const aNum = parseInt(a);
    const bNum = parseInt(b);
    return aNum - bNum;
  });
}

/**
 * Extract shadow values from CSS text
 */
function extractShadows(cssText) {
  const shadows = new Set();
  const shadowRegex = /box-shadow:\\s*([^;]+);/g;
  let match;

  while ((match = shadowRegex.exec(cssText)) !== null) {
    const shadowValue = match[1].trim();
    if (shadowValue !== 'none' && shadowValue !== 'inherit') {
      shadows.add(shadowValue);
    }
  }

  return Array.from(shadows);
}

/**
 * Extract border radius values from CSS text
 */
function extractBorderRadius(cssText) {
  const radius = new Set();
  const radiusRegex = /border-radius:\\s*([^;]+);/g;
  let match;

  while ((match = radiusRegex.exec(cssText)) !== null) {
    const radiusValue = match[1].trim();
    if (radiusValue !== '0' && radiusValue !== 'inherit') {
      radius.add(radiusValue);
    }
  }

  return Array.from(radius);
}

/**
 * Extract transition and animation durations
 */
function extractMotionTokens(cssText) {
  const durations = new Set();
  const easings = new Set();

  // Extract transition durations
  const transitionRegex = /transition(?:-duration)?:\\s*([^;]+);/g;
  let match;
  while ((match = transitionRegex.exec(cssText)) !== null) {
    const value = match[1].trim();
    if (value.includes('ms') || value.includes('s')) {
      durations.add(value.split(' ')[0]);
    }
  }

  // Extract easing functions
  const easingRegex = /transition-timing-function:\\s*([^;]+);/g;
  while ((match = easingRegex.exec(cssText)) !== null) {
    const easing = match[1].trim();
    easings.add(easing);
  }

  return {
    durations: Array.from(durations),
    easings: Array.from(easings)
  };
}

/**
 * Fetch and parse a single URL
 */
async function processUrl(url) {
  try {
    console.log(`Processing: ${url}`);

    // Fetch HTML
    const response = await fetch(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
      }
    });

    if (!response.ok) {
      console.warn(`Failed to fetch ${url}: ${response.status}`);
      return null;
    }

    const html = await response.text();

    // Extract CSS links
    const linkRegex = /<link[^>]*rel=["']stylesheet["'][^>]*href=["']([^"']+)["'][^>]*>/g;
    const cssUrls = [];
    let match;

    while ((match = linkRegex.exec(html)) !== null) {
      let cssUrl = match[1];
      if (cssUrl.startsWith('//')) {
        cssUrl = 'https:' + cssUrl;
      } else if (cssUrl.startsWith('/')) {
        cssUrl = new URL(cssUrl, url).href;
      } else if (!cssUrl.startsWith('http')) {
        cssUrl = new URL(cssUrl, url).href;
      }
      cssUrls.push(cssUrl);
    }

    // Fetch CSS files
    let allCss = '';
    for (const cssUrl of cssUrls.slice(0, 5)) { // Limit to first 5 CSS files
      try {
        const cssResponse = await fetch(cssUrl);
        if (cssResponse.ok) {
          const cssText = await cssResponse.text();
          allCss += cssText + '\\n';
        }
      } catch (error) {
        console.warn(`Failed to fetch CSS: ${cssUrl}`);
      }
    }

    return {
      url,
      css: allCss,
      variables: extractCSSVariables(allCss),
      colors: extractColors(allCss),
      fonts: extractFontFamilies(allCss),
      breakpoints: extractBreakpoints(allCss),
      shadows: extractShadows(allCss),
      radius: extractBorderRadius(allCss),
      motion: extractMotionTokens(allCss)
    };

  } catch (error) {
    console.warn(`Error processing ${url}:`, error.message);
    return null;
  }
}

/**
 * Generate fallback tokens if extraction fails
 */
function generateFallbackTokens() {
  return {
    variables: {
      'primary-color': '#1e40af',
      'secondary-color': '#64748b',
      'success-color': '#059669',
      'warning-color': '#d97706',
      'danger-color': '#dc2626',
      'info-color': '#0284c7'
    },
    colors: [
      '#1e40af', // Blue
      '#64748b', // Gray
      '#059669', // Green
      '#d97706', // Orange
      '#dc2626', // Red
      '#0284c7', // Light blue
      '#ffffff', // White
      '#000000', // Black
      '#f8fafc', // Light gray
      '#1e293b'  // Dark gray
    ],
    fonts: [
      'Inter',
      'system-ui',
      '-apple-system',
      'BlinkMacSystemFont',
      'Segoe UI',
      'Roboto',
      'Arial',
      'sans-serif'
    ],
    breakpoints: ['640px', '768px', '1024px', '1280px', '1536px'],
    shadows: [
      '0 1px 3px 0 rgb(0 0 0 / 0.1)',
      '0 4px 6px -1px rgb(0 0 0 / 0.1)',
      '0 10px 15px -3px rgb(0 0 0 / 0.1)',
      '0 25px 50px -12px rgb(0 0 0 / 0.25)'
    ],
    radius: ['0.125rem', '0.25rem', '0.375rem', '0.5rem', '0.75rem', '1rem'],
    motion: {
      durations: ['150ms', '200ms', '300ms', '500ms'],
      easings: ['cubic-bezier(0.4, 0, 0.2, 1)', 'ease-in-out', 'ease-out']
    }
  };
}

/**
 * Main extraction function
 */
async function extractTokens() {
  console.log('Starting design token extraction...');

  const results = [];

  // Process each URL
  for (const url of REFERENCE_URLS) {
    const result = await processUrl(url);
    if (result) {
      results.push(result);
    }

    // Add delay to be respectful
    await new Promise(resolve => setTimeout(resolve, 1000));
  }

  // Aggregate all tokens
  const aggregated = {
    sources: results.map(r => r.url),
    variables: {},
    colors: new Set(),
    fonts: new Set(),
    breakpoints: new Set(),
    shadows: new Set(),
    radius: new Set(),
    motion: {
      durations: new Set(),
      easings: new Set()
    }
  };

  // Merge results
  for (const result of results) {
    Object.assign(aggregated.variables, result.variables);
    result.colors.forEach(c => aggregated.colors.add(c));
    result.fonts.forEach(f => aggregated.fonts.add(f));
    result.breakpoints.forEach(b => aggregated.breakpoints.add(b));
    result.shadows.forEach(s => aggregated.shadows.add(s));
    result.radius.forEach(r => aggregated.radius.add(r));
    result.motion.durations.forEach(d => aggregated.motion.durations.add(d));
    result.motion.easings.forEach(e => aggregated.motion.easings.add(e));
  }

  // Convert sets back to arrays
  const finalTokens = {
    sources: aggregated.sources,
    variables: aggregated.variables,
    colors: Array.from(aggregated.colors),
    fonts: Array.from(aggregated.fonts),
    breakpoints: Array.from(aggregated.breakpoints).sort((a, b) => {
      const aNum = parseInt(a);
      const bNum = parseInt(b);
      return aNum - bNum;
    }),
    shadows: Array.from(aggregated.shadows),
    radius: Array.from(aggregated.radius),
    motion: {
      durations: Array.from(aggregated.motion.durations),
      easings: Array.from(aggregated.motion.easings)
    },
    extractedAt: new Date().toISOString(),
    note: 'Extracted from public CSS for design system consistency. No copyrighted content included.'
  };

  // Use fallback if extraction yielded minimal results
  if (finalTokens.colors.length < 5 || finalTokens.fonts.length < 2) {
    console.log('Using fallback tokens due to limited extraction results');
    const fallback = generateFallbackTokens();
    Object.assign(finalTokens, fallback);
    finalTokens.usingFallback = true;
  }

  return finalTokens;
}

/**
 * Main execution
 */
async function main() {
  try {
    const tokens = await extractTokens();

    // Ensure temp directory exists
    await mkdir(`${__dirname}/../temp`, { recursive: true });

    // Write tokens to file
    const outputPath = `${__dirname}/../temp/raw-tokens.json`;
    await writeFile(outputPath, JSON.stringify(tokens, null, 2));

    console.log(`\\n✅ Design tokens extracted successfully!`);
    console.log(`📁 Saved to: ${outputPath}`);
    console.log(`🎨 Colors found: ${tokens.colors.length}`);
    console.log(`🔤 Fonts found: ${tokens.fonts.length}`);
    console.log(`📱 Breakpoints found: ${tokens.breakpoints.length}`);
    console.log(`💫 Shadows found: ${tokens.shadows.length}`);
    console.log(`🔄 Radius values found: ${tokens.radius.length}`);

    if (tokens.usingFallback) {
      console.log(`\\n⚠️  Using fallback tokens due to extraction limitations`);
      console.log(`   You can manually update the values in raw-tokens.json`);
    }

  } catch (error) {
    console.error('❌ Error during token extraction:', error);

    // Generate fallback tokens as a safety net
    console.log('🔄 Generating fallback tokens...');
    const fallbackTokens = {
      ...generateFallbackTokens(),
      extractedAt: new Date().toISOString(),
      usingFallback: true,
      note: 'Fallback tokens generated due to extraction failure. Manually update as needed.'
    };

    await mkdir(`${__dirname}/../temp`, { recursive: true });
    await writeFile(
      `${__dirname}/../temp/raw-tokens.json`,
      JSON.stringify(fallbackTokens, null, 2)
    );

    console.log('✅ Fallback tokens generated successfully!');
  }
}

// Run the script
if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}

export { extractTokens, generateFallbackTokens };
