#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  ./scripts/bootstrap-legacy-github.sh [project-root]

Cosa fa:
  - inizializza Git locale se manca
  - crea o riusa una repository GitHub
  - crea o riusa un project Bitwarden Secrets Manager
  - chiede i valori da salvare su Bitwarden
  - imposta secrets e variables della repository GitHub
  - genera .github/workflows/deploy.yml

Prerequisiti:
  - gh autenticato (`gh auth login`)
  - bws installato
  - jq installato
EOF
}

if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
  usage
  exit 0
fi

require_command() {
  local command_name="$1"

  if ! command -v "$command_name" >/dev/null 2>&1; then
    printf 'Errore: comando richiesto non trovato: %s\n' "$command_name" >&2
    exit 1
  fi
}

slugify() {
  printf '%s' "$1" \
    | tr '[:upper:]' '[:lower:]' \
    | sed -E 's/[^a-z0-9._-]+/-/g; s/^-+//; s/-+$//; s/-{2,}/-/g'
}

normalize_domain() {
  printf '%s' "$1" \
    | sed -E 's#^[[:space:]]*https?://##; s#/.*$##; s#/$##; s#[[:space:]]+$##'
}

prompt_value() {
  local prompt_text="$1"
  local default_value="${2:-}"
  local hidden="${3:-false}"
  local allow_empty="${4:-false}"
  local value=""

  while :; do
    if [ "$hidden" = "true" ]; then
      if [ -n "$default_value" ]; then
        printf '%s [%s]: ' "$prompt_text" "$default_value" >&2
      else
        printf '%s: ' "$prompt_text" >&2
      fi

      stty -echo
      IFS= read -r value
      stty echo
      printf '\n' >&2
    else
      if [ -n "$default_value" ]; then
        printf '%s [%s]: ' "$prompt_text" "$default_value" >&2
      else
        printf '%s: ' "$prompt_text" >&2
      fi

      IFS= read -r value
    fi

    if [ -z "$value" ] && [ -n "$default_value" ]; then
      value="$default_value"
    fi

    if [ "$allow_empty" = "true" ] || [ -n "$value" ]; then
      printf '%s' "$value"
      return 0
    fi

    printf 'Valore obbligatorio.\n' >&2
  done
}

confirm() {
  local prompt_text="$1"
  local default_answer="${2:-y}"
  local answer=""

  while :; do
    if [ "$default_answer" = "y" ]; then
      printf '%s [Y/n]: ' "$prompt_text" >&2
    else
      printf '%s [y/N]: ' "$prompt_text" >&2
    fi

    IFS= read -r answer

    if [ -z "$answer" ]; then
      answer="$default_answer"
    fi

    case "$answer" in
      y|Y|yes|YES) return 0 ;;
      n|N|no|NO) return 1 ;;
    esac

    printf 'Rispondi con y o n.\n' >&2
  done
}

ensure_github_auth() {
  if ! gh auth status >/dev/null 2>&1; then
    printf 'Errore: autenticazione GitHub non valida. Esegui `gh auth login` e riprova.\n' >&2
    exit 1
  fi
}

ensure_git_repository() {
  if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    return 0
  fi

  printf 'Inizializzo repository Git locale...\n'
  git init >/dev/null 2>&1
  git symbolic-ref HEAD refs/heads/main >/dev/null 2>&1 || true
}

ensure_remote_origin() {
  local repo_full_name="$1"
  local repo_url

  if git remote get-url origin >/dev/null 2>&1; then
    return 0
  fi

  repo_url="$(gh repo view "$repo_full_name" --json url --jq '.url')"
  git remote add origin "$repo_url"
}

