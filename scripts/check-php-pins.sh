#!/usr/bin/env bash
#
# Fails when the builder's PHP and the runtime's PHP are not the same patch,
# or when the Renovate rules stop constraining them to the same PHP minor.
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
# Renovate cannot express "these two tags must carry the same version", so
# the coupling is enforced here instead of in renovate.json. The check reads
# the two tags out of docker/Dockerfile and compares them, and it reads the
# two Renovate rules and compares the PHP minors they allow: a rule that
# allowed a second minor could move one image onto a PHP the other does not
# satisfy, which is the failure this arrangement exists to prevent.
#
# It asks the registry nothing, so it runs offline and in a second.
#
# Exit codes:
#   0  both stages run the same PHP patch, and both rules allow one minor
#   1  the patches differ, or the rules allow different minors
#   2  the check could not run: a tag is not in the shape it is read from

set -eu

root="$(cd "$(dirname "$0")/.." && pwd)"
dockerfile="${1:-$root/docker/Dockerfile}"
renovate="${2:-$root/renovate.json}"

if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required." >&2
    exit 2
fi

if [[ ! -f "$dockerfile" ]]; then
    echo "$dockerfile is missing." >&2
    exit 2
fi

if [[ ! -f "$renovate" ]]; then
    echo "$renovate is missing." >&2
    exit 2
fi

builder="$(sed -n -E 's|^FROM[[:space:]]+php:([0-9]+\.[0-9]+\.[0-9]+)-.*|\1|p' "$dockerfile" | head -n 1)"
runtime="$(sed -n -E 's|^FROM[[:space:]]+dunglas/frankenphp:[^-]*-php([0-9]+\.[0-9]+\.[0-9]+)-.*|\1|p' "$dockerfile" | head -n 1)"

if [[ -z "$builder" || -z "$runtime" ]]; then
    echo "Could not read a PHP patch from both base images in $dockerfile." >&2
    exit 2
fi

if [[ "$builder" != "$runtime" ]]; then
    printf "The builder runs PHP %s and the runtime PHP %s.\n" "$builder" "$runtime" >&2
    echo "They must be the same patch: the builder resolves against the runtime's PHP." >&2
    exit 1
fi

# The PHP minors a rule admits, found by asking the rule's own pattern which
# candidate tags it accepts. The pattern is the authority; reading it as text
# would only handle the shapes it happens to be written in.
allowed_minors() {
    local image="$1" raw pattern minor tag
    raw="$(jq -r --arg image "$image" '
        (.packageRules[]? | select((.matchPackageNames // []) | index($image)) | .allowedVersions) // empty
    ' "$renovate" | head -n 1)"

    if [[ -z "$raw" ]]; then
        return 1
    fi

    # Renovate writes its patterns as /pattern/ in the JavaScript dialect.
    # POSIX ERE has no \d, so it is spelled out.
    pattern="${raw#/}"
    pattern="${pattern%/}"
    pattern="${pattern//\\d/[0-9]}"

    for minor in 7.0 7.1 7.2 7.3 7.4 8.0 8.1 8.2 8.3 8.4 8.5 8.6 8.7 8.8 8.9; do
        case "$image" in
            php) tag="$minor.0-cli-alpine" ;;
            dunglas/frankenphp) tag="1.0.0-php$minor.0-bookworm" ;;
            *) return 1 ;;
        esac
        if printf '%s' "$tag" | grep -qE "$pattern"; then
            printf '%s ' "$minor"
        fi
    done | sed -E 's/[[:space:]]+$//'
}

builder_minors="$(allowed_minors php)"
runtime_minors="$(allowed_minors dunglas/frankenphp)"

if [[ -z "$builder_minors" || -z "$runtime_minors" ]]; then
    echo "Could not read a PHP minor from the Renovate rules for both images." >&2
    exit 2
fi

minor="${builder%.*}"
if [[ "$builder_minors" != "$minor" || "$runtime_minors" != "$minor" ]]; then
    printf "The php rule admits PHP %s and the frankenphp rule admits PHP %s.\n" \
        "$builder_minors" "$runtime_minors" >&2
    printf "The images run PHP %s.\n" "$minor" >&2
    echo "Both rules must admit exactly the minor the images are pinned to: a rule" >&2
    echo "that admitted a second one could move one image onto a PHP the other does" >&2
    echo "not satisfy." >&2
    exit 1
fi

printf 'The builder and the runtime both run PHP %s, and both rules admit PHP %s.\n' "$builder" "$minor"
