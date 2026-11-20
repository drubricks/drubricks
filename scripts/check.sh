#!/usr/bin/env bash
# Runs the same checks as CI (phpcs, phpstan, PHPUnit unit + kernel + functional)
# in DDEV.
# Run it before every commit.
set -uo pipefail
cd "$(dirname "$0")/.."
fail=0
echo "== phpcs";   ddev exec vendor/bin/phpcs -q || fail=1
echo "== phpstan"; ddev exec vendor/bin/phpstan analyse --no-progress --memory-limit=1G || fail=1
echo "== phpunit"; ddev exec vendor/bin/phpunit --testsuite unit,kernel,functional || fail=1
[[ $fail == 0 ]] && echo "All checks passed." || { echo "Checks FAILED."; exit 1; }