ensure_github_repository() {
  local repo_name="$1"
  local visibility="$2"
  local repo_full_name=""

  if gh repo view "$repo_name" --json nameWithOwner --jq '.nameWithOwner' >/dev/null 2>&1; then
    repo_full_name="$(gh repo view "$repo_name" --json nameWithOwner --jq '.nameWithOwner')"
    printf 'Repository GitHub già presente: %s\n' "$repo_full_name" >&2
    printf '%s' "$repo_full_name"
    return 0
  fi

  printf 'Creo repository GitHub: %s\n' "$repo_name" >&2

  if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    if git remote get-url origin >/dev/null 2>&1; then
      gh repo create "$repo_name" "--$visibility" >/dev/null
    else
      gh repo create "$repo_name" "--$visibility" --source=. --remote=origin >/dev/null
    fi
  else
    gh repo create "$repo_name" "--$visibility" >/dev/null
  fi

  repo_full_name="$(gh repo view "$repo_name" --json nameWithOwner --jq '.nameWithOwner')"
  printf 'Repository GitHub creata: %s\n' "$repo_full_name" >&2
  printf '%s' "$repo_full_name"
}

ensure_bitwarden_project() {
  local access_token="$1"
  local project_name="$2"
  local project_id=""
  local projects_json=""

  projects_json="$(BWS_ACCESS_TOKEN="$access_token" bws project list --output json)"
  project_id="$(printf '%s' "$projects_json" | jq -r --arg name "$project_name" 'first(.[] | select(.name == $name) | .id) // empty')"

  if [ -n "$project_id" ] && [ "$project_id" != "null" ]; then
    printf 'Project Bitwarden già presente: %s\n' "$project_name" >&2
    printf '%s' "$project_id"
    return 0
  fi

  printf 'Creo project Bitwarden: %s\n' "$project_name" >&2
  project_id="$(
    BWS_ACCESS_TOKEN="$access_token" \
      bws project create "$project_name" --output json \
      | jq -r '.id'
  )"

  if [ -z "$project_id" ] || [ "$project_id" = "null" ]; then
    printf 'Errore: impossibile creare il project Bitwarden.\n' >&2
    exit 1
  fi

  printf 'Project Bitwarden creato: %s\n' "$project_name" >&2
  printf '%s' "$project_id"
}

upsert_bitwarden_secret() {
  local access_token="$1"
  local project_id="$2"
  local secret_key="$3"
  local secret_value="$4"
  local secret_id=""
  local secrets_json=""

  [ -n "$secret_value" ] || return 0

  secrets_json="$(BWS_ACCESS_TOKEN="$access_token" bws secret list "$project_id" --output json)"
  secret_id="$(printf '%s' "$secrets_json" | jq -r --arg key "$secret_key" 'first(.[] | select(.key == $key) | .id) // empty')"

  if [ -n "$secret_id" ] && [ "$secret_id" != "null" ]; then
    BWS_ACCESS_TOKEN="$access_token" \
      bws secret edit --value "$secret_value" "$secret_id" --output json >/dev/null
    printf 'Secret Bitwarden aggiornato: %s\n' "$secret_key"
    return 0
  fi

  BWS_ACCESS_TOKEN="$access_token" \
    bws secret create "$secret_key" "$secret_value" "$project_id" --output json >/dev/null
  printf 'Secret Bitwarden creato: %s\n' "$secret_key"
}

set_github_secret() {
  local repo_full_name="$1"
  local key="$2"
  local value="$3"

  gh secret set "$key" --repo "$repo_full_name" --body "$value" >/dev/null
  printf 'GitHub secret aggiornato: %s\n' "$key"
}

set_github_variable() {
  local repo_full_name="$1"
  local key="$2"
  local value="$3"

  gh variable set "$key" --repo "$repo_full_name" --body "$value" >/dev/null
  printf 'GitHub variable aggiornata: %s\n' "$key"
}

