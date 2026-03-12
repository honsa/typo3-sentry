#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

# Ensure TYPO3_PATH_ROOT is set (point to project root two levels above this package)
if [ -z "${TYPO3_PATH_ROOT:-}" ]; then
  CANDIDATE_ROOT="$(cd "$(pwd)/../.." && pwd)"
  # Prefer a web root that contains index.php (common: public/index.php)
  if [ -f "$CANDIDATE_ROOT/index.php" ]; then
    TYPO3_PATH_ROOT="$CANDIDATE_ROOT"
  elif [ -f "$CANDIDATE_ROOT/public/index.php" ]; then
    TYPO3_PATH_ROOT="$CANDIDATE_ROOT/public"
  else
    # Fallback to candidate root; the testing bootstrap will report a helpful error
    TYPO3_PATH_ROOT="$CANDIDATE_ROOT"
  fi
  export TYPO3_PATH_ROOT
fi

# Run PHPUnit with TYPO3 testing-framework bootstrap
# No phpunit.xml.dist needed in project root
.Build/bin/phpunit \
  --bootstrap .Build/vendor/typo3/testing-framework/Resources/Core/Build/UnitTestsBootstrap.php \
  --testdox \
  --colors=always \
  Tests/Unit \
  $(
    # Filter out wrapper-only arguments that should not be forwarded to phpunit
    # (the user previously passed --no-interaction and -v which PHPUnit rejected).
    args=""
    for a in "$@"; do
      case "$a" in
        -v|--no-interaction)
          # ignore
          ;;
        *)
          args="$args \"$a\""
          ;;
      esac
    done
    eval "echo $args"
  )

