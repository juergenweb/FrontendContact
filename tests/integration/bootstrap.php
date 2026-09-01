<?php

declare(strict_types=1);

/*
 * Bootstrap for the INTEGRATION test suite (phpunit-integration.xml), as opposed to
 * tests/bootstrap.php used by the fast, dependency-free unit suite (phpunit.xml).
 *
 * This one boots a REAL ProcessWire installation, so these tests hit your actual
 * database and filesystem, and - for the mail-related tests in SecurityIntegrationTest -
 * do attempt a real WireMail::send() call (see the note in SecurityIntegrationTest's own
 * class docblock for why, and what that implies for your test installation's mail
 * configuration).
 *
 * IMPORTANT:
 * - Run this ONLY against a development/test ProcessWire installation, never production.
 * - Tests that create pages/fields clean up after themselves in tearDown(), but a
 *   failed/interrupted run can leave test data behind - look for pages/fields whose
 *   name/title contains "frontendcontact-test-" if something looks off afterwards.
 *
 * CONFIGURATION - set the PW_ROOT environment variable to the absolute path of your
 * ProcessWire installation root (the directory that contains index.php, wire/ and
 * site/). Example (Windows/cmd, from this module's directory):
 *     set PW_ROOT=C:\laragon\www\webseite2
 *     vendor\bin\phpunit -c phpunit-integration.xml
 * If PW_ROOT is not set, this falls back to five directories up from this file, which
 * matches this module's default install location:
 *     site/modules/FrontendContact/tests/integration/bootstrap.php
 *     -> site/modules/FrontendContact/tests
 *     -> site/modules/FrontendContact
 *     -> site/modules
 *     -> site
 *     -> <PW root>
 *
 * Optionally set PW_TEST_USER to the name of a ProcessWire user to run the tests as
 * (needs page-add/page-edit rights on the frontend-contact-message template, and
 * permission-related rights for the permission test). If not set, this falls back to
 * the first user found with the superuser role - fine for a local dev/test install,
 * but you may want a dedicated, less-privileged test user for anything less trusted.
 */

$pwRoot = getenv('PW_ROOT');
if (!$pwRoot) {
    $pwRoot = dirname(__DIR__, 5);
}

if (!file_exists($pwRoot . '/index.php')) {
    fwrite(STDERR, "Could not find a ProcessWire installation at \"$pwRoot\".\n");
    fwrite(STDERR, "Set the PW_ROOT environment variable to your ProcessWire root (the folder containing index.php).\n");
    exit(1);
}

chdir($pwRoot);
/** @noinspection PhpIncludeInspection */
require $pwRoot . '/index.php';

// ProcessWire's own documented way of bootstrapping outside of a normal page request
// (cron jobs, CLI scripts, and - here - tests) leaves the root API instance available
// in this including scope as $wire once index.php has finished. This suite builds its
// own wire()-style helper directly on top of that $wire instance, rather than relying
// on ProcessWire's procedural wire()/ProcessWire\wire() function: whether that function
// exists (and in which namespace) depends on this installation's
// $config->useFunctionsAPI setting, and on this installation neither the bare wire()
// nor the namespaced ProcessWire\wire() turned out to be reliably callable from a test
// file outside ProcessWire's own namespace/module-loading. Building on $wire instead
// sidesteps that entirely - it is guaranteed by ProcessWire itself, regardless of that
// setting.
if (!isset($wire) || !is_object($wire)) {
    fwrite(STDERR, "ProcessWire did not bootstrap correctly from \"$pwRoot\" (no \$wire API instance found after including index.php).\n");
    exit(1);
}

$GLOBALS['__frontendContactTestPwInstance'] = $wire;

/**
 * This test suite's own, always-available replacement for ProcessWire's procedural
 * wire($name) helper. A plain global FUNCTION was deliberately not used here - two
 * earlier attempts with a function, imported per-file via "use function ... as wire;",
 * both failed with "Call to undefined function" on this installation for reasons that
 * could not be pinned down remotely. A class, referenced with a leading backslash
 * (\FrontendContactTestWire::api(...)), has none of the namespace-fallback ambiguity
 * that plain function calls have in PHP, so this suite uses that instead everywhere.
 */
if (!class_exists('FrontendContactTestWire', false)) {
    class FrontendContactTestWire
    {
        public static function api(?string $name = null)
        {
            $wireInstance = $GLOBALS['__frontendContactTestPwInstance'] ?? null;
            if (!$wireInstance) {
                throw new \RuntimeException(
                    'FrontendContactTestWire::api() was called before tests/integration/bootstrap.php finished running - the ProcessWire API instance is not available yet.'
                );
            }
            return $name === null ? $wireInstance : $wireInstance->wire($name);
        }
    }
}

// Sanity-check this suite's own helper right here, at bootstrap time - if this fails,
// something is fundamentally wrong with the bootstrap itself, and you get a clear,
// immediate error instead of a confusing "undefined function/class" report buried
// inside an unrelated test's failure output.
if (\FrontendContactTestWire::api('modules') === null) {
    fwrite(STDERR, "FrontendContactTestWire::api('modules') returned null right after bootstrap - something is wrong with this bootstrap file or your ProcessWire installation.\n");
    exit(1);
}

// run as a superuser (or the configured PW_TEST_USER) so page/field/permission API
// calls used by the tests have the rights they need
$testUsername = getenv('PW_TEST_USER');
if ($testUsername) {
    $testUser = $wire->users->get('name=' . $wire->sanitizer->pageName($testUsername));
} else {
    $testUser = $wire->users->find('roles=superuser, include=all')->first();
}
if ($testUser && $testUser->id) {
    $wire->users->setCurrentUser($testUser);
} else {
    fwrite(STDERR, "Warning: could not find a test user to run as - page saves may fail due to insufficient permissions.\n");
}
