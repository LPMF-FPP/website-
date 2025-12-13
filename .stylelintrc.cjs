/**
 * Stylelint Configuration - Pusdokkes FE Audit
 * Strict rules to prevent layout shifts, performance issues, and specificity problems
 */

module.exports = {
  extends: [
    'stylelint-config-standard',
    'stylelint-config-recommended-scss'
  ],
  plugins: [
    'stylelint-order',
    'stylelint-no-unsupported-browser-features',
    'stylelint-high-performance-animation'
  ],
  rules: {
    // ===== LAYOUT SAFETY RULES =====
    
    // Warn about global aggressive selectors that modify layout
    'selector-max-universal': 0,
    'selector-max-type': [2, {
      message: 'Avoid too many type selectors (max 2) to prevent specificity issues'
    }],
    
    // Prevent excessive specificity
    'selector-max-specificity': ['0,4,0', {
      message: 'Selector specificity too high (max 0,4,0). Refactor to use classes.'
    }],
    
    'selector-max-id': [0, {
      message: 'Avoid ID selectors - use classes instead for better reusability'
    }],
    
    'selector-max-compound-selectors': [4, {
      message: 'Too many compound selectors (max 4). Consider simplifying or using BEM.'
    }],
    
    // ===== !important RESTRICTIONS =====
    
    'declaration-no-important': [true, {
      message: 'Avoid !important except in utility classes. Consider refactoring specificity.',
      severity: 'warning'
    }],
    
    // ===== PERFORMANCE RULES =====
    
    // Warn about transitions on non-composited properties
    'plugin/no-low-performance-animation-properties': [true, {
      message: 'Avoid animating width/height/top/left. Use transform/opacity instead.',
      severity: 'warning'
    }],
    
    // Browser compatibility
    'plugin/no-unsupported-browser-features': [true, {
      browsers: ['> 1%', 'last 2 versions', 'not dead'],
      ignore: ['css-nesting', 'css-cascade-layers'],
      severity: 'warning'
    }],
    
    // ===== ORDERING =====
    
    'order/properties-alphabetical-order': null,
    'order/properties-order': [
      [
        // Positioning
        'position',
        'top',
        'right',
        'bottom',
        'left',
        'z-index',
        'inset',
        
        // Display & Box Model
        'display',
        'flex',
        'flex-direction',
        'flex-wrap',
        'flex-flow',
        'justify-content',
        'align-items',
        'align-content',
        'gap',
        'grid',
        'grid-template',
        'grid-template-rows',
        'grid-template-columns',
        'grid-area',
        
        // Box model
        'width',
        'min-width',
        'max-width',
        'height',
        'min-height',
        'max-height',
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        
        // Visual
        'color',
        'background',
        'background-color',
        'background-image',
        'border',
        'border-radius',
        'box-shadow',
        'opacity',
        
        // Typography
        'font-family',
        'font-size',
        'font-weight',
        'line-height',
        'text-align',
        
        // Others
        'transition',
        'transform'
      ],
      {
        severity: 'warning',
        unspecified: 'bottomAlphabetical'
      }
    ],
    
    // ===== CSS LAYERS =====
    
    'at-rule-empty-line-before': ['always', {
      except: ['blockless-after-same-name-blockless', 'first-nested'],
      ignore: ['after-comment'],
      ignoreAtRules: ['else']
    }],
    
    // ===== BEST PRACTICES =====
    
    'color-hex-length': 'short',
    'color-named': 'never',
    'declaration-block-no-duplicate-properties': [true, {
      ignore: ['consecutive-duplicates-with-different-values']
    }],
    'font-family-name-quotes': 'always-where-recommended',
    'function-url-quotes': 'always',
    'shorthand-property-no-redundant-values': true,
    'value-keyword-case': 'lower',
    
    // ===== CUSTOM RULES FOR OVERLAY SAFETY =====
    
    // Allow CSS custom properties (variables)
    'custom-property-empty-line-before': null,
    'custom-property-pattern': [
      '^(pd|theme|color|spacing|radius|shadow|font|motion)-[a-z0-9-]+$',
      {
        message: 'Custom properties should follow pattern: pd-*, theme-*, color-*, etc.',
        severity: 'warning'
      }
    ],
    
    // ===== REPORTING =====
    
    'report-needless-disables': true,
    'report-invalid-scope-disables': true,
    'report-descriptionless-disables': true
  },
  
  // Ignore patterns
  ignoreFiles: [
    'node_modules/**',
    'vendor/**',
    'public/build/**',
    'public/hot',
    '**/*.min.css',
    '**/bootstrap*.css',
    '**/tailwind*.css'
  ]
};
