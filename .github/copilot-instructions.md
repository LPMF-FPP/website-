# Copilot Instructions for This Codebase

## Big Picture Architecture
- **Monorepo**: Contains Laravel (PHP) backend, frontend assets, and a separate `dokpol-style` design system (Next.js/NestJS/Storybook/Playwright).
- **Frontend Audit System**: Automated audits for CSS, JS, accessibility, and performance. All reports output to `report/`.
- **Design System**: Shared UI components and configs in `dokpol-style/packages/ui` and `dokpol-style/packages/config`.
- **App Structure**: Main Laravel app in `app/`, with supporting config, routes, resources, and public assets. See `patcher/` for deployment and audit documentation.

## Developer Workflows
- **Install dependencies**: `npm install` (root), `pnpm install` (dokpol-style)
- **Run Laravel server**: `php artisan serve` (required for audits)
- **Run all audits**: `npm run audit:all` (see `report/README.md` for details)
- **Run critical audits**: `npm run audit:critical` (CI, pre-commit)
- **Run design system dev**: `pnpm dev` (dokpol-style)
- **Build**: `pnpm build` (dokpol-style)
- **Test**: `pnpm test` (dokpol-style), `npm run test` (root)
- **Storybook**: `pnpm storybook` (dokpol-style)

## Project-Specific Conventions
- **Safe Mode v2**: Overlay CSS (pd-*.css) must not use layout properties. Violations fail audits (`audit:guard`).
- **CSS/JS Linting**: Strict rules in `.stylelintrc.cjs` and `.eslintrc.cjs`. Fix with `npx stylelint ... --fix` or `npx eslint ... --fix`.
- **Audit URLs**: Set via `AUDIT_URLS` env or `.env` file for a11y/coverage scans.
- **Reports**: All audit results in `report/` as Markdown/JSON/HTML. See `report/README.md` for interpreting results.
- **CI/CD**: Audits run on push/PR via GitHub Actions. See `report/README.md` for YAML example.
- **Pre-commit**: Run `npm run audit:guard` before commit (see `report/README.md`).

## Integration Points & Dependencies
- **Node.js**: Required for all audits and design system work (Node 20+, pnpm 9+ for dokpol-style).
- **Puppeteer**: Downloads Chromium for coverage/a11y audits (see troubleshooting in `report/README.md`).
- **Lighthouse**: Performance/SEO audits, config in `lighthouserc.json`.
- **axe-core**: Accessibility scanning.
- **Playwright**: e2e tests for design system.

## Key Files & Directories
- `report/README.md`: Full audit system guide and troubleshooting
- `dokpol-style/README.md`: Design system architecture and workflows
- `patcher/`: Deployment, audit, and design documentation
- `app/`, `resources/`, `routes/`, `public/`: Laravel app core
- `dokpol-style/apps/`, `dokpol-style/packages/`: Design system apps and shared packages

## Examples
- To run all audits before deploy: `npm run audit:critical`
- To develop the design system: `cd dokpol-style && pnpm dev`
- To fix CSS lint errors: `npx stylelint "resources/**/*.css" --fix`
- To run accessibility audit: `npm run audit:a11y` (Laravel server must be running)

---

For more, see `report/README.md`, `dokpol-style/README.md`, and docs in `patcher/`.
