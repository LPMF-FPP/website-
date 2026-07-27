const js = require("@eslint/js");
const globals = require("globals");
const unicorn = require("eslint-plugin-unicorn").default;

module.exports = [
    {
        ignores: [
            "node_modules/",
            "vendor/",
            "public/build/",
            "public/hot",
            "**/*.min.js",
            "resources/js/bootstrap.js",
        ],
    },
    {
        files: ["resources/js/**/*.js"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        plugins: {
            unicorn,
        },
        rules: {
            ...js.configs.recommended.rules,
            "no-restricted-properties": [
                "error",
                {
                    object: "window",
                    property: "innerWidth",
                    message: "Cache window.innerWidth to prevent layout thrashing",
                },
                {
                    object: "window",
                    property: "innerHeight",
                    message: "Cache window.innerHeight to prevent layout thrashing",
                },
                {
                    object: "document",
                    property: "body",
                    message: "Cache document.body to prevent repeated lookups",
                },
            ],
            "no-unused-vars": [
                "warn",
                {
                    vars: "all",
                    args: "after-used",
                    ignoreRestSiblings: true,
                    argsIgnorePattern: "^_",
                },
            ],
            "no-eval": "error",
            "no-implied-eval": "error",
            "no-new-func": "error",
            "no-script-url": "error",
            "no-restricted-syntax": "off",
            "no-console": ["warn", { allow: ["warn", "error"] }],
            "no-debugger": "warn",
            "no-alert": "off",
            eqeqeq: ["error", "always"],
            curly: ["error", "all"],
            "no-var": "error",
            "prefer-const": "error",
            "prefer-arrow-callback": "warn",
            "prefer-template": "warn",
            "no-duplicate-imports": "error",
            "unicorn/prefer-query-selector": "off",
            "unicorn/prefer-add-event-listener": "warn",
            "unicorn/prefer-dom-node-append": "warn",
            "unicorn/prefer-dom-node-remove": "warn",
            "unicorn/no-array-for-each": "off",
            "unicorn/prevent-abbreviations": "off",
        },
    },
];
