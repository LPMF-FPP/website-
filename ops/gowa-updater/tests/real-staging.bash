#!/usr/bin/env bash
set -euo pipefail

# Local-only real Docker staging test. It never contacts SSH, deploy tooling, or production paths.
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
runner="${script_dir}/../lpmf-gowa-runner"
submit_helper="${script_dir}/../lpmf-gowa-submit"
project='gowa-updater-staging'
port='3000'
rollback_image='docker.io/aldinokemal2104/go-whatsapp-web-multidevice@sha256:2649acd2db195b23695b36b034ce92812e2204de721c17e9a105c461030833bb'
target_image='docker.io/aldinokemal2104/go-whatsapp-web-multidevice@sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc'
operation_id='00000000-0000-4000-8000-000000000002'
claim_nonce='00000000-0000-4000-8000-000000000003'
payload_hash='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
rollback_mode=false

if [[ "${1:-}" == '--rollback-failure' ]]; then
    rollback_mode=true
elif [[ $# -ne 0 ]]; then
    printf 'usage: %s [--rollback-failure]\n' "$0" >&2
    exit 64
fi

for binary in docker jq openssl curl php; do
    command -v "$binary" >/dev/null || { printf 'missing required binary: %s\n' "$binary" >&2; exit 78; }
done
docker compose version >/dev/null

root="$(mktemp -d "${TMPDIR:-/tmp}/gowa-updater-staging.XXXXXX")"
compose_file="${root}/compose.yml"
override_file="${root}/target-override.yml"
storage_dir="${root}/storages"
statics_dir="${root}/statics"
request_root="${root}/requests"
evidence_root="${root}/evidence"
run_dir="${root}/run"
bin_dir="${root}/bin"
private_key="${root}/evidence.key"
public_key="${root}/evidence.pub"
catalog_public_key="${root}/catalog.pub"
cleanup_status=0

cleanup() {
    set +e
    docker compose --project-name "$project" -f "$compose_file" down --volumes --remove-orphans >/dev/null 2>&1
    mapfile -t leftovers < <(docker ps -aq --filter "label=com.docker.compose.project=${project}")
    [[ "${#leftovers[@]}" -eq 0 ]] || cleanup_status=1
    if [[ -e "$root" ]]; then
        docker run --rm --user 0 --entrypoint /bin/sh -v "$root:/cleanup" "$rollback_image" -c 'rm -rf /cleanup' >/dev/null 2>&1 || true
        rm -rf -- "$root"
        [[ ! -e "$root" ]] || cleanup_status=1
    fi
    [[ ! -e "$private_key" && ! -e "$public_key" ]] || cleanup_status=1
    if [[ "$cleanup_status" -ne 0 ]]; then
        printf 'cleanup verification failed\n' >&2
        exit 1
    fi
}
trap cleanup EXIT INT TERM

mkdir -p "$storage_dir" "$statics_dir" "$request_root/$operation_id" "$evidence_root" "$run_dir" "$bin_dir"
printf 'staging-preserved-storage\n' > "$storage_dir/preserved.txt"
printf 'staging-preserved-statics\n' > "$statics_dir/preserved.txt"

cat > "$compose_file" <<EOF
services:
  whatsapp_go:
    image: ${rollback_image}
    network_mode: host
    restart: unless-stopped
    volumes:
      - ${storage_dir}:/app/storages
      - ${statics_dir}:/app/statics
    environment:
      APP_BASIC_AUTH: staging:local-only
      APP_PORT: ${port}
      APP_DEBUG: 'false'
      APP_UI_ENABLED: 'false'
      MCP_ENABLED: 'false'
      APP_OS: Chrome
      APP_BASE_PATH: ''
      TZ: UTC
EOF
printf '%s\n' '{"services":{"whatsapp_go":{"image":"'"$target_image"'"}}}' > "$override_file"

openssl genpkey -algorithm ED25519 -out "$private_key" >/dev/null 2>&1
chmod 0600 "$private_key"
openssl pkey -in "$private_key" -pubout -outform DER -out "${root}/evidence.pub.der" >/dev/null 2>&1
pub_size="$(wc -c < "${root}/evidence.pub.der")"
dd if="${root}/evidence.pub.der" bs=1 skip=$((pub_size - 32)) status=none | base64 -w0 > "$public_key"
chmod 0640 "$public_key"

printf '%s\n' '{"contract":"reconcile-first-v1","fully_implemented":true,"production_ready":true,"capability_version":"1","stage":"local-only"}' > "$root/capability.json"
printf '%s\n' '{"schema_version":1,"signature_valid":true,"generation":"staging-real-generation-1","releases":[{"release_id":"gowa-real-staging-target","version":"staging-target","image":"'"$target_image"'","digest":"sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc","approved":true,"revoked":false,"revocation_generation":"staging-real-revocation-1"}]}' > "$root/catalog.json"
php -r '$path=$argv[1]; $public=$argv[2]; $catalog=json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); $catalog += ["revocation_generation"=>"staging-real-revocation-1","approved_registry"=>"docker.io","approved_repository"=>"docker.io/aldinokemal2104/go-whatsapp-web-multidevice","platform"=>"linux/amd64","signature"=>["algorithm"=>"ed25519","key_id"=>"staging-catalog-key","value"=>""]]; $keypair=sodium_crypto_sign_keypair(); $sort=function (array $value) use (&$sort): array { foreach ($value as $key => $item) { if (is_array($item)) { $value[$key]=$sort($item); } } if (!array_is_list($value)) { ksort($value); } return $value; }; $payload=$catalog; unset($payload["signature"], $payload["signature_valid"]); $catalog["signature"]["value"]=base64_encode(sodium_crypto_sign_detached(json_encode($sort($payload), JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES), sodium_crypto_sign_secretkey($keypair))); file_put_contents($path, json_encode($catalog, JSON_THROW_ON_ERROR)); file_put_contents($public, base64_encode(sodium_crypto_sign_publickey($keypair)));' "$root/catalog.json" "$catalog_public_key"
printf '%s\n' '{"project":"'"$project"'","service":"whatsapp_go","working_directory":"'"$root"'","compose_files":["'"$compose_file"'"],"image_override":"'"$override_file"'","network_mode":"host","restart_policy":"unless-stopped","required_socket_absent":true}' > "$root/envelope.json"
printf '%s\n' '{"schema_version":1,"operation_id":"'"$operation_id"'","fence":1,"revoked":false,"installed":true}' > "$root/authority.json"
printf 'pass\n' > "$root/preflight.pass"
printf 'enabled\n' > "$root/enabled"
mounts="$(jq -cn --arg storage "$storage_dir" --arg statics "$statics_dir" '[{Type:"bind",Source:$storage,Destination:"/app/storages",RW:true},{Type:"bind",Source:$statics,Destination:"/app/statics",RW:true}]')"
fixed_config_hash="$(docker compose --project-name "$project" -f "$compose_file" -f "$override_file" config --format json | jq -S -c --arg service whatsapp_go 'del(.services[$service].image)' | sha256sum | cut -d ' ' -f 1)"
if "$rollback_mode"; then
    fixed_config_hash='d2c28428f03bb3573f2e617cd97b373544352eff796e585f9e0896c5b0c90e57'
    mounts='[]'
fi
jq -n --arg image "$rollback_image" --arg project "$project" --arg work "$root" --arg compose "$compose_file" --arg override "$override_file" --arg generation 'staging-real-generation-1' --arg fixed_hash "$fixed_config_hash" --argjson mounts "$mounts" '{schema_version:1,previous_image:$image,project:$project,service:"whatsapp_go",working_directory:$work,compose_files:[$compose],image_override:$override,network_mode:"host",restart_policy:"unless-stopped",mounts:$mounts,fixed_config_hash:$fixed_hash,catalog_generation:$generation,policy_version:"1",secret_source:"runner-owned"}' > "$root/rollback.json"

cat > "$bin_dir/psql" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
sql="$*"
printf '%s\n' "$sql" >> "${GOWA_STAGING_PSQL_LOG:?}"
if [[ "$sql" == *'claim_dispatch'* ]]; then
    printf '%s\n' '{"replayed":false,"payload":{"operation_id":"00000000-0000-4000-8000-000000000002","scope":"gowa","claim_nonce":"00000000-0000-4000-8000-000000000003","fencing_token":1,"release_id":"gowa-real-staging-target","digest":"sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc","catalog_generation":"staging-real-generation-1","revocation_generation":"staging-real-revocation-1"},"payload_hash":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"}'
elif [[ "$sql" == *'consume_dispatch'* ]]; then
    printf '%s\n' '{"replayed":false,"payload":{"operation_id":"00000000-0000-4000-8000-000000000002","scope":"gowa","claim_nonce":"00000000-0000-4000-8000-000000000003","fencing_token":1,"release_id":"gowa-real-staging-target","digest":"sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc","catalog_generation":"staging-real-generation-1","revocation_generation":"staging-real-revocation-1"},"payload_hash":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"}'
else
    exit 1
fi
EOF
cat > "$bin_dir/systemctl" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" > "${GOWA_STAGING_SYSTEMCTL_LOG:?}"
EOF
chmod 0700 "$bin_dir/psql" "$bin_dir/systemctl"

if "$rollback_mode"; then
    fake_docker="$bin_dir/docker"
    cat > "$fake_docker" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
joined=" $* "
if [[ "$joined" == *' config --format json '* ]]; then
    printf '%s\n' '{"services":{"whatsapp_go":{"image":"docker.io/aldinokemal2104/go-whatsapp-web-multidevice@sha256:d4411a6e3f197ffc830ac5be9a055dec5756e8199cacd028d989f8ae657435fc","network_mode":"host","restart":"unless-stopped"}}}'
elif [[ "$joined" == *' ps --format json '* ]]; then
    printf '%s\n' '[{"ID":"bbbbbbbbbbbb"}]'
elif [[ "$1" == inspect ]]; then
    printf '%s\n' '[{"Id":"bbbbbbbbbbbb","Config":{"Image":"docker.io/aldinokemal2104/go-whatsapp-web-multidevice@sha256:2649acd2db195b23695b36b034ce92812e2204de721c17e9a105c461030833bb","Labels":{"com.docker.compose.project":"gowa-updater-staging","com.docker.compose.service":"whatsapp_go"}},"HostConfig":{"NetworkMode":"host","RestartPolicy":{"Name":"unless-stopped"}},"Mounts":[]}]'
else
    exit 1
fi
EOF
    chmod 0700 "$fake_docker"
    printf '%s\n' '{"operation_id":"00000000-0000-4000-8000-000000000002","action":"update","scope":"gowa","fence":1,"claim_nonce":"00000000-0000-4000-8000-000000000003","payload_hash":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","release_id":"gowa-real-staging-target"}' > "$request_root/$operation_id/request.pending"
    set +e
    env PATH="$bin_dir:/usr/bin:/bin" GOWA_STAGING_PSQL_LOG="$root/psql.log" GOWA_UPDATER_ENABLED=1 GOWA_UPDATER_NO_SOCKET_GATE=1 GOWA_UPDATER_EXPECTED_PROJECT="$project" GOWA_UPDATER_CAPABILITY_MANIFEST="$root/capability.json" GOWA_UPDATER_CATALOG="$root/catalog.json" GOWA_UPDATER_CATALOG_PUBLIC_KEY="$catalog_public_key" GOWA_UPDATER_ENVELOPE="$root/envelope.json" GOWA_UPDATER_REQUEST_ROOT="$request_root" GOWA_UPDATER_EVIDENCE_ROOT="$evidence_root" GOWA_UPDATER_AUTHORITY_PATH="$root/authority.json" GOWA_UPDATER_LOCK_PATH="$run_dir/update.lock" GOWA_UPDATER_PREFLIGHT_GATE="$root/preflight.pass" GOWA_UPDATER_ENABLED_MARKER="$root/enabled" GOWA_UPDATER_EVIDENCE_SIGNING_KEY="$private_key" GOWA_UPDATER_ROLLBACK_MANIFEST="$root/rollback.json" GOWA_UPDATER_DOCKER_BIN="$fake_docker" GOWA_UPDATER_PSQL_BIN="$bin_dir/psql" bash "$runner" "$operation_id"
    status=$?
    set -e
    [[ "$status" -eq 70 ]] || { printf 'rollback failure mode status=%s, expected 70\n' "$status" >&2; exit 1; }
    jq -e '.payload.code == "rollback_degraded"' "$evidence_root/$operation_id/1/3-rollback_degraded.json" >/dev/null
    printf 'rollback failure mode passed\n'
    exit 0
fi

docker compose --project-name "$project" -f "$compose_file" up --detach --wait whatsapp_go >/dev/null
for attempt in {1..60}; do
    if curl --silent --show-error --fail --user 'staging:local-only' "http://127.0.0.1:${port}/" >/dev/null; then break; fi
    [[ "$attempt" -lt 60 ]] || { printf 'rollback image did not return HTTP 200 on port %s\n' "$port" >&2; exit 1; }
    sleep 1
done

env PATH="$bin_dir:/usr/bin:/bin" GOWA_STAGING_PSQL_LOG="$root/psql.log" GOWA_STAGING_SYSTEMCTL_LOG="$root/systemctl.log" GOWA_UPDATER_CAPABILITY_MANIFEST="$root/capability.json" GOWA_UPDATER_ENABLED_MARKER="$root/enabled" GOWA_UPDATER_PREFLIGHT_GATE="$root/preflight.pass" GOWA_UPDATER_AUTHORITY_PATH="$root/authority.json" GOWA_UPDATER_REQUEST_ROOT="$request_root" GOWA_UPDATER_DATABASE='staging' GOWA_UPDATER_SUBMIT_ROLE='lpmf_gowa_submit' bash "$submit_helper" "$operation_id"
env PATH="$bin_dir:/usr/bin:/bin" GOWA_STAGING_PSQL_LOG="$root/psql.log" GOWA_UPDATER_ENABLED=1 GOWA_UPDATER_NO_SOCKET_GATE=1 GOWA_UPDATER_EXPECTED_PROJECT="$project" GOWA_UPDATER_CAPABILITY_MANIFEST="$root/capability.json" GOWA_UPDATER_CATALOG="$root/catalog.json" GOWA_UPDATER_CATALOG_PUBLIC_KEY="$catalog_public_key" GOWA_UPDATER_ENVELOPE="$root/envelope.json" GOWA_UPDATER_REQUEST_ROOT="$request_root" GOWA_UPDATER_EVIDENCE_ROOT="$evidence_root" GOWA_UPDATER_AUTHORITY_PATH="$root/authority.json" GOWA_UPDATER_LOCK_PATH="$run_dir/update.lock" GOWA_UPDATER_PREFLIGHT_GATE="$root/preflight.pass" GOWA_UPDATER_ENABLED_MARKER="$root/enabled" GOWA_UPDATER_EVIDENCE_SIGNING_KEY="$private_key" GOWA_UPDATER_ROLLBACK_MANIFEST="$root/rollback.json" GOWA_UPDATER_DOCKER_BIN=/usr/bin/docker GOWA_UPDATER_PSQL_BIN="$bin_dir/psql" bash "$runner" "$operation_id"

container_id="$(docker compose --project-name "$project" -f "$compose_file" ps --format json whatsapp_go | jq -r 'if type == "array" then .[0].ID // .[0].Id else .ID // .Id end')"
inspect="$(docker inspect "$container_id")"
jq -e --arg image "$target_image" --arg project "$project" '.[0].Config.Image == $image and .[0].Config.Labels["com.docker.compose.project"] == $project and ((.[0].Mounts // []) | map(select(.Destination == "/var/run/docker.sock")) | length == 0)' <<<"$inspect" >/dev/null
jq -e --arg storage "$storage_dir" --arg statics "$statics_dir" '.[0].Mounts | map({Type,Source,Destination,RW}) | sort_by(.Destination) == ([{Type:"bind",Source:$storage,Destination:"/app/storages",RW:true},{Type:"bind",Source:$statics,Destination:"/app/statics",RW:true}] | sort_by(.Destination))' <<<"$inspect" >/dev/null
[[ -f "$storage_dir/preserved.txt" && -f "$statics_dir/preserved.txt" ]]
[[ -f "$request_root/$operation_id/request.consumed" && ! -e "$request_root/$operation_id/request.pending" ]]
mapfile -t evidence < <(printf '%s\n' "$evidence_root/$operation_id/1/"*.json)
[[ "${#evidence[@]}" -eq 3 ]]
for evidence_file in "${evidence[@]}"; do
    php -r 'require $argv[1]; $importer = new App\Services\WhatsApp\GowaEvidenceImporter($argv[3], false); $importer->decode($argv[2]);' "$script_dir/../../../vendor/autoload.php" "$evidence_file" "$public_key"
done
psql_log="$(<"$root/psql.log")"
[[ "$psql_log" == *claim_dispatch* && "$psql_log" == *consume_dispatch* ]]
printf 'real staging forward update passed: project=%s port=%s target=%s evidence=%s\n' "$project" "$port" "$target_image" "${#evidence[@]}"
