/**
 * ESLint Configuration - Pusdokkes FE Audit
 * Prevent DOM thrashing, memory leaks, and accessibility issues
 */

module.exports = {
  root: true,
  env: {
    browser: true,
    es2022: true,
    node: true
  },
  parserOptions: {
    ecmaVersion: 2022,
    sourceType: 'module',
    ecmaFeatures: {
      jsx: true
    }
  },
  extends: [
    'eslint:recommended'
  ],
  plugins: [
    'import',
    'unicorn'
  ],
  rules: {
    // ===== PERFORMANCE & DOM THRASHING PREVENTION =====
    
    // Prevent layout thrashing (read-write-read-write)
    'no-restricted-properties': [
      'error',
      {
        object: 'window',
        property: 'innerWidth',
        message: 'Cache window.innerWidth to prevent layout thrashing'
      },
      {
        object: 'window',
        property: 'innerHeight',
        message: 'Cache window.innerHeight to prevent layout thrashing'
      },
      {
        object: 'document',
        property: 'body',
        message: 'Cache document.body to prevent repeated lookups'
      }
    ],
    
    // Warn about potential memory leaks
    'no-unused-vars': ['warn', {
      vars: 'all',
      args: 'after-used',
      ignoreRestSiblings: true,
      argsIgnorePattern: '^_'
    }],
    
    // ===== SECURITY =====
    
    'no-eval': 'error',
    'no-implied-eval': 'error',
    'no-new-func': 'error',
    'no-script-url': 'error',
    
    // ===== EVENT LISTENER MANAGEMENT =====
    
    'no-restricted-syntax': [
      'warn',
      {
        selector: "CallExpression[callee.property.name='addEventListener']:not(:has(CallExpression[callee.property.name='removeEventListener']))",
        message: 'addEventListener without removeEventListener may cause memory leaks. Consider cleanup in unmount/destroy.'
      }
    ],
    
    // ===== BEST PRACTICES =====
    
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'no-debugger': 'warn',
    'no-alert': 'warn',
    'eqeqeq': ['error', 'always'],
    'curly': ['error', 'all'],
    'no-var': 'error',
    'prefer-const': 'error',
    'prefer-arrow-callback': 'warn',
    'prefer-template': 'warn',
    
    // ===== IMPORTS =====
    
    'import/no-unresolved': 'off', // Laravel Mix/Vite handles this
    'import/named': 'off',
    'import/no-duplicates': 'error',
    'import/order': ['warn', {
      'groups': [
        'builtin',
        'external',
        'internal',
        'parent',
        'sibling',
        'index'
      ],
      'newlines-between': 'always'
    }],
    
    // ===== UNICORN RULES (CODE QUALITY) =====
    
    'unicorn/prefer-query-selector': 'off',
    'unicorn/prefer-add-event-listener': 'warn',
    'unicorn/prefer-dom-node-append': 'warn',
    'unicorn/prefer-dom-node-remove': 'warn',
    'unicorn/no-array-for-each': 'off',
    'unicorn/prevent-abbreviations': 'off'
  },
  overrides: [
    // ===== VUE FILES =====
    {
      files: ['*.vue'],
      parser: 'vue-eslint-parser',
      extends: [
        'plugin:vue/vue3-recommended'
      ],
      rules: {
        'vue/multi-word-component-names': 'warn',
        'vue/no-v-html': 'warn',
        'vue/require-default-prop': 'warn',
        'vue/require-prop-types': 'warn'
      }
    },
    
    // ===== JSX/TSX FILES =====
    {
      files: ['*.jsx', '*.tsx'],
      extends: [
        'plugin:jsx-a11y/recommended'
      ],
      plugins: ['jsx-a11y'],
      rules: {
        // Accessibility
        'jsx-a11y/alt-text': 'error',
        'jsx-a11y/anchor-has-content': 'error',
        'jsx-a11y/anchor-is-valid': 'warn',
        'jsx-a11y/aria-props': 'error',
        'jsx-a11y/aria-proptypes': 'error',
        'jsx-a11y/aria-unsupported-elements': 'error',
        'jsx-a11y/click-events-have-key-events': 'warn',
        'jsx-a11y/heading-has-content': 'error',
        'jsx-a11y/img-redundant-alt': 'warn',
        'jsx-a11y/label-has-associated-control': 'warn',
        'jsx-a11y/no-autofocus': 'warn',
        'jsx-a11y/no-static-element-interactions': 'warn'
      }
    },
    
    // ===== TYPESCRIPT FILES =====
    {
      files: ['*.ts', '*.tsx'],
      parser: '@typescript-eslint/parser',
      plugins: ['@typescript-eslint'],
      extends: [
        'plugin:@typescript-eslint/recommended'
      ],
      rules: {
        '@typescript-eslint/no-unused-vars': ['warn', {
          argsIgnorePattern: '^_'
        }],
        '@typescript-eslint/no-explicit-any': 'warn',
        '@typescript-eslint/explicit-function-return-type': 'off',
        '@typescript-eslint/explicit-module-boundary-types': 'off'
      }
    }
  ],
  
  ignorePatterns: [
    'node_modules/',
    'vendor/',
    'public/build/',
    'public/hot',
    '*.min.js',
    'bootstrap.js'
  ]
};
