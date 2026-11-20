#!/usr/bin/env bash
# Full reinstall of the local DDEV site from code (installer profile + recipes).
#
#   scripts/reinstall.sh                 all apps (asks first)
#   scripts/reinstall.sh -y              no confirmation prompt (CI)
#   scripts/reinstall.sh -y --apps=drubricks_examples_crm
#   scripts/reinstall.sh -y --apps=      base only
#
# Every app comes with its demo data (recipes/<app>_demo), as in the installer.
#
# Same as the browser installer's "Choose applications" step.
set -euo pipefail
cd "$(dirname "$0")/.."

YES=false
APPS=all
for arg in "$@"; do
  case "$arg" in
    -y|--yes) YES=true ;;
    --apps=*) APPS=${arg#--apps=} ;;
    *) echo "Unknown option $arg"; exit 1 ;;
  esac
done

if [[ "$YES" == false ]]; then
  read -r -p "This DROPS the local database and reinstalls Drubricks (apps: ${APPS:-none}). Continue? [y/N] " answer
  [[ "$answer" == "y" ]] || { echo "Aborted."; exit 1; }
fi

ddev start
ddev composer install --no-interaction
ddev drush site:install drubricks_installer \
  --account-name=admin --account-pass=admin --site-name="Drubricks" -y \
  "drubricks_installer_apps.apps=$APPS"
URL=$(ddev describe -j | python3 -c 'import json,sys; print(json.load(sys.stdin)["raw"]["primary_url"])')
echo "Done: $URL (admin / admin) — apps: ${APPS:-none}"