write_workflow() {
  local workflow_path="$1"

  mkdir -p "$(dirname "$workflow_path")"

  cat > "$workflow_path" <<'YAML'
name: 🚀 Deploy

on:
  push:
    branches:
      - main

jobs:
  web-deploy:
    name: 🎯 Deploy
    runs-on: ubuntu-latest
    env:
      BWS_ACCESS_TOKEN: ${{ secrets.BWS_ACCESS_TOKEN }}
      BWS_PROJECT_ID: ${{ secrets.BWS_PROJECT_ID }}
    steps:

      - name: 🚚 Get latest code
        uses: actions/checkout@v4

      - name: 📦 Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: 📦 Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: composer

      - name: 📦 Install dependencies
        run: |
          sudo apt-get update
          sudo apt-get install -y jq unzip

      - name: 📦 Install Bitwarden Secrets Manager CLI
        run: |
          curl -Lso bws.zip https://github.com/bitwarden/sdk-sm/releases/download/bws-v2.0.0/bws-x86_64-unknown-linux-gnu-2.0.0.zip
          unzip -o bws.zip
          chmod +x bws
          sudo mv bws /usr/local/bin/bws
          bws --version

      - name: 🔐 Load secrets + generate .env
        shell: bash
        run: |
          set -euo pipefail

          : > .env

          bws secret list "$BWS_PROJECT_ID" --output json \
            | jq -c '.[] | select(.key != null and .value != null)' \
            | while read -r item; do
                key="$(echo "$item" | jq -r '.key')"
                value="$(echo "$item" | jq -r '.value')"

                echo "::add-mask::$value"

                printf '%s=%s\n' "$key" "$value" >> .env
                printf '%s=%s\n' "$key" "$value" >> "$GITHUB_ENV"
              done

          cat >> .env <<EOF

          APP_DEBUG=false
          APP_DOMAIN=${{ vars.APP_DOMAIN }}
          APP_URL=https://${{ vars.APP_DOMAIN }}

          ASSETS_VERSION=${{ vars.ASSETS_VERSION }}

          DB_CONNECTION_LOG=false

          EOF

      - name: Normalize FTP path
        shell: bash
        run: |
          set -euo pipefail
          path="${FTP_REMOTE_PATH:-}"
          [ -n "$path" ] || { echo "Missing FTP_REMOTE_PATH"; exit 1; }
          [[ "$path" != */ ]] && path="${path}/"
          echo "FTP_REMOTE_PATH=$path" >> "$GITHUB_ENV"

      - name: 📦 Installa Pacchetti
        run: composer install --no-dev --optimize-autoloader --no-scripts

      - name: 📂 Sincronizza file FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.4
        with:
            server: ${{ env.FTP_HOST }}
            username: ${{ env.FTP_USER }}
            password: ${{ env.FTP_PASSWORD }}
            port: ${{ env.FTP_PORT }}
            server-dir: ${{ env.FTP_REMOTE_PATH }}
            local-dir: ./
            exclude: |
              **/.git*
              **/.git*/**
              **/.github*
              **/.github/**
              **/.vscode/**
              **/composer.json
              **/composer.lock
              **/package.json
              **/package-lock.json
              *.md

      - name: 🗄️ Update App
        shell: bash
        run: |
          set -euo pipefail

          curl -fsS --retry 3 --retry-all-errors \
            -X POST "https://${{ vars.APP_DOMAIN }}/api/app/update/" \
            -H "Authorization: Bearer $GITHUB_API_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"release_id\":\"$GITHUB_SHA\",\"source\":\"github\"}"
YAML
}

require_command git
require_command gh
require_command bws
require_command jq

ensure_github_auth

PROJECT_ROOT="${1:-$(pwd)}"
PROJECT_ROOT="$(cd "$PROJECT_ROOT" && pwd)"
PROJECT_BASENAME="$(basename "$PROJECT_ROOT")"
DEFAULT_SLUG="$(slugify "$PROJECT_BASENAME")"

cd "$PROJECT_ROOT"

printf 'Bootstrap progetto legacy in: %s\n' "$PROJECT_ROOT"

REPO_NAME="$(prompt_value 'Nome repository GitHub' "$DEFAULT_SLUG")"
APP_DOMAIN="$(normalize_domain "$(prompt_value 'APP_DOMAIN' "$DEFAULT_SLUG")")"
ASSETS_VERSION="$(prompt_value 'ASSETS_VERSION' '0.0')"
BWS_ACCESS_TOKEN="$(prompt_value 'BWS_ACCESS_TOKEN Bitwarden' '' true)"
BITWARDEN_PROJECT_NAME="$(prompt_value 'Nome project Bitwarden' "$APP_DOMAIN")"

