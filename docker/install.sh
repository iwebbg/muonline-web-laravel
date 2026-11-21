#!/usr/bin/env bash

set -euo pipefail
# Credentials must never appear in shell tracing, including when invoked with bash -x.
set +x
umask 077

installer_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
project_directory="$(dirname -- "$installer_directory")"
application_environment_file="$project_directory/.env"
docker_environment_file="$installer_directory/.env"
docker_requires_sudo=false

# Reads simple installer defaults without executing the environment file as shell code.
environment_value() {
    local environment_file="$1"
    local setting="$2"

    if [ ! -f "$environment_file" ]; then
        return
    fi

    awk -v setting="$setting" '
        index($0, setting "=") == 1 {
            value = substr($0, length(setting) + 2)
            sub(/\r$/, "", value)
            if (value ~ /^".*"$/ || value ~ /^\047.*\047$/) {
                value = substr(value, 2, length(value) - 2)
            }
            result = value
        }
        END { print result }
    ' "$environment_file"
}

# Atomically replaces a dotenv value; stdin keeps secrets out of process arguments.
write_environment_value() {
    local environment_file="$1"
    local setting="$2"
    local value="$3"
    local temporary_file

    if [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]]; then
        printf 'Environment values cannot contain line breaks.\n' >&2
        return 1
    fi

    value=${value//\\/\\\\}
    value=${value//\"/\\\"}
    value=${value//\$/\\\$}
    temporary_file=$(mktemp "${environment_file}.install.XXXXXX")

    if ! printf '%s="%s"\n' "$setting" "$value" | awk -v setting="$setting" '
        BEGIN { getline replacement < "/dev/stdin" }
        index($0, setting "=") == 1 {
            if (!written) { print replacement }
            written = 1
            next
        }
        { print }
        END { if (!written) { print replacement } }
    ' "$environment_file" > "$temporary_file"; then
        rm -f "$temporary_file"
        return 1
    fi

    chmod 600 "$temporary_file"
    mv "$temporary_file" "$environment_file"
}

# Prompts with a visible default and returns the answer in the requested variable.
prompt_value() {
    local variable_name="$1"
    local label="$2"
    local default_value="$3"
    local answer

    read -r -p "$label [$default_value]: " answer
    printf -v "$variable_name" '%s' "${answer:-$default_value}"
}

# Requests a yes/no choice; EOF is a cancellation rather than implicit consent.
confirm() {
    local answer

    read -r -p "$1 [y/N]: " answer
    [[ "$answer" == [yY] || "$answer" == [yY][eE][sS] ]]
}

# Installs Docker from its official APT repository on Ubuntu or Debian when missing.
ensure_docker() {
    local distribution
    local codename
    local architecture
    local -a privileged=()

    if [ "$(id -u)" -ne 0 ]; then
        privileged=(sudo)
    fi

    if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
        confirm 'Install Docker Engine and Compose from the official Docker APT repository?' || return 1
        distribution=$(. /etc/os-release; printf '%s' "$ID")
        codename=$(. /etc/os-release; printf '%s' "${UBUNTU_CODENAME:-$VERSION_CODENAME}")

        case "$distribution" in
            ubuntu|debian) ;;
            *) printf 'Automatic Docker installation supports Ubuntu and Debian only.\n' >&2; return 1 ;;
        esac

        "${privileged[@]}" apt-get update
        "${privileged[@]}" apt-get install -y ca-certificates curl openssl
        "${privileged[@]}" install -m 0755 -d /etc/apt/keyrings
        "${privileged[@]}" curl -fsSL "https://download.docker.com/linux/$distribution/gpg" -o /etc/apt/keyrings/docker.asc
        "${privileged[@]}" chmod a+r /etc/apt/keyrings/docker.asc
        architecture=$(dpkg --print-architecture)
        printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/%s %s stable\n' "$architecture" "$distribution" "$codename" |
            "${privileged[@]}" tee /etc/apt/sources.list.d/docker.list >/dev/null
        "${privileged[@]}" apt-get update
        "${privileged[@]}" apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    fi

    if docker info >/dev/null 2>&1; then
        return
    fi

    if ! "${privileged[@]}" docker info >/dev/null 2>&1; then
        "${privileged[@]}" service docker start || {
            printf 'Start Docker Desktop with WSL integration, or enable the Docker service, then rerun this installer.\n' >&2
            return 1
        }
    fi

    "${privileged[@]}" docker info >/dev/null
    if [ "${#privileged[@]}" -gt 0 ]; then
        docker_requires_sudo=true
    fi
}

