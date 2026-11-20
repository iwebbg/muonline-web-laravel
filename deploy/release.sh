#!/usr/bin/env bash
# Build and sign the current working tree without changing local dependencies.
set -euo pipefail

deploy_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
exec python3 "$deploy_directory/build-release.py" "$@"
