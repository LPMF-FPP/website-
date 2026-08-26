#!/usr/bin/env bash
set -euo pipefail

check_only=false
if [[ "${1:-}" == "--check-only" ]]; then
    check_only=true
    shift
fi

if [[ "$#" -lt 2 || "$#" -gt 3 ]]; then
    printf 'Usage: %s [--check-only] <ssh-host> <deploy-path> [git-ref]\n' "$0"
    printf 'Required: DEPLOY_HOST_KEY_FINGERPRINT=SHA256:...\n'
    printf 'Optional: DEPLOY_KNOWN_HOSTS_FILE=<path>, DEPLOY_SSH_IDENTITY_FILE=<path>, DEPLOY_SSH_PORT=22, DEPLOY_EXPECTED_PATH=/var/www/lis, DEPLOY_HEALTH_URL=https://...\n'
    exit 1
fi

host="$1"
deploy_path="$2"
git_ref="${3:-origin/release}"
ssh_port="${DEPLOY_SSH_PORT:-22}"
known_hosts_file="${DEPLOY_KNOWN_HOSTS_FILE:-$HOME/.ssh/known_hosts}"
identity_file="${DEPLOY_SSH_IDENTITY_FILE:-$HOME/.ssh/id_ed25519_lpmf_production}"
expected_host_key_fingerprint="${DEPLOY_HOST_KEY_FINGERPRINT:?Set DEPLOY_HOST_KEY_FINGERPRINT to a verified SHA256 host-key fingerprint}"
expected_deploy_path="${DEPLOY_EXPECTED_PATH:-/var/www/lis}"
maintenance_mode=0
tmp_dir=""
pinned_known_hosts_file=""
ssh_command=()

cleanup() {
    if [[ "$maintenance_mode" -eq 1 && "${#ssh_command[@]}" -gt 0 ]]; then
        "${ssh_command[@]}" "$host" "if [ -f ${remote_deploy_path}/artisan ]; then cd ${remote_deploy_path} && php artisan up || true; fi" >/dev/null 2>&1 || true
    fi

    if [[ -n "$tmp_dir" ]]; then
        rm -rf -- "$tmp_dir"
    fi

    if [[ -n "$pinned_known_hosts_file" ]]; then
        rm -f -- "$pinned_known_hosts_file"
    fi
}
trap cleanup EXIT

if [[ ! "$host" =~ ^[A-Za-z_][A-Za-z0-9._-]*@[A-Za-z0-9][A-Za-z0-9.-]*$ ]]; then
    printf 'Invalid SSH host. Expected user@hostname or user@IPv4.\n' >&2
    exit 1
fi

if [[ "$deploy_path" == "/" || "$deploy_path" == */ || "$deploy_path" == *"//"* \
    || "$deploy_path" == *"/../"* || "$deploy_path" == */.. \
    || "$deploy_path" == *"/./"* || "$deploy_path" == */. \
    || ! "$deploy_path" =~ ^/[A-Za-z0-9._/-]+$ ]]; then
    printf 'Unsafe deploy path: %s\n' "$deploy_path" >&2
    exit 1
fi

if [[ "$deploy_path" != "$expected_deploy_path" ]]; then
    printf 'Refusing deploy path %s; expected %s.\n' "$deploy_path" "$expected_deploy_path" >&2
    exit 1
fi

if [[ "$git_ref" == -* || ! "$git_ref" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ ]]; then
    printf 'Invalid git ref: %s\n' "$git_ref" >&2
    exit 1
fi

printf -v remote_deploy_path '%q' "$deploy_path"

if [[ ! "$ssh_port" =~ ^[0-9]+$ ]] || (( ssh_port < 1 || ssh_port > 65535 )); then
    printf 'Invalid DEPLOY_SSH_PORT: %s\n' "$ssh_port" >&2
    exit 1
fi

if [[ ! "$expected_host_key_fingerprint" =~ ^SHA256:[A-Za-z0-9+/=]+$ ]]; then
    printf 'DEPLOY_HOST_KEY_FINGERPRINT must use the SHA256:<value> format.\n' >&2
    exit 1
fi

if [[ ! -r "$known_hosts_file" ]]; then
    printf 'Trusted known_hosts file is not readable: %s\n' "$known_hosts_file" >&2
    exit 1
fi

if [[ ! -r "$identity_file" ]]; then
    printf 'SSH identity file is not readable: %s\n' "$identity_file" >&2
    exit 1
fi

host_name="${host##*@}"
known_host="${DEPLOY_HOST_KEY_ALIAS:-$host_name}"
if [[ -z "${DEPLOY_HOST_KEY_ALIAS:-}" && "$ssh_port" != "22" ]]; then
    known_host="[$host_name]:$ssh_port"
fi

