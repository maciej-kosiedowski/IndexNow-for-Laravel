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

PACKAGE="slimad/indexnow"
REPOSITORY="https://github.com/maciej-kosiedowski/IndexNow"

if curl -fsS -o /dev/null "https://repo.packagist.org/p2/${PACKAGE}.json"; then
    echo "${PACKAGE} is published on Packagist; nothing to do."
    exit 0
fi

echo "${PACKAGE} is not on Packagist yet; trying to install it from GitHub."

if ! git ls-remote "${REPOSITORY}.git" >/dev/null 2>&1; then
    cat <<MESSAGE

${PACKAGE} is reachable neither on Packagist nor on GitHub, so Composer has
nothing to install and every job in this workflow will fail.

Unblock it by doing either of these:

  * publish ${PACKAGE} on Packagist (the normal, permanent fix), or
  * make ${REPOSITORY} public, so Composer can clone it anonymously.

The core repository is private today. The intended order is: merge its pull
request, switch the repository to public, tag 1.0.0, submit the package to
Packagist. After that this script exits at the check above and can be deleted
together with the "Resolve the core package" steps.

MESSAGE
    exit 1
fi

composer config repositories.slimad-indexnow \
    "{\"type\":\"vcs\",\"url\":\"${REPOSITORY}\",\"no-api\":true}"

if composer show --available "${PACKAGE}" 2>/dev/null | grep -qE '^versions.*[^.0-9]1\.[0-9]'; then
    echo "Found a 1.x release of ${PACKAGE} on GitHub."
    exit 0
fi

echo "No 1.x tag found; falling back to the default branch."
composer config minimum-stability dev
composer config prefer-stable true
composer require --no-update --no-interaction "${PACKAGE}:1.x-dev as 1.0.0"
