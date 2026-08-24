#!/usr/bin/env bash
#
# slimad/indexnow-laravel depends on slimad/indexnow. Until the core package is
# published on Packagist there is nothing for Composer to resolve, so this
# script points Composer at the GitHub repository instead.
#
# It is a bootstrap, not a permanent part of the build: as soon as
# https://packagist.org/packages/slimad/indexnow exists the script does nothing
# and can be deleted together with the "Resolve the core package" CI steps.

set -euo pipefail

if curl -fsS -o /dev/null "https://repo.packagist.org/p2/slimad/indexnow.json"; then
    echo "slimad/indexnow is published on Packagist; nothing to do."
    exit 0
fi

echo "slimad/indexnow is not on Packagist yet; installing it from GitHub."

composer config repositories.slimad-indexnow \
    '{"type":"vcs","url":"https://github.com/maciej-kosiedowski/IndexNow","no-api":true}'

if ! composer show --available slimad/indexnow >/dev/null 2>&1 \
    || ! composer show --available slimad/indexnow 2>/dev/null | grep -qE '^versions.*1\.'; then
    echo "No 1.x tag found; falling back to the default branch."
    composer config minimum-stability dev
    composer config prefer-stable true
    composer require --no-update --no-interaction "slimad/indexnow:1.x-dev as 1.0.0"
fi
