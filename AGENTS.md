# Project Workflow Rules

You are strictly required to follow these workflow rules for every task.

## 0. Agent Roles & Responsibilities

### Core Agents
- **Sisyphus** (`google/antigravity-claude-opus-4-5-thinking-high`) - Primary coding agent for implementation, refactoring, debugging with deep reasoning
- **Planner-Sisyphus** (`openai/gpt-5.2-codex`) - Strategic planning, task breakdown, architectural decisions
- **oracle** (`openai/gpt-5.2-codex`) - Problem-solving, Q&A, technical reasoning, code review
- **librarian** (`google/antigravity-claude-sonnet-4-5`) - Documentation research, API references, library usage

### Specialized Agents
- **frontend-ui-ux-engineer** (`google/antigravity-gemini-3-pro-high`) - UI/UX design, frontend implementation, accessibility
- **document-writer** (`openai/gpt-5.2-codex`) - Writing documentation, summaries, walkthroughs, changelog updates
- **multimodal-looker** (`google/antigravity-claude-sonnet-4-5`) - Image analysis, visual debugging, design reviews
- **explore** (`openai/gpt-5.2-codex`) - Fast codebase exploration, quick searches, file discovery

### MCP Integrations
- **shadcn** - UI component generation and management
- **supabase** - Database operations, authentication, storage

## 1. Project Snapshot

**Type**: Laravel 12 monorepo with integrated design system  
**Stack**: PHP 8.3+, Laravel 12, Blade + Alpine.js + Tailwind CSS, Node.js 20+  
**Structure**: Main app + dokpol-style package + frontend audit system  
**Docs**: Sub-packages reference `patcher/` and `report/README.md` for detailed guides

## 2. Root Setup Commands

```bash
# Install dependencies
composer install && npm install

# Development server (required for audits)
php artisan serve

# Build frontend
npm run build

# Run all audits
npm run audit:all

# Run critical audits (CI, pre-commit)
npm run audit:critical

# Run tests
npm run test
```

## 3. Universal Conventions

- **Code Style**: PHP PSR-12, ESLint + Stylelint for JS/CSS
- **CSS Rules**: Overlay CSS (pd-*.css) MUST NOT use layout properties (enforced by `audit:guard`)
- **Commits**: Descriptive messages (e.g., `feat: implement login page UI`, `fix: resolve jwt token error`)
- **Documentation**: NEVER create new .md files - always update `WALKTHROUGH.md`
- **Audit URLs**: Set via `AUDIT_URLS` env or `.env` file for a11y/coverage scans
- **Pre-commit**: Run `npm run audit:guard` before every commit

## 4. Security & Secrets

- **Never commit**: API keys, tokens, passwords, `.env` files
- **Secrets location**: `.env` (never committed), use `.env.example` as template
- **PII handling**: Follow Laravel's encryption helpers for sensitive data
- **Database**: Never expose credentials in error logs

## 5. JIT Index - Directory Map

### Main Application Structure
- **Laravel App**: `app/` → Models, Controllers, Services, Repositories  
  - Controllers: `app/Http/Controllers/**`
  - Models: `app/Models/**`
  - Services: `app/Services/**`
  - Helpers: `app/helpers.php`

- **Frontend Resources**: `resources/`  
  - Views: `resources/views/**/*.blade.php`
  - CSS: `resources/css/**`
  - JS: `resources/js/**`
  - Tokens: `resources/design-tokens.example.json`

- **Routes**: `routes/`  
  - Web: `routes/web.php`
  - API: `routes/api.php`
  - Auth: `routes/auth.php`

- **Frontend Styles**: `styles/`  
  - Base: `styles/base.css`
  - Components: `styles/components.css`
  - Safe overlays: `styles/pd-*.css` (strict layout rules)
  - Tokens: `styles/tokens.css`

- **Design System**: `dokpol-style/` → [see dokpol-style/README.md]  
  - Apps: `dokpol-style/apps/**`
  - Packages: `dokpol-style/packages/**`

- **Audit System**: `scripts/audit/` + `report/` → [see report/README.md]  
  - CSS/JS linting, accessibility, coverage, performance audits
  - All reports output to `report/`

- **Documentation**: `patcher/` → Deployment, audit, and design docs

### Quick Find Commands

```bash
# Search for a function
rg -n "function functionName" app/ resources/

# Find a Blade component
rg -n "<x-" resources/views/

# Find a controller method
rg -n "public function" app/Http/Controllers/

# Find a route
rg -n "Route::" routes/

# Find CSS class usage
rg -n "className" resources/views/ resources/js/

# Find audit configuration
rg -n "audit:" package.json scripts/audit/

# List all migrations
ls -la database/migrations/

# Find test files
find tests/ -name "*.php"
```

## 6. Master Plan (todos.md)

- **Initiation**: If `todos.md` does not exist, delegate to `Planner-Sisyphus` to CREATE IT immediately.
- **Structure**: Comprehensive list of tasks from development start to finish.
- **Status**: Use `[ ]` for pending and `[x]` for completed tasks.
- **Update**: MUST mark tasks as `[x]` immediately after completion.
- **Agent Delegation**: 
  - Complex planning → `Planner-Sisyphus`
  - Task prioritization → `oracle`

