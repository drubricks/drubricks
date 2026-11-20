#!/usr/bin/env bash
###
# Launches Drubricks on DDEV: configures and starts the containers, installs
# the Composer dependencies and installs the site (or leaves it to the
# browser installer). Unix-like systems only (Linux, macOS, WSL).
#
#   ./launch-drubricks.sh                 asks how to install and how you use
#                                         the code (your own project or
#                                         contributing to Drubricks)
#   ./launch-drubricks.sh -y              installs every app (with its demo
#                                         data), keeps the starter files
#                                         (contributing), no questions
#   ./launch-drubricks.sh --own-project   like -y, then starts your own
#                                         project without asking (see below)
#   ./launch-drubricks.sh --own-project --apps=
#                                         your own project, base only: no
#                                         example app installed (they stay in
#                                         the code as references)
#   --apps=a,b                            install only these App recipes
#                                         (like scripts/reinstall.sh)
#
# Drubricks is a starter: the code a project starts from becomes the
# project's own code (no update hooks, no upgrade path). The theme and the
# modules keep their machine names and place (web/themes/custom/drubricks,
# web/modules/custom/drubricks_*): modules, templates and config refer to the
# theme's components as drubricks:<component>, so the theme is not renamed
# (like a site template that ships components, see core's starterkit docs).
# Starting your own project stamps them with the starter version
# (starter_source) and removes the starter's history (.git).
###
set -e

AUTO_YES=false
OWN_PROJECT=ask
APPS=all
APPS_GIVEN=false
for arg in "$@"; do
  case "$arg" in
    -y|--yes) AUTO_YES=true; OWN_PROJECT=no ;;
    --own-project) AUTO_YES=true; OWN_PROJECT=yes ;;
    --apps=*) APPS=${arg#--apps=}; APPS_GIVEN=true ;;
    *) echo "Unknown option $arg"; exit 1 ;;
  esac
done

echo ""
echo "Drubricks launcher (DDEV)"
echo "========================="

if ! command -v ddev >/dev/null; then
  echo "DDEV needs to be installed. Visit https://ddev.com/get-started for instructions."
  exit 1
fi
if ! docker info >/dev/null 2>&1; then
  echo "Docker is not running. Start Docker Desktop / OrbStack / Colima and run this script again."
  exit 1
fi

# DDEV configuration is local, not part of the repository (like Open Intranet
# and Drupal CMS): create it on the first run, reuse it afterwards. Check for
# the config file, not the directory: a .ddev/ without config.yaml (e.g. only
# add-ons) must still be configured.
if [[ -f .ddev/config.yaml ]]; then
  echo "==> DDEV already configured, reusing .ddev/config.yaml"
else
  # The folder name, with the first free numeric suffix when another DDEV
  # project already uses it (names from the JSON output: the table wraps).
  NAME=$(basename "$PWD" | tr '_' '-')
  EXISTING=$(ddev list -j 2>/dev/null | grep -o '"name":"[^"]*"' | sed 's/^"name":"\(.*\)"$/\1/' || true)
  if printf '%s\n' "$EXISTING" | grep -qx "$NAME"; then
    SUFFIX=2
    while printf '%s\n' "$EXISTING" | grep -qx "$NAME-$SUFFIX"; do SUFFIX=$((SUFFIX + 1)); done
    NAME="$NAME-$SUFFIX"
  fi
  echo "==> Configuring DDEV project \"$NAME\" (Drupal 12, PHP 8.5, MariaDB 11.8, Node.js 24, docroot: web)"
  ddev config --project-type=drupal12 --docroot=web --php-version=8.5 --database=mariadb:11.8 \
    --nodejs-version=24 --corepack-enable --ddev-version-constraint=">=1.25.0" --project-name="$NAME"
fi

echo "==> Starting DDEV"
ddev start
echo "==> Installing Composer dependencies (the first run takes a few minutes)"
ddev composer install --no-interaction
URL=$(ddev describe -j | python3 -c 'import json,sys; print(json.load(sys.stdin)["raw"]["primary_url"])')

ask_yes_no() {
  while true; do
    # End of input counts as "no" (piped or closed input).
    if ! read -r -p "$1 [y/n]: " yn; then
      echo ""
      return 1
    fi
    case $yn in
      [Yy]*) return 0 ;;
      [Nn]*) return 1 ;;
      *) echo "Please answer yes (y) or no (n)." ;;
    esac
  done
}

# How is this codebase used?
#  - Your own project (an app for a client or for you): the starter code in
#    web/themes/custom and web/modules/custom becomes your code. Every
#    extension gets starter_source: 'drubricks:<version>' (to diff against
#    the right starter version later), the starter's git history goes,
#    and you can start a fresh repository.
#  - Contributing to Drubricks: keep everything (-y does that).
# The work is done by scripts/start-own-project.sh. A project started before (stamped) is never asked again: its .git is the
# project's own repository.
if grep -q '^starter_source:' web/modules/custom/drubricks_core/drubricks_core.info.yml 2>/dev/null; then
  echo "==> Your own project (started from $(sed -n "s/^starter_source: '\(.*\)'/\1/p" web/modules/custom/drubricks_core/drubricks_core.info.yml))"
  OWN_PROJECT=started
fi
if [[ "$OWN_PROJECT" == ask ]]; then
  echo ""
  echo "How will you use this codebase?"
  echo "  * Your OWN project: answer yes. The theme and modules stay where they are and"
  echo "    become your code (no upgrade path from Drubricks); the starter's .git is"
  echo "    removed and you can start a fresh git repository."
  echo "  * Contributing to Drubricks itself: answer no to keep everything."
  if ask_yes_no "Start your own project from Drubricks?"; then OWN_PROJECT=yes; else OWN_PROJECT=no; fi
fi

if [[ "$OWN_PROJECT" == yes ]]; then
  if [[ "$AUTO_YES" == true ]]; then scripts/start-own-project.sh --yes; else scripts/start-own-project.sh; fi
elif [[ "$OWN_PROJECT" == no ]]; then
  echo "==> Keeping the starter files (contributing to Drubricks)."
fi


CHOICE=1
if [[ "$AUTO_YES" == false && "$APPS_GIVEN" == false ]]; then
  echo ""
  echo "How do you want to install Drubricks?"
  echo "  1) Now, with every example app and its demo data (admin / admin)"
  echo "  2) Now, base only, no apps (admin / admin)"
  echo "  3) In the browser installer (you choose the apps and the account)"
  read -r -p "Choice [1]: " CHOICE
  CHOICE=${CHOICE:-1}
fi

case "$CHOICE" in
  1) scripts/reinstall.sh -y "--apps=$APPS" ;;
  2) scripts/reinstall.sh -y --apps= ;;
  3)
    if ddev drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
      echo "A site is already installed here. To start over: scripts/reinstall.sh (it drops the database)."
    else
      echo "==> Opening the installer: $URL/core/install.php"
      ddev launch /core/install.php || true
      exit 0
    fi
    ;;
  *) echo "Unknown choice"; exit 1 ;;
esac

echo ""
echo "Drubricks is ready: $URL  (admin / admin)"
ddev launch /apps >/dev/null 2>&1 || true
