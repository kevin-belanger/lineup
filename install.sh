#!/usr/bin/env bash

set -euo pipefail

ENV_FILE="${ENV_FILE:-.env}"
ENV_EXAMPLE_FILE="${ENV_EXAMPLE_FILE:-.env.example}"
created_env=0

set_env_value() {
    local key="$1"
    local value="$2"
    local tmp_file

    tmp_file="$(mktemp)"

    if grep -q "^${key}=" "${ENV_FILE}"; then
        awk -v key="${key}" -v value="${value}" '
            BEGIN { replaced = 0 }
            $0 ~ "^" key "=" {
                print key "=" value
                replaced = 1
                next
            }
            { print }
            END {
                if (replaced == 0) {
                    print key "=" value
                }
            }
        ' "${ENV_FILE}" > "${tmp_file}"
    else
        cp "${ENV_FILE}" "${tmp_file}"
        printf '\n%s=%s\n' "${key}" "${value}" >> "${tmp_file}"
    fi

    mv "${tmp_file}" "${ENV_FILE}"
}

get_env_value() {
    local key="$1"

    grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 | cut -d '=' -f 2-
}

generate_base64() {
    local bytes="$1"

    openssl rand -base64 "${bytes}" | tr -d '\n'
}

if [ ! -f "${ENV_EXAMPLE_FILE}" ]; then
    echo "Missing ${ENV_EXAMPLE_FILE}."
    exit 1
fi

if [ ! -f "${ENV_FILE}" ]; then
    cp "${ENV_EXAMPLE_FILE}" "${ENV_FILE}"
    created_env=1
    echo "Created ${ENV_FILE} from ${ENV_EXAMPLE_FILE}."
fi

app_key="$(get_env_value APP_KEY || true)"
if [ -z "${app_key}" ]; then
    set_env_value APP_KEY "base64:$(generate_base64 32)"
    echo "Generated APP_KEY in ${ENV_FILE}."
fi

db_password="$(get_env_value DB_PASSWORD || true)"
if [ -z "${db_password}" ]; then
    set_env_value DB_PASSWORD "$(generate_base64 32)"
    echo "Generated DB_PASSWORD in ${ENV_FILE}."
fi

echo "Review ${ENV_FILE} before production use, especially APP_URL, mail settings, and admin bootstrap values."

if [ "${created_env}" -eq 1 ]; then
    echo "Edit ${ENV_FILE}, then run ./install.sh again to start Docker and run migrations."
    exit 0
fi

docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
