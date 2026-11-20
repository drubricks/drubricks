#!/usr/bin/env bash
# Starts your own project from the Drubricks starter (run by
# launch-drubricks.sh when you answer "yes", or with --own-project).
#
#   scripts/start-own-project.sh          asks before creating a new repository
#   scripts/start-own-project.sh --yes    no questions (new repository included)
#
# The theme and the modules stay where they are, with their machine names
# (modules, templates and config use the theme's components as
# drubricks:<component>), and become the project's code: every extension in
# web/themes/custom, web/modules/custom and web/profiles/custom gets
# starter_source: 'drubricks:<version>+<commit>' in its *.info.yml, to diff
# against the right starter version later. The starter's history (.git) goes; a new git repository starts with a first commit that
# includes composer.lock (the starter does not track one).
# A stamped project is left alone (its .git is the project's own).
set -euo pipefail
cd "$(dirname "$0")/.."

YES=false
for arg in "$@"; do
  case "$arg" in
    -y|--yes) YES=true ;;
    *) echo "Unknown option $arg"; exit 1 ;;
  esac
done

if grep -q '^starter_source:' web/modules/custom/drubricks_core/drubricks_core.info.yml; then
  echo "Already your own project: $(sed -n "s/^starter_source: '\(.*\)'/\1/p" web/modules/custom/drubricks_core/drubricks_core.info.yml)"
  exit 0
fi

# The starter's release tag (Composer takes versions from tags as well).
VERSION=$(git describe --tags --abbrev=0 2>/dev/null || true)
COMMIT=$(git rev-parse --short HEAD 2>/dev/null || true)
SOURCE="drubricks:${VERSION:-dev}${COMMIT:++$COMMIT}"

echo "==> Stamping the starter code with its source: $SOURCE"
STAMPED=0
while read -r info; do
  printf "\n# Starter code from Drubricks - owned by this project, not updated by\n# Drubricks releases (diff against this version to see what changed).\nstarter_source: '%s'\n" "$SOURCE" >> "$info"
  STAMPED=$((STAMPED + 1))
done < <(find web/themes/custom web/modules/custom web/profiles/custom -name '*.info.yml' \
  -not -path '*/tests/*' -not -path '*/node_modules/*')
echo "    Stamped $STAMPED extension(s): from now on they are this project's code."

# The project keeps its composer.lock (the starter does not).
if grep -q '^/composer.lock$' .gitignore; then
  grep -v '^/composer.lock$' .gitignore > .gitignore.tmp && mv .gitignore.tmp .gitignore
  echo "==> composer.lock is tracked from now on (the project's exact versions)."
fi

echo "==> Removing the starter's history (.git)"
rm -rf .git

INIT=$YES
if [[ "$YES" == false ]]; then
  read -r -p "Initialize a new git repository with the project's first commit? [y/n]: " answer || answer=n
  [[ "$answer" == [Yy]* ]] && INIT=true
fi
if [[ "$INIT" == true ]]; then
  git init -q
  git add -A
  if git commit -q -m "Start from Drubricks ($SOURCE)"; then
    echo "==> New repository with the first commit."
  else
    echo "==> New repository (files staged; commit them once git knows your name and email)."
  fi
fi
