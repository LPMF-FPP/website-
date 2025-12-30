# Copilot Instructions for This Codebase

## Big Picture Architecture
- **Laravel App**: Contains Laravel 12 (PHP 8.3+) backend with Blade + Alpine.js + Tailwind CSS frontend.
- **Frontend Audit System**: Automated audits for CSS, JS, accessibility, and performance. All reports output to `report/`.
- **App Structure**: Main Laravel app in `app/`, with supporting config, routes, resources, and public assets. See `patcher/` for deployment and audit documentation.

## Developer Workflows
- **Install dependencies**: `npm install`
- **Run Laravel server**: `php artisan serve` (required for audits)
- **Run all audits**: `npm run audit:all` (see `report/README.md` for details)
- **Run critical audits**: `npm run audit:critical` (CI, pre-commit)
- **Build frontend**: `npm run build`
- **Test**: `npm run test`

## Project-Specific Conventions
- **Safe Mode v2**: Overlay CSS (pd-*.css) must not use layout properties. Violations fail audits (`audit:guard`).
- **CSS/JS Linting**: Strict rules in `.stylelintrc.cjs` and `.eslintrc.cjs`. Fix with `npx stylelint ... --fix` or `npx eslint ... --fix`.
- **Audit URLs**: Set via `AUDIT_URLS` env or `.env` file for a11y/coverage scans.
- **Reports**: All audit results in `report/` as Markdown/JSON/HTML. See `report/README.md` for interpreting results.
- **CI/CD**: Audits run on push/PR via GitHub Actions. See `report/README.md` for YAML example.
- **Pre-commit**: Run `npm run audit:guard` before commit (see `report/README.md`).

## Integration Points & Dependencies
- **Node.js**: Required for audits (Node 20+).
- **Puppeteer**: Downloads Chromium for coverage/a11y audits (see troubleshooting in `report/README.md`).
- **Lighthouse**: Performance/SEO audits, config in `lighthouserc.json`.
- **axe-core**: Accessibility scanning.
- **DomPDF**: PDF generation (barryvdh/laravel-dompdf ^3.1).

## Key Files & Directories
- `report/README.md`: Full audit system guide and troubleshooting
- `patcher/`: Deployment, audit, and design documentation
- `app/`, `resources/`, `routes/`, `public/`: Laravel app core

## Examples
- To run all audits before deploy: `npm run audit:critical`
- To fix CSS lint errors: `npx stylelint "resources/**/*.css" --fix`
- To run accessibility audit: `npm run audit:a11y` (Laravel server must be running)

---

## Documentation Rules (IMPORTANT)

**⚠️ DO NOT CREATE NEW .md FILES FOR DOCUMENTATION**

All project documentation is consolidated in `WALKTHROUGH.md`. When completing a coding task:

1. **DO NOT** create new `.md` files to document changes, fixes, or features
2. **INSTEAD**, append or update the relevant section in `WALKTHROUGH.md`
3. If you need to document a new feature or fix:
   - Find the appropriate section in `WALKTHROUGH.md`
   - Add your documentation there
   - Use proper markdown heading hierarchy

### How to Update WALKTHROUGH.md

```markdown
## 📄 [Category]/[Feature Name]

```
Source: Updated on YYYY-MM-DD
```

[Your documentation content here]
```

### Exception Files
These standalone .md files are allowed to exist separately:
- `README.md` (root, for GitHub)
- `PRE_PULL_CHECKLIST.md`
- `PRE_PUSH_CHECKLIST.md`
- `report/README.md`
- `.github/copilot-instructions.md`

---

For more, see `report/README.md` and docs in `patcher/`.
