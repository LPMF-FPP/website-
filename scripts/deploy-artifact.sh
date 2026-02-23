#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
    printf 'Usage: %s <ssh-host> <deploy-path> [git-ref]\n' "$0"
    printf 'Example: %s 192.168.1.25 /var/www/lis origin/main\n' "$0"
    exit 1
fi

host="$1"
deploy_path="$2"
git_ref="${3:-origin/main}"

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

rsync -az --delete --delete-excluded \
    --exclude-from="$exclude_file" \
    --filter='P .env' \
    --filter='P .env.*' \
    --filter='P storage/' \
    --filter='P vendor/' \
    "$tmp_dir/" "$host:$deploy_path/"

ssh "$host" "cd \"$deploy_path\" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && php artisan migrate --force && export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && npm ci && npm run build && rm -rf node_modules && php artisan optimize"

printf 'Deploy artifact selesai: %s (%s)\n' "$host:$deploy_path" "$git_ref"
