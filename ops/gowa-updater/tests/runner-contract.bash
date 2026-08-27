#!/usr/bin/env bash
set -euo pipefail

runner="$(dirname "$0")/../lpmf-gowa-runner"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT
bin="$tmp/bin"
mkdir -p "$bin" "$tmp/work" "$tmp/requests/00000000-0000-4000-8000-000000000000" "$tmp/evidence" "$tmp/run"

printf '%s\n' '{"contract":"reconcile-first-v1","fully_implemented":true,"production_ready":true,"capability_version":"1"}' > "$tmp/capability.json"
printf '%s\n' '{"schema_version":1,"signature_valid":true,"generation":"staging-generation-1","releases":[{"release_id":"release-a","version":"v1","image":"registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","digest":"sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","approved":true,"revoked":false,"revocation_generation":"rev-1"}]}' > "$tmp/catalog.json"
printf '%s\n' '{"project":"go-whatsapp-web-multidevice","service":"whatsapp_go","working_directory":"'"$tmp"'/work","compose_files":["'"$tmp"'/compose.yml"],"image_override":"'"$tmp"'/override.yml","network_mode":"host","restart_policy":"unless-stopped","required_socket_absent":true}' > "$tmp/envelope.json"
printf '%s\n' 'services: {}' > "$tmp/compose.yml"
printf '%s\n' '{"services":{"whatsapp_go":{"image":"registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}}}' > "$tmp/override.yml"
printf '%s\n' '{"operation_id":"00000000-0000-4000-8000-000000000000","fence":1,"revoked":false}' > "$tmp/authority.json"
printf '%s\n' 'pass' > "$tmp/preflight.pass"
printf '%s\n' '{"schema_version":1,"previous_image":"registry.example.test/gowa@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc","project":"go-whatsapp-web-multidevice","service":"whatsapp_go","working_directory":"'"$tmp"'/work","compose_files":["'"$tmp"'/compose.yml"],"image_override":"'"$tmp"'/override.yml","network_mode":"host","restart_policy":"unless-stopped","mounts":[],"fixed_config_hash":"0000000000000000000000000000000000000000000000000000000000000000","catalog_generation":"staging-generation-1","policy_version":"1","secret_source":"runner-owned"}' > "$tmp/rollback.json"
openssl genpkey -algorithm ED25519 -out "$tmp/evidence.key" >/dev/null 2>&1

cat > "$bin/psql" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' '{"replayed":false,"payload":{"operation_id":"00000000-0000-4000-8000-000000000000","scope":"gowa","claim_nonce":"00000000-0000-4000-8000-000000000001","fencing_token":1,"release_id":"release-a","digest":"sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","catalog_generation":"staging-generation-1","revocation_generation":"rev-1"},"payload_hash":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"}'
EOF
cat > "$bin/docker" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
if [[ "$*" == *'config --format json'* ]]; then
  printf '%s\n' '{"services":{"whatsapp_go":{"image":"registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","network_mode":"host","restart":"unless-stopped","volumes":[]}}}'
elif [[ "$*" == *'ps --format json'* ]]; then
  printf '%s\n' '{"ID":"0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"}'
elif [[ "$1" == inspect ]]; then
  printf '%s\n' '[{"Id":"0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef","Image":"sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd","Config":{"Image":"registry.example.test/gowa@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","Labels":{"com.docker.compose.project":"go-whatsapp-web-multidevice","com.docker.compose.service":"whatsapp_go"}},"HostConfig":{"NetworkMode":"host","RestartPolicy":{"Name":"unless-stopped"}},"Mounts":[]}]'
fi
EOF
chmod 0700 "$bin/psql" "$bin/docker"
cat > "$bin/systemctl" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" > "${GOWA_TEST_SYSTEMCTL_LOG:?}"
EOF
chmod 0700 "$bin/systemctl"

env PATH="$bin:/usr/bin:/bin" GOWA_UPDATER_ENABLED=1 GOWA_UPDATER_NO_SOCKET_GATE=1 GOWA_UPDATER_CAPABILITY_MANIFEST="$tmp/capability.json" GOWA_UPDATER_REQUEST_ROOT="$tmp/requests" GOWA_TEST_SYSTEMCTL_LOG="$tmp/systemctl.log" bash "$(dirname "$runner")/lpmf-gowa-submit" 00000000-0000-4000-8000-000000000000
grep -F -- 'lpmf-gowa-update@00000000-0000-4000-8000-000000000000.service' "$tmp/systemctl.log" >/dev/null

env PATH="$bin:/usr/bin:/bin" GOWA_UPDATER_ENABLED=1 GOWA_UPDATER_NO_SOCKET_GATE=1 GOWA_UPDATER_CAPABILITY_MANIFEST="$tmp/capability.json" GOWA_UPDATER_CATALOG="$tmp/catalog.json" GOWA_UPDATER_ENVELOPE="$tmp/envelope.json" GOWA_UPDATER_REQUEST_ROOT="$tmp/requests" GOWA_UPDATER_EVIDENCE_ROOT="$tmp/evidence" GOWA_UPDATER_AUTHORITY_PATH="$tmp/authority.json" GOWA_UPDATER_LOCK_PATH="$tmp/run/update.lock" GOWA_UPDATER_PREFLIGHT_GATE="$tmp/preflight.pass" GOWA_UPDATER_EVIDENCE_SIGNING_KEY="$tmp/evidence.key" GOWA_UPDATER_ROLLBACK_MANIFEST="$tmp/rollback.json" GOWA_UPDATER_DOCKER_BIN="$bin/docker" GOWA_UPDATER_PSQL_BIN="$bin/psql" bash "$runner" 00000000-0000-4000-8000-000000000000
test -s "$tmp/evidence/00000000-0000-4000-8000-000000000000/1/1-mutation_observed.json"
test -s "$tmp/evidence/00000000-0000-4000-8000-000000000000/phases/mutation_prepared"
test -s "$tmp/evidence/00000000-0000-4000-8000-000000000000/phases/mutation_observed"
test -f "$tmp/requests/00000000-0000-4000-8000-000000000000/request.consumed"

printf '%s\n' 'runner contract passed'
