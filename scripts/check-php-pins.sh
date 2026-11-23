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
# A rule it cannot read exactly is refused, not guessed at. A regular
# expression cannot be sampled to find out what it admits - one that accepts
# 8.6.1 but not 8.6.0 admits a second minor, and no finite set of samples
# proves otherwise - so the rules have to have a shape the check reads
# exactly.
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

# The PHP minor a rule admits.
#
# A regular expression cannot be sampled to find out what it admits: a
# pattern that accepts 8.6.1 but not 8.6.0 admits a second minor, and no
# finite set of samples proves otherwise. So the pattern has to have a shape
# this check can read exactly - one literal PHP minor, and any patch - and a
# pattern of any other shape is refused rather than guessed at.
#
# The two shapes are the ones this repository writes, on the decoded pattern
# (Renovate's /pattern/ slashes removed):
#   ^<major>.<minor>.\d+-cli-alpine$
#   ^1.\d+.\d+-php<major>.<minor>.\d+-bookworm$
admitted_minor() {
    local image="$1" raw pattern

    raw="$(jq -r --arg image "$image" '
        (.packageRules[]? | select((.matchPackageNames // []) | index($image)) | .allowedVersions) // empty
    ' "$renovate" | head -n 1)"

    if [[ -z "$raw" ]]; then
        return 1
    fi

    pattern="${raw#/}"
    pattern="${pattern%/}"

    case "$image" in
        php)
            if [[ "$pattern" =~ ^\^([0-9]+)\\\.([0-9]+)\\\.\\d\+\-cli-alpine\$$ ]]; then
                printf '%s.%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
                return 0
            fi
            ;;
        dunglas/frankenphp)
            if [[ "$pattern" =~ ^\^1\\\.\\d\+\\\.\\d\+\-php([0-9]+)\\\.([0-9]+)\\\.\\d\+\-bookworm\$$ ]]; then
                printf '%s.%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
                return 0
            fi
            ;;
    esac

    return 1
}

builder_minor="$(admitted_minor php || true)"
runtime_minor="$(admitted_minor dunglas/frankenphp || true)"

if [[ -z "$builder_minor" || -z "$runtime_minor" ]]; then
    echo "Could not read the PHP minor out of the Renovate rules." >&2
    echo "A rule this check cannot read exactly is refused rather than guessed at:" >&2
    echo "it has to name one PHP minor and allow any patch of it." >&2
    exit 1
fi

minor="${builder%.*}"
if [[ "$builder_minor" != "$minor" || "$runtime_minor" != "$minor" ]]; then
    printf "The php rule admits PHP %s and the frankenphp rule admits PHP %s.\n" \
        "$builder_minor" "$runtime_minor" >&2
    printf "The images run PHP %s.\n" "$minor" >&2
    echo "Both rules must admit exactly the minor the images are pinned to: a rule" >&2
    echo "that admitted a second one could move one image onto a PHP the other does" >&2
    echo "not satisfy." >&2
    exit 1
fi

printf 'The builder and the runtime both run PHP %s, and both rules admit PHP %s.\n' "$builder" "$minor"