# Uses the existing project wrapper, elevating only Docker when socket access needs it.
compose() {
    if [ "$docker_requires_sudo" = true ]; then
        sudo bash "$installer_directory/compose" "$@"
    else
        bash "$installer_directory/compose" "$@"
    fi
}

# Keeps stable runtime credentials on reruns and generates only missing placeholders.
ensure_generated_value() {
    local setting="$1"
    local value

    value=$(environment_value "$application_environment_file" "$setting")
    if [ -z "$value" ] || [[ "$value" == replace-with-* ]]; then
        value="$(openssl rand -hex 32)Ss@"
        write_environment_value "$application_environment_file" "$setting" "$value"
    fi
}

# Discovers the selectable adapters from the committed registry instead of duplicating its list.
choose_profile() {
    local selection
    local position
    local -a profiles=()

    mapfile -t profiles < <(awk '
        /\047version_profiles\047 => \[/ { registry = 1; next }
        registry && /\]/ { exit }
        registry && /=>/ { split($0, parts, "\047"); print parts[2] }
    ' "$project_directory/config/game.php")

    printf '\nAvailable game profiles:\n'
    for position in "${!profiles[@]}"; do
        printf '  %d) %s\n' "$((position + 1))" "${profiles[$position]}"
    done

    while true; do
        read -r -p 'Profile number: ' selection
        if [[ "$selection" =~ ^[1-9][0-9]?$ ]] && (( selection <= ${#profiles[@]} )); then
            selected_profile="${profiles[$((selection - 1))]}"
            return
        fi
        printf 'Select one of the listed profile numbers.\n'
    done
}

# Collects SQL settings, leaving existing encryption keys and runtime passwords intact.
configure_environment() {
    local selected_profile
    local sql_host
    local sql_port
    local game_database
    local web_database
    local sql_admin
    local sql_password
    local app_login
    local game_login
    local default_game_database=MuOnline
    local default_web_database=MuOnline_Web
    local password_scheme=plaintext
    local trust_certificate=false
    local writes_enabled=false
    local battle_core_database=
    local application_key
    local server_name
    local server_version

    choose_profile
    if [ "$selected_profile" = mudevs_season_21 ]; then
        default_game_database=MuOnline_S21
        default_web_database=MuOnline_S21_Web
    elif [ "$selected_profile" = darksteam_1_0m ]; then
        password_scheme=darksteam_md5
    fi
    case "$selected_profile" in
        mudevs_season_21)
            server_name='MuOnline MUDevs Season 21'
            server_version='S21.1-2'
            ;;
        darksteam_1_0m)
            server_name='MuOnline DarksTeam 1.0M'
            server_version='0.99.60/62T (1.0M)'
            ;;
        muemu_season_6)
            server_name='MuOnline MuEMU Season 6'
            server_version='Season 6 Episode 3 Update 32'
            ;;
        darksteam_97d99i)
            server_name='MuOnline 97d+99i'
            server_version='97d + 99i'
            ;;
        *)
            server_name="$selected_profile"
            server_version="$selected_profile"
            ;;
    esac

    prompt_value sql_host 'SQL Server IP or hostname (Windows host address when using WSL)' "$(environment_value "$application_environment_file" GAME_DB_HOST)"
    while [[ -z "$sql_host" || "$sql_host" == replace-with-* || "$sql_host" == *[\;\=\,[:space:]]* ]]; do
        prompt_value sql_host 'Enter a SQL hostname or IP, without an instance or port' '127.0.0.1'
    done
    prompt_value sql_port 'SQL TCP port' '1433'
    while ! [[ "$sql_port" =~ ^[0-9]{1,5}$ ]] || (( 10#$sql_port < 1 || 10#$sql_port > 65535 )); do
        prompt_value sql_port 'Enter a port between 1 and 65535' '1433'
    done
    prompt_value game_database 'Existing game database' "$default_game_database"
    prompt_value web_database 'Separate website database to create/use' "$default_web_database"
    while [[ "${web_database,,}" == "${game_database,,}" || ! "$web_database" =~ ^[a-zA-Z0-9_]+$ || ! "$game_database" =~ ^[a-zA-Z0-9_]+$ ]]; do
        printf 'Use distinct database names with letters, digits and underscores.\n'
        prompt_value game_database 'Existing game database' "$default_game_database"
        prompt_value web_database 'Separate website database' "$default_web_database"
    done
    prompt_value app_login 'Dedicated website SQL login' 'muonline_web_app'
    prompt_value game_login 'Dedicated game-access SQL login' 'muonline_web_user'
    prompt_value sql_admin 'Temporary SQL administrator login' 'sa'
    while [[ ! "$app_login" =~ ^[a-zA-Z0-9_]+$ || ! "$game_login" =~ ^[a-zA-Z0-9_]+$ || "${app_login,,}" == "${game_login,,}" || "${app_login,,}" == "${sql_admin,,}" || "${game_login,,}" == "${sql_admin,,}" ]]; do
        printf 'Runtime logins must be valid, distinct names, separate from the administrator.\n'
        prompt_value app_login 'Dedicated website SQL login' 'muonline_web_app'
        prompt_value game_login 'Dedicated game-access SQL login' 'muonline_web_user'
    done
    read -r -s -p "SQL password for $sql_admin (hidden): " sql_password
    printf '\n'
    if [ -z "$sql_password" ]; then
        printf 'An administrator password is required for installation.\n' >&2
        return 1
    fi
    if confirm 'Trust the SQL Server certificate for this local installation?'; then
        trust_certificate=true
    fi
    if confirm 'Enable player writes to the game database (resets, storage, services)?'; then
        writes_enabled=true
    fi
    if [ "$selected_profile" = mudevs_season_21 ]; then
        prompt_value battle_core_database 'Existing BattleCore database (blank to skip)' ''
    fi

    write_environment_value "$docker_environment_file" DOCKER_ENV local
    write_environment_value "$docker_environment_file" LOCAL_UID "$(id -u)"
    write_environment_value "$docker_environment_file" LOCAL_GID "$(id -g)"
    write_environment_value "$application_environment_file" APP_ENV local
    write_environment_value "$application_environment_file" DB_CONNECTION sqlsrv
    write_environment_value "$application_environment_file" DB_HOST "$sql_host"
    write_environment_value "$application_environment_file" DB_PORT "$sql_port"
    write_environment_value "$application_environment_file" DB_DATABASE "$web_database"
    write_environment_value "$application_environment_file" DB_USERNAME "$app_login"
    write_environment_value "$application_environment_file" DB_ENCRYPT true
    write_environment_value "$application_environment_file" DB_TRUST_SERVER_CERTIFICATE "$trust_certificate"
    write_environment_value "$application_environment_file" APP_DB_ADMIN_USERNAME "$sql_admin"
    write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD "$sql_password"
    write_environment_value "$application_environment_file" APP_DB_BOOTSTRAP true
    write_environment_value "$application_environment_file" GAME_DB_CONNECTION sqlsrv
    write_environment_value "$application_environment_file" GAME_DB_HOST "$sql_host"
    write_environment_value "$application_environment_file" GAME_DB_PORT "$sql_port"
    write_environment_value "$application_environment_file" GAME_DB_MUONLINE "$game_database"
    write_environment_value "$application_environment_file" GAME_DB_RANKING "$game_database"
    write_environment_value "$application_environment_file" GAME_RANKING_CONNECTION game
    write_environment_value "$application_environment_file" GAME_DB_USERNAME "$game_login"
    write_environment_value "$application_environment_file" GAME_DB_ADMIN_USERNAME ''
    write_environment_value "$application_environment_file" GAME_DB_ADMIN_PASSWORD ''
    write_environment_value "$application_environment_file" GAME_DB_ENCRYPT true
    write_environment_value "$application_environment_file" GAME_DB_TRUST_SERVER_CERTIFICATE "$trust_certificate"
    write_environment_value "$application_environment_file" GAME_DB_BATTLE_CORE "$battle_core_database"
    write_environment_value "$application_environment_file" GAME_BATTLE_CORE_READS_ENABLED false
    write_environment_value "$application_environment_file" GAME_SERVER_PROFILE "$selected_profile"
    write_environment_value "$application_environment_file" GAME_EVENT_PROFILE "$selected_profile"
    write_environment_value "$application_environment_file" GAME_ACCOUNT_PASSWORD_SCHEME "$password_scheme"
    write_environment_value "$application_environment_file" GAME_SERVER_NAME "$server_name"
    write_environment_value "$application_environment_file" GAME_SERVER_VERSION "$server_version"
    write_environment_value "$application_environment_file" GAME_SERVER_STATUS_HOST "$sql_host"
    write_environment_value "$application_environment_file" GAME_DATA_SOURCE database
    write_environment_value "$application_environment_file" GAME_WRITES_ENABLED "$writes_enabled"
    if [ "$writes_enabled" = true ]; then
        write_environment_value "$application_environment_file" GAME_DB_READONLY false
    else
        write_environment_value "$application_environment_file" GAME_DB_READONLY true
    fi
    write_environment_value "$application_environment_file" REDIS_HOST redis
    ensure_generated_value DB_PASSWORD
    ensure_generated_value GAME_DB_PASSWORD
    application_key=$(environment_value "$application_environment_file" APP_KEY)
    if [ -z "$application_key" ]; then
        write_environment_value "$application_environment_file" APP_KEY "base64:$(openssl rand -base64 32)"
    fi
    unset sql_password
}

# Tests the administrator connection and verifies the existing game database without mutations.
check_sql_connection() {
    compose run --rm --no-deps --entrypoint php app -r '
        $dsn = "sqlsrv:Server=".getenv("GAME_DB_HOST").",".getenv("GAME_DB_PORT").";Database=master;Encrypt=true;TrustServerCertificate=".getenv("GAME_DB_TRUST_SERVER_CERTIFICATE").";LoginTimeout=5";
        try {
            $connection = new PDO($dsn, getenv("APP_DB_ADMIN_USERNAME"), getenv("APP_DB_ADMIN_PASSWORD"));
            $query = $connection->prepare("SELECT state_desc FROM sys.databases WHERE name = ?");
            $query->execute([getenv("GAME_DB_MUONLINE")]);
            if ($query->fetchColumn() !== "ONLINE") {
                throw new RuntimeException("The configured game database is missing or is not ONLINE. Restore it before installing the website.");
            }
            echo "SQL connection and game database: OK\n";
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage()."\n");
            exit(1);
        }
    '
}

