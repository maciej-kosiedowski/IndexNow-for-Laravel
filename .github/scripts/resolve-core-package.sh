#!/usr/bin/env bash
#
# slimad/indexnow-laravel requires slimad/indexnow "^1.0", which only resolves
# once the core package has a tagged 1.x release. Until that tag exists, this
# script lets CI install the package's default branch instead (Packagist serves
# it as 1.x-dev through the core package's branch-alias).
#
# It is a bootstrap, not a permanent part of the build: as soon as
# https://packagist.org/packages/slimad/indexnow ships 1.0.0 the script does
# nothing and can be deleted together with the "Resolve the core package" steps.

set -euo pipefail

PACKAGE="slimad/indexnow"
METADATA="https://repo.packagist.org/p2/${PACKAGE}.json"

# Packagist keeps tagged releases in p2/<package>.json and branches in the
# ~dev variant, so this file alone answers "is there a 1.x release yet?".
if curl -fsS "${METADATA}" | PACKAGE="${PACKAGE}" php -r '
    $metadata = json_decode(stream_get_contents(STDIN), true);
    $releases = \is_array($metadata) ? ($metadata["packages"][getenv("PACKAGE")] ?? []) : [];

    foreach ($releases as $release) {
        if (str_starts_with(ltrim((string) ($release["version"] ?? ""), "v"), "1.")) {
            exit(0);
        }
    }

    exit(1);
'; then
    echo "${PACKAGE} has a 1.x release on Packagist; nothing to do."
    exit 0
fi

if curl -fsS -o /dev/null "${METADATA}"; then
    echo "${PACKAGE} is on Packagist but has no 1.x release; installing its default branch."

    # ^1.0 matches the 1.x-dev alias of the default branch once dev packages are
    # allowed. prefer-stable keeps every other dependency on its stable release.
    composer config minimum-stability dev
    composer config prefer-stable true

    exit 0
fi

cat <<MESSAGE

${PACKAGE} is not on Packagist, so Composer has nothing to install and every job
in this workflow will fail.

Unblock it by submitting ${PACKAGE} to Packagist from
https://github.com/maciej-kosiedowski/IndexNow, then tag 1.0.0 there. After the
tag lands this script exits at the first check above and can be deleted together
with the "Resolve the core package" steps.

MESSAGE

exit 1