if [ -z "$APP_DOMAIN" ]; then
  printf 'Errore: APP_DOMAIN non valido.\n' >&2
  exit 1
fi

VISIBILITY='private'
if confirm 'Vuoi creare una repository pubblica?' 'n'; then
  VISIBILITY='public'
fi

APP_KEY="$(prompt_value 'APP_KEY (lascia vuoto per saltare)' '' true true)"
DB_HOSTNAME="$(prompt_value 'DB_HOSTNAME (lascia vuoto per saltare)' '' false true)"
DB_USERNAME="$(prompt_value 'DB_USERNAME (lascia vuoto per saltare)' '' false true)"
DB_PASSWORD="$(prompt_value 'DB_PASSWORD (lascia vuoto per saltare)' '' true true)"
DB_DATABASE="$(prompt_value 'DB_DATABASE (lascia vuoto per saltare)' '' false true)"
FTP_HOST="$(prompt_value 'FTP_HOST' '')"
FTP_USER="$(prompt_value 'FTP_USER' '')"
FTP_PASSWORD="$(prompt_value 'FTP_PASSWORD' '' true)"
FTP_PORT="$(prompt_value 'FTP_PORT' '21')"
FTP_REMOTE_PATH="$(prompt_value 'FTP_REMOTE_PATH' '/')"
GITHUB_API_TOKEN="$(prompt_value 'GITHUB_API_TOKEN da usare per /api/app/update/' '' true)"

ensure_git_repository
REPO_FULL_NAME="$(ensure_github_repository "$REPO_NAME" "$VISIBILITY")"
ensure_remote_origin "$REPO_FULL_NAME"
BITWARDEN_PROJECT_ID="$(ensure_bitwarden_project "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_NAME")"

set_github_secret "$REPO_FULL_NAME" 'BWS_ACCESS_TOKEN' "$BWS_ACCESS_TOKEN"
set_github_secret "$REPO_FULL_NAME" 'BWS_PROJECT_ID' "$BITWARDEN_PROJECT_ID"
set_github_variable "$REPO_FULL_NAME" 'APP_DOMAIN' "$APP_DOMAIN"
set_github_variable "$REPO_FULL_NAME" 'ASSETS_VERSION' "$ASSETS_VERSION"

upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'APP_KEY' "$APP_KEY"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_HOSTNAME' "$DB_HOSTNAME"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_HOST' "$DB_HOSTNAME"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_USERNAME' "$DB_USERNAME"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_USER' "$DB_USERNAME"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_PASSWORD' "$DB_PASSWORD"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_DATABASE' "$DB_DATABASE"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'DB_NAME' "$DB_DATABASE"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'FTP_HOST' "$FTP_HOST"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'FTP_USER' "$FTP_USER"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'FTP_PASSWORD' "$FTP_PASSWORD"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'FTP_PORT' "$FTP_PORT"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'FTP_REMOTE_PATH' "$FTP_REMOTE_PATH"
upsert_bitwarden_secret "$BWS_ACCESS_TOKEN" "$BITWARDEN_PROJECT_ID" 'GITHUB_API_TOKEN' "$GITHUB_API_TOKEN"

WORKFLOW_PATH="$PROJECT_ROOT/.github/workflows/deploy.yml"
if [ -f "$WORKFLOW_PATH" ]; then
  if confirm 'deploy.yml esiste già. Vuoi sovrascriverlo?' 'n'; then
    write_workflow "$WORKFLOW_PATH"
  fi
else
  write_workflow "$WORKFLOW_PATH"
fi

printf '\nBootstrap completato.\n'
printf 'Repository GitHub: %s\n' "$REPO_FULL_NAME"
printf 'Project Bitwarden: %s\n' "$BITWARDEN_PROJECT_NAME"
printf 'Workflow creato: %s\n' "$WORKFLOW_PATH"
printf '\nProssimi passi consigliati:\n'
printf '  git add .\n'
printf '  git commit -m "Add deploy workflow"\n'
printf '  git push -u origin main\n'