pinned_known_hosts_file="$(mktemp)"
while IFS= read -r candidate_key; do
    if [[ -z "$candidate_key" || "$candidate_key" == \#* ]]; then
        continue
    fi

    candidate_fingerprint="$(printf '%s\n' "$candidate_key" | ssh-keygen -lf - 2>/dev/null | awk '{print $2}')"
    if [[ "$candidate_fingerprint" == "$expected_host_key_fingerprint" ]]; then
        printf '%s\n' "$candidate_key" >> "$pinned_known_hosts_file"
    fi
done < <(ssh-keygen -F "$known_host" -f "$known_hosts_file" 2>/dev/null)

if [[ ! -s "$pinned_known_hosts_file" ]]; then
    printf 'Trusted host-key entry does not match DEPLOY_HOST_KEY_FINGERPRINT.\n' >&2
    exit 1
fi
chmod 600 "$pinned_known_hosts_file"

ssh_options=(
    -F /dev/null
    -i "$identity_file"
    -p "$ssh_port"
    -o BatchMode=yes
    -o IdentitiesOnly=yes
    -o StrictHostKeyChecking=yes
    -o "UserKnownHostsFile=$pinned_known_hosts_file"
    -o GlobalKnownHostsFile=/dev/null
    -o KnownHostsCommand=none
    -o VerifyHostKeyDNS=no
    -o UpdateHostKeys=no
    -o ControlMaster=no
    -o ControlPath=none
    -o ControlPersist=no
)

if [[ -n "${DEPLOY_HOST_KEY_ALIAS:-}" ]]; then
    ssh_options+=(-o "HostKeyAlias=${DEPLOY_HOST_KEY_ALIAS}")
fi

ssh_command=(ssh "${ssh_options[@]}")
printf -v rsync_ssh '%q ' "${ssh_command[@]}"
rsync_ssh="${rsync_ssh% }"

if ! remote_canonical_path="$("${ssh_command[@]}" "$host" "readlink -f -- ${remote_deploy_path}")"; then
    printf 'Unable to resolve the remote deploy path.\n' >&2
    exit 1
fi

if [[ "$remote_canonical_path" != "$deploy_path" ]]; then
    printf 'Remote deploy path resolves to an unexpected location: %s\n' "$remote_canonical_path" >&2
    exit 1
fi

if ! "${ssh_command[@]}" "$host" "test -d ${remote_deploy_path} && test -f ${remote_deploy_path}/artisan && test -f ${remote_deploy_path}/.env"; then
    printf 'Remote deploy target is missing the expected application markers.\n' >&2
    exit 1
fi

if $check_only; then
    printf 'Trusted host key and SSH connection verified for deployment preflight.\n'
    exit 0
fi

health_url="${DEPLOY_HEALTH_URL:?Set DEPLOY_HEALTH_URL before running a deployment}"
if [[ ! "$health_url" =~ ^https:// ]]; then
    printf 'DEPLOY_HEALTH_URL must use HTTPS.\n' >&2
    exit 1
fi

repo_root="$(git rev-parse --show-toplevel)"
exclude_file="${repo_root}/scripts/deploy-artifact.exclude"

if [ ! -f "$exclude_file" ]; then
    printf 'Exclude file not found: %s\n' "$exclude_file"
    exit 1
fi

if ! git -C "$repo_root" rev-parse --verify --end-of-options "${git_ref}^{commit}" >/dev/null 2>&1; then
    printf 'Git ref not found: %s\n' "$git_ref"
    exit 1
fi

tmp_dir="$(mktemp -d)"
git -C "$repo_root" archive --format=tar "$git_ref" -- | tar -xf - -C "$tmp_dir"

"${ssh_command[@]}" "$host" "mkdir -p ${remote_deploy_path}"

"${ssh_command[@]}" "$host" "if [ -f ${remote_deploy_path}/artisan ]; then cd ${remote_deploy_path} && php artisan down --retry=60; fi"
maintenance_mode=1

rsync -rlz --delete --force \
    -e "$rsync_ssh" \
    --exclude-from="$exclude_file" \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='storage/***' \
    --exclude='vendor/***' \
    "$tmp_dir/" "$host:$deploy_path/"

"${ssh_command[@]}" "$host" "cd ${remote_deploy_path} && rm -rf .git .github .vscode .intelephense .opencode .ruff_cache .serena .uv-cache .worktrees _bmad _bmad-output docs output report temp tests dokpol-style && rm -f AGENTS.md CODE_REVIEW.md RAMS_UI_GUIDELINES.md VERCEL_GUIDELINES.md qmh-living-system-tech-spec.md todos.md .editorconfig .eslintrc.cjs .stylelintrc.cjs eslint.config.cjs phpunit.xml phpunit.dusk.xml .phpunit.result.cache .env.testing .env.testing.example .env.dusk.local .env.dusk.testing lighthouserc.json .phpstorm.meta.php _ide_helper.php check_docs.php check_all_docs.php fix_numbering.php landing-page-lpmf.html role"

"${ssh_command[@]}" "$host" "cd ${remote_deploy_path} && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && php artisan migrate --force && export NVM_DIR=\"\$HOME/.nvm\" && [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\" && npm ci && npm run build && rm -rf node_modules && php artisan optimize:clear && php artisan optimize"

"${ssh_command[@]}" "$host" "if [ -f ${remote_deploy_path}/artisan ]; then cd ${remote_deploy_path} && php artisan up; fi"
maintenance_mode=0

if ! curl --fail --location --silent --show-error --max-time 15 "$health_url" >/dev/null; then
    printf 'Deployment completed, but the configured health check failed.\n' >&2
    exit 1
fi

printf 'Deploy artifact selesai: %s (%s)\n' "$host:$deploy_path" "$git_ref"