## 7. Documentation & Change Tracking (WALKTHROUGH.md)

- **Purpose**: Central documentation hub for all project changes, features, and fixes.
- **Rule**: WALKTHROUGH.md already exists - NEVER create new .md files for documentation.
- **Format**: Append changes to relevant sections with proper markdown hierarchy.
- **Update Protocol**:
  1. After completing meaningful tasks → Update WALKTHROUGH.md with details
  2. Document new features, fixes, and architectural decisions
  3. Include code examples and explanations where relevant
  4. Use proper date stamps: `Updated on YYYY-MM-DD`
- **Agent Delegation**:
  - Documentation writing → `document-writer`
  - Technical summaries → `oracle`
  - Code examples → `Sisyphus`

## 8. Changelog Page Updates

- **After completing work**: Update the `/changelogs` page in the Laravel application
- **Location**: `resources/views/` or appropriate route
- **Content**: Generate user-facing changelog entries from WALKTHROUGH.md updates
- **Agent Delegation**: `document-writer`

## 9. Version Control (Git)

- **Commit**: After every logical unit of work (e.g., after completing a single item in `todos.md`).
- **Message**: Descriptive (e.g., `feat: implement login page UI`, `fix: resolve jwt token error`).
- **History**: Ensure git history tells a clear story of the development process.

## 10. Execution Protocol

### Pre-Coding Phase
1. Check `todos.md` and overwrite if new task
2. For unclear requirements → consult `oracle`
3. For architecture decisions → consult `Planner-Sisyphus`
4. For API/library questions → consult `librarian`
5. For visual/design questions → consult `multimodal-looker`

### Coding Phase
1. **Backend/Logic** → `Sisyphus`
2. **Frontend/UI** → `frontend-ui-ux-engineer`
3. **File discovery** → `explore`
4. **Visual debugging** → `multimodal-looker`
5. **UI Components** → Use `shadcn` MCP
6. **Database operations** → Use `supabase` MCP

### Post-Coding Phase
1. Run tests/verification
2. Code review → `oracle`
3. Update `WALKTHROUGH.md` → `document-writer`
4. Update `/changelogs` page → `document-writer`
5. Mark item in `todos.md` as `[x]`
6. Git add & commit → `Sisyphus`

## 11. Agent Selection Matrix

| Task Type | Primary Agent | Support Agent |
|-----------|---------------|---------------|
| Code implementation | Sisyphus | oracle |
| Planning & architecture | Planner-Sisyphus | oracle |
| Frontend/UI work | frontend-ui-ux-engineer | multimodal-looker |
| Documentation | document-writer | librarian |
| Research/learning | librarian | oracle |
| Debugging | Sisyphus | oracle |
| Visual analysis | multimodal-looker | frontend-ui-ux-engineer |
| Quick exploration | explore | Sisyphus |
| Database work | Sisyphus + supabase MCP | oracle |
| UI components | frontend-ui-ux-engineer + shadcn MCP | multimodal-looker |
| Changelog updates | document-writer | Sisyphus |

## 12. Collaboration Protocol

- **Always start with Planner-Sisyphus** for new features or major changes
- **Use oracle** when stuck or need validation
- **Delegate to specialists** (frontend-ui-ux-engineer, document-writer) for domain-specific work
- **Use explore** for quick fact-finding before deep implementation
- **Leverage MCP servers** (shadcn, supabase) for external integrations
- **Sisyphus handles** the final implementation and git commits
- **document-writer creates summaries** after significant changes
- **Never create standalone .md files** - always update WALKTHROUGH.md

## 13. Definition of Done

Before any PR is ready:

- [ ] All tests pass: `npm run test`
- [ ] Critical audits pass: `npm run audit:critical`
- [ ] Code linted: `npx eslint ... --fix` and `npx stylelint ... --fix`
- [ ] `WALKTHROUGH.md` updated with changes
- [ ] `/changelogs` page updated
- [ ] `todos.md` items marked `[x]`
- [ ] Git commit with descriptive message

## 14. Complete Task Workflow

```
1. START → Check todos.md
2. PLAN → Planner-Sisyphus (if complex)
3. RESEARCH → librarian/oracle (if needed)
4. IMPLEMENT → Sisyphus/frontend-ui-ux-engineer
5. TEST → Run verification
6. REVIEW → oracle
7. DOCUMENT → Update WALKTHROUGH.md (document-writer)
8. CHANGELOG → Update /changelogs page (document-writer)
9. TODO → Mark [x] in todos.md
10. COMMIT → Git commit (Sisyphus)
11. END
```

## 15. Common Gotchas

- **CSS Safe Mode**: pd-*.css files CANNOT use layout properties (enforced by `audit:guard`)
- **Laravel Server**: Must be running (`php artisan serve`) for a11y/coverage audits
- **Puppeteer**: Downloads Chromium automatically; see `report/README.md` for troubleshooting
- **Audit URLs**: Configure in `.env` or `AUDIT_URLS` environment variable
- **Node Version**: Requires Node.js 20+ for audit system

## 16. Pre-PR Checks

```bash
# Single command to run before creating PR
npm run audit:critical && npm run test && npm run build
```
