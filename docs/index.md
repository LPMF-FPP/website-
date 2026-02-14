# LPMF LIMS - Project Documentation Index

## Project Overview

- **Type:** Monolith (single-part)
- **Project Type:** Web application (`project_type_id: web`)
- **Primary Language:** PHP 8.2+
- **Framework:** Laravel 12
- **Database:** PostgreSQL
- **Frontend:** Blade + Alpine.js + Tailwind + Vite

## Quick Reference

- **Codebase Scale:** 5,229 scanned files, 2,296,478 scanned lines
- **Core Metrics:** 1,001 PHP files, 205 Blade files, 64 models, 87 controllers, 116 migrations
- **Route Surface:** 359 literal route declarations (`api.php` + `web.php`)
- **Architecture Pattern:** Layered Laravel monolith with service-oriented modules

## Generated Documentation

- [Project Overview](./project-overview.md)
- [Architecture](./architecture.md)
- [Source Tree Analysis](./source-tree-analysis.md)
- [API Contracts](./api-contracts.md)
- [Data Models](./data-models.md)
- [Component Inventory](./component-inventory.md)
- [Development Guide](./development-guide.md)
- [Deployment Guide](./deployment-guide.md)

## Existing Documentation (Repository)

- [AGENTS.md](../AGENTS.md) - Codebase rules, standards, and domain constraints
- [WALKTHROUGH.md](../WALKTHROUGH.md) - Ongoing implementation history and project walkthrough
- [RAMS_UI_GUIDELINES.md](../RAMS_UI_GUIDELINES.md) - UI/UX and accessibility standards
- [VERCEL_GUIDELINES.md](../VERCEL_GUIDELINES.md) - Design and frontend principles
- [report/README.md](../report/README.md) - Frontend audit system guide
- [tests/Load/README.md](../tests/Load/README.md) - k6 load-testing guide
- [.github/copilot-instructions.md](../.github/copilot-instructions.md) - Copilot context and coding guidance
- [ALPINE_JS_PATTERNS.md](./ALPINE_JS_PATTERNS.md) - Alpine interaction and implementation patterns

## Getting Started for AI-Assisted Work

1. Start with [Project Overview](./project-overview.md) and [Architecture](./architecture.md)
2. Use [Source Tree Analysis](./source-tree-analysis.md) to locate implementation targets quickly
3. Validate integration impact through [API Contracts](./api-contracts.md) and [Data Models](./data-models.md)
4. Reuse existing UI patterns from [Component Inventory](./component-inventory.md)
5. Follow run/test/quality steps in [Development Guide](./development-guide.md)

## Update Workflow

- Source of truth for generated state: `docs/project-scan-report.json`
- To refresh this documentation set, re-run BMAD `document-project` workflow
