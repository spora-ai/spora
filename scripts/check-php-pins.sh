#!/usr/bin/env bash
#
# Fails when the builder's PHP and the runtime's PHP are not the same patch.
#
# The builder resolves the dependency tree, and it resolves against the PHP
# it runs on. The runtime stage runs the PHP the deployment gets. A builder
# on a newer patch than the runtime aborts the install on a dependency that
# caps its PHP range below the builder's patch, even though the runtime
# satisfies that range - which is the failure this repository's deps-builder
# was rebased on. A builder on an older patch installs a dependency the
# runtime cannot run, and that one surfaces at the operator's first request
# rather than at the build.
#
# It exists because Renovate's group does not hold the two pins together. A
# group pull request bumps only the images that have an update, so one pin
# moves while the other stays, and the image the group has no release for
# holds its pin back - which is how the two drift apart without anybody
# deciding to.
#
# The check reads the two tags out of docker/Dockerfile and compares them.
# It asks the registry nothing, so it runs offline and in a second.
#
# Exit codes:
#   0  both stages run the same PHP patch
#   1  the patches differ
#   2  the check could not run: a tag is not in the shape it is read from

set -eu

root="$(cd "$(dirname "$0")/.." && pwd)"
dockerfile="${1:-$root/docker/Dockerfile}"

if [ ! -f "$dockerfile" ]; then
    echo "$dockerfile is missing." >&2
    exit 2
fi

builder="$(sed -n -E 's|^FROM[[:space:]]+php:([0-9]+\.[0-9]+\.[0-9]+)-.*|\1|p' "$dockerfile" | head -n 1)"
runtime="$(sed -n -E 's|^FROM[[:space:]]+dunglas/frankenphp:[^-]*-php([0-9]+\.[0-9]+\.[0-9]+)-.*|\1|p' "$dockerfile" | head -n 1)"

if [ -z "$builder" ] || [ -z "$runtime" ]; then
    echo "Could not read a PHP patch from both base images in $dockerfile." >&2
    exit 2
fi

if [ "$builder" != "$runtime" ]; then
    printf "The builder runs PHP %s and the runtime PHP %s.\n" "$builder" "$runtime" >&2
    echo "They must be the same patch: the builder resolves against the runtime's PHP." >&2
    exit 1
fi

printf 'The builder and the runtime both run PHP %s.\n' "$builder"
