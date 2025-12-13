# Dokpol Style - Design System Monorepo

Production-ready design system and applications inspired by pusdokkes.polri.go.id visual patterns and UX.

## Architecture

This monorepo contains:

- **apps/web**: Next.js 14 (App Router) frontend application
- **apps/api**: NestJS REST API backend
- **packages/ui**: Shared design system components
- **packages/config**: Shared ESLint, TypeScript, and Prettier configs

## Features

- 🎨 Complete design system with Light/Dark/System theme support
- ♿ WCAG 2.2 AA accessibility compliant
- 🗺️ Facility map with radius search (10/50/100 km)
- 📰 News listing with categories and relative dates
- 🔐 OIDC SSO authentication ready
- 📱 Responsive and mobile-first
- ⚡ High performance (LCP < 2.5s)
- 🧪 Tested with Playwright e2e tests
- 📚 Storybook documentation

## Getting Started

### Prerequisites

- Node.js >= 20.0.0
- pnpm >= 9.0.0

### Installation

```bash
pnpm install
```

### Development

```bash
# Run all apps in dev mode
pnpm dev

# Run specific app
pnpm --filter web dev
pnpm --filter api dev
```

### Build

```bash
pnpm build
```

### Testing

```bash
# Run all tests
pnpm test

# Run e2e tests
pnpm test:e2e

# Run accessibility audit
pnpm audit:a11y
```

### Storybook

```bash
pnpm storybook
```

## Documentation

See [docs/](./docs) for detailed documentation:

- [Design System](./docs/design-system.md)
- [Architecture](./docs/architecture.md)
- [Accessibility](./docs/accessibility.md)
- [Deployment](./docs/deployment.md)

## Compliance

This project follows all compliance requirements:
- ✅ No copyrighted assets from reference site
- ✅ Custom placeholder content
- ✅ Inspired visual patterns only
- ✅ Original implementation

## License

MIT
