#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
    printf 'Usage: %s <ssh-host> <deploy-path> [git-ref]\n' "$0"
    printf 'Example: %s 192.168.1.25 /var/www/lis origin/release\n' "$0"
    exit 1
fi

host="$1"
deploy_path="$2"
git_ref="${3:-origin/release}"

repo_root="$(git rev-parse --show-toplevel)"
exclude_file="${repo_root}/scripts/deploy-artifact.exclude"

if [ ! -f "$exclude_file" ]; then
    printf 'Exclude file not found: %s\n' "$exclude_file"
    exit 1
fi

if ! git -C "$repo_root" rev-parse --verify "$git_ref" >/dev/null 2>&1; then
    printf 'Git ref not found: %s\n' "$git_ref"
    exit 1
fi

tmp_dir="$(mktemp -d)"
cleanup() {
    rm -rf "$tmp_dir"
}
trap cleanup EXIT

git -C "$repo_root" archive --format=tar "$git_ref" | tar -xf - -C "$tmp_dir"

ssh "$host" "mkdir -p \"$deploy_path\""

rsync -rlz --delete \
    --exclude-from="$exclude_file" \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='storage/***' \
    --exclude='vendor/***' \
    "$tmp_dir/" "$host:$deploy_path/"

ssh "$host" "cd \"$deploy_path\" && rm -rf .git .github .vscode .intelephense .opencode .ruff_cache .serena .uv-cache .worktrees _bmad _bmad-output docs output report temp tests && rm -f AGENTS.md CODE_REVIEW.md RAMS_UI_GUIDELINES.md VERCEL_GUIDELINES.md WALKTHROUGH.md qmh-living-system-tech-spec.md todos.md .editorconfig .eslintrc.cjs .stylelintrc.cjs eslint.config.cjs phpunit.xml phpunit.dusk.xml .phpunit.result.cache .env.testing .env.testing.example .env.dusk.local .env.dusk.testing lighthouserc.json .phpstorm.meta.php _ide_helper.php check_docs.php check_all_docs.php fix_numbering.php landing-page-lpmf.html role"

ssh "$host" "cd \"$deploy_path\" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && php artisan migrate --force && export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && npm ci && npm run build && rm -rf node_modules && php artisan optimize"

printf 'Deploy artifact selesai: %s (%s)\n' "$host:$deploy_path" "$git_ref"
