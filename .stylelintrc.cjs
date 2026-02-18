/**
 * Stylelint Configuration - Pusdokkes FE Audit
 * Strict rules to prevent layout shifts, performance issues, and specificity problems
 */

module.exports = {
    extends: ["stylelint-config-standard", "stylelint-config-recommended-scss"],
    plugins: [
        "stylelint-order",
        "stylelint-no-unsupported-browser-features",
        "stylelint-high-performance-animation",
    ],
    rules: {
        // ===== LAYOUT SAFETY RULES =====

        // Warn about global aggressive selectors that modify layout
        "selector-max-universal": [
            0,
            {
                severity: "warning",
            },
        ],
        "selector-max-type": [
            2,
            {
                message:
                    "Avoid too many type selectors (max 2) to prevent specificity issues",
                severity: "warning",
            },
        ],

        // Prevent excessive specificity
        "selector-max-specificity": [
            "0,4,0",
            {
                message:
                    "Selector specificity too high (max 0,4,0). Refactor to use classes.",
                severity: "warning",
            },
        ],

        "selector-max-id": [
            0,
            {
                message:
                    "Avoid ID selectors - use classes instead for better reusability",
                severity: "warning",
            },
        ],

        "selector-max-compound-selectors": [
            4,
            {
                message:
                    "Too many compound selectors (max 4). Consider simplifying or using BEM.",
                severity: "warning",
            },
        ],

        // ===== !important RESTRICTIONS =====

        "declaration-no-important": [
            true,
            {
                message:
                    "Avoid !important except in utility classes. Consider refactoring specificity.",
                severity: "warning",
            },
        ],

        // ===== PERFORMANCE RULES =====

        // Warn about transitions on non-composited properties
        "plugin/no-low-performance-animation-properties": [
            true,
            {
                message:
                    "Avoid animating width/height/top/left. Use transform/opacity instead.",
                severity: "warning",
            },
        ],

        // Browser compatibility
        "plugin/no-unsupported-browser-features": [
            true,
            {
                browsers: ["defaults", "not op_mini all"],
                ignore: ["css-nesting", "css-cascade-layers"],
                severity: "warning",
            },
        ],

        // ===== ORDERING =====

        "order/properties-alphabetical-order": null,
        "order/properties-order": [
            [
                // Positioning
                "position",
                "top",
                "right",
                "bottom",
                "left",
                "z-index",
                "inset",

                // Display & Box Model
                "display",
                "flex",
                "flex-direction",
                "flex-wrap",
                "flex-flow",
                "justify-content",
                "align-items",
                "align-content",
                "gap",
                "grid",
                "grid-template",
                "grid-template-rows",
                "grid-template-columns",
                "grid-area",

                // Box model
                "width",
                "min-width",
                "max-width",
                "height",
                "min-height",
                "max-height",
                "margin",
                "margin-top",
                "margin-right",
                "margin-bottom",
                "margin-left",
                "padding",
                "padding-top",
                "padding-right",
                "padding-bottom",
                "padding-left",

                // Visual
                "color",
                "background",
                "background-color",
                "background-image",
                "border",
                "border-radius",
                "box-shadow",
                "opacity",

                // Typography
                "font-family",
                "font-size",
                "font-weight",
                "line-height",
                "text-align",

                // Others
                "transition",
                "transform",
            ],
            {
                severity: "warning",
                unspecified: "bottomAlphabetical",
            },
        ],

        // ===== CSS LAYERS =====

        "at-rule-empty-line-before": [
            "always",
            {
                except: ["blockless-after-same-name-blockless", "first-nested"],
                ignore: ["after-comment"],
                ignoreAtRules: ["else"],
                severity: "warning",
            },
        ],

        "scss/at-rule-no-unknown": [
            true,
            {
                ignoreAtRules: [
                    "tailwind",
                    "apply",
                    "variants",
                    "responsive",
                    "screen",
                    "layer",
                ],
            },
        ],

        // ===== BEST PRACTICES =====

        "color-hex-length": [
            "short",
            {
                severity: "warning",
            },
        ],
        "color-named": [
            "never",
            {
                severity: "warning",
            },
        ],
        "declaration-block-no-duplicate-properties": [
            true,
            {
                ignore: ["consecutive-duplicates-with-different-values"],
            },
        ],
        "font-family-name-quotes": "always-where-recommended",
        "function-url-quotes": "always",
        "shorthand-property-no-redundant-values": true,
        "value-keyword-case": [
            "lower",
            {
                severity: "warning",
                ignoreKeywords: [
                    "optimizeLegibility",
                    "BlinkMacSystemFont",
                    "SFMono-Regular",
                ],
            },
        ],

        "alpha-value-notation": [
            "percentage",
            {
                severity: "warning",
            },
        ],
        "color-function-notation": [
            "modern",
            {
                severity: "warning",
            },
        ],
        "declaration-block-no-duplicate-custom-properties": [
            true,
            {
                severity: "warning",
            },
        ],
        "declaration-block-no-redundant-longhand-properties": [
            true,
            {
                severity: "warning",
            },
        ],
        "declaration-block-single-line-max-declarations": null,
        "comment-empty-line-before": [
            "always",
            {
                except: ["first-nested"],
                severity: "warning",
            },
        ],
        "rule-empty-line-before": [
            "always-multi-line",
            {
                except: ["first-nested"],
                ignore: ["after-comment"],
                severity: "warning",
            },
        ],
        "media-feature-range-notation": null,
        "no-descending-specificity": [
            true,
            {
                severity: "warning",
            },
        ],
        "no-duplicate-selectors": [
            true,
            {
                severity: "warning",
            },
        ],
        "selector-no-vendor-prefix": [
            true,
            {
                severity: "warning",
            },
        ],
        "selector-not-notation": [
            "complex",
            {
                severity: "warning",
            },
        ],

        // ===== CUSTOM RULES FOR OVERLAY SAFETY =====

        // Allow CSS custom properties (variables)
        "custom-property-empty-line-before": null,
        "custom-property-pattern": [
            "^(pd|theme|color|spacing|radius|shadow|font|motion|tw|text|leading|tracking)-[a-z0-9-]+$",
            {
                message:
                    "Custom properties should follow pattern: pd-*, theme-*, color-*, etc.",
                severity: "warning",
            },
        ],

        "selector-class-pattern": [
            "^([a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:--[a-z0-9-]+)?|[A-Z][A-Za-z0-9]*)$",
            {
                message:
                    "Class selector should be kebab-case (BEM modifier allowed) or trusted external PascalCase class.",
                resolveNestedSelectors: true,
                severity: "warning",
            },
        ],

        // ===== REPORTING =====
    },

    overrides: [
        {
            files: ["public/**/*.css", "styles/**/*.css"],
            rules: {
                "order/properties-order": null,
                "custom-property-pattern": null,
                "alpha-value-notation": null,
                "color-function-notation": null,
                "color-hex-length": null,
                "color-named": null,
                "value-keyword-case": null,
                "rule-empty-line-before": null,
                "comment-empty-line-before": null,
                "declaration-block-no-duplicate-custom-properties": null,
                "declaration-block-no-redundant-longhand-properties": null,
                "selector-max-universal": null,
                "selector-max-type": null,
                "selector-max-specificity": null,
                "selector-not-notation": null,
                "selector-no-vendor-prefix": null,
                "selector-class-pattern": null,
                "no-duplicate-selectors": null,
                "no-descending-specificity": null,
                "media-feature-range-notation": null,
                "at-rule-empty-line-before": null,
                "declaration-no-important": null,
                "plugin/no-unsupported-browser-features": null,
                "plugin/no-low-performance-animation-properties": null,
            },
        },
    ],

    reportNeedlessDisables: false,
    reportInvalidScopeDisables: true,
    reportDescriptionlessDisables: false,

    // Ignore patterns
    ignoreFiles: [
        "node_modules/**",
        "vendor/**",
        "public/build/**",
        "public/hot",
        "**/*.min.css",
        "**/bootstrap*.css",
        "**/tailwind*.css",
    ],
};