# Boots the configured application and retires bootstrap secrets after successful provisioning.
install_application() {
    compose config --quiet
    compose build app
    check_sql_connection
    compose run --rm --no-deps --entrypoint sh app -c 'if [ -f vendor/autoload.php ]; then php artisan config:clear --no-interaction; fi'
    compose up -d redis
    compose run --rm --no-deps app true

    write_environment_value "$application_environment_file" APP_DB_BOOTSTRAP false
    write_environment_value "$application_environment_file" APP_DB_ADMIN_PASSWORD ''
    write_environment_value "$application_environment_file" GAME_DB_ADMIN_PASSWORD ''
    compose up -d --build --force-recreate --wait --wait-timeout 300
    compose exec -T app php artisan game:doctor --no-interaction
    if confirm 'Create a website administrator now?'; then
        compose exec app php artisan admin:create
    fi
    printf '\nInstallation complete. Temporary SQL administrator passwords have been removed.\n'
    printf 'Open the site using APP_URL in .env. Rebuild assets with: ./docker/compose run --rm frontend\n'
}

# Runs the interactive local setup without changing existing files before consent.
main() {
    local environment_file
    local -a privileged=()

    if [[ "${1:-}" == --help ]]; then
        printf 'Usage: bash docker/install.sh\nInteractive local Ubuntu/WSL installation; requires an existing SQL Server game database.\n'
        return
    fi
    if [ ! -t 0 ]; then
        printf 'Run this installer in an interactive terminal.\n' >&2
        return 1
    fi
    printf '\nMuOnline Web — local Ubuntu / WSL installer\n'
    if [ -f "$application_environment_file" ] || [ -f "$docker_environment_file" ]; then
        confirm 'Existing configuration found. Reconfigure it while preserving runtime passwords and APP_KEY?' || return 0
    fi
    ensure_docker
    if ! command -v openssl >/dev/null; then
        if [ "$(id -u)" -ne 0 ]; then
            privileged=(sudo)
        fi
        "${privileged[@]}" apt-get install -y openssl
    fi
    for environment_file in "$application_environment_file" "$docker_environment_file"; do
        if [ ! -f "$environment_file" ]; then
            cp "${environment_file}.example" "$environment_file"
        fi
    done
    configure_environment
    install_application
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    main "$@"
fi
