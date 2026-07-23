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
maintenance_mode=0

# ── Ensure host key is trusted (avoids "Host key verification failed") ──
ensure_known_host() {
    local hostname="${1%%@*}"
    local hostaddr="${1##*@}"
    mkdir -p "$HOME/.ssh"
    ssh-keygen -f "$HOME/.ssh/known_hosts" -R "$hostaddr" 2>/dev/null || true
    ssh-keygen -f "$HOME/.ssh/known_hosts" -R "$hostname" 2>/dev/null || true
    ssh-keyscan -H "$hostaddr" >> "$HOME/.ssh/known_hosts" 2>/dev/null
}
ensure_known_host "$host"

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
    if [ "${maintenance_mode:-0}" -eq 1 ]; then
        ssh "$host" "if [ -f \"$deploy_path/artisan\" ]; then cd \"$deploy_path\" && php artisan up || true; fi" >/dev/null 2>&1 || true
    fi

    rm -rf "$tmp_dir"
}
trap cleanup EXIT

git -C "$repo_root" archive --format=tar "$git_ref" | tar -xf - -C "$tmp_dir"

ssh "$host" "mkdir -p \"$deploy_path\""

ssh "$host" "if [ -f \"$deploy_path/artisan\" ]; then cd \"$deploy_path\" && php artisan down --retry=60; fi"
maintenance_mode=1

rsync -rlz --delete --force \
    --exclude-from="$exclude_file" \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='storage/***' \
    --exclude='vendor/***' \
    "$tmp_dir/" "$host:$deploy_path/"

ssh "$host" "cd \"$deploy_path\" && rm -rf .git .github .vscode .intelephense .opencode .ruff_cache .serena .uv-cache .worktrees _bmad _bmad-output docs output report temp tests dokpol-style && rm -f AGENTS.md CODE_REVIEW.md RAMS_UI_GUIDELINES.md VERCEL_GUIDELINES.md qmh-living-system-tech-spec.md todos.md .editorconfig .eslintrc.cjs .stylelintrc.cjs eslint.config.cjs phpunit.xml phpunit.dusk.xml .phpunit.result.cache .env.testing .env.testing.example .env.dusk.local .env.dusk.testing lighthouserc.json .phpstorm.meta.php _ide_helper.php check_docs.php check_all_docs.php fix_numbering.php landing-page-lpmf.html role"

ssh "$host" "cd \"$deploy_path\" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && php artisan migrate --force && export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && npm ci && npm run build && rm -rf node_modules && php artisan optimize:clear && php artisan optimize"

ssh "$host" "if [ -f \"$deploy_path/artisan\" ]; then cd \"$deploy_path\" && php artisan up; fi"
maintenance_mode=0

printf 'Deploy artifact selesai: %s (%s)\n' "$host:$deploy_path" "$git_ref"
