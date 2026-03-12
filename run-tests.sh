#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

# Run PHPUnit with TYPO3 testing-framework bootstrap
# No phpunit.xml.dist needed in project root
.Build/bin/phpunit \
  --bootstrap .Build/vendor/typo3/testing-framework/Resources/Core/Build/UnitTestsBootstrap.php \
  --testdox \
  --colors=always \
  Tests/Unit \
  "$@"

