<?php

declare(strict_types=1);

namespace FrontendContact\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration test for the dedicated "frontendcontact-view" permission introduced to
 * replace the generic "page-edit" permission previously used to gate access to the
 * FrontendContact Manager admin page (submitted messages contain personal data, so
 * access should be an explicit, opt-in grant rather than tied to the broad, commonly
 * assigned page-edit permission).
 *
 * This needs a real ProcessWire instance because it checks the actual Permissions API
 * (\FrontendContactTestWire::api('permissions')) - whether the permission was really
 * created, not just whether the module declares it.
 *
 * NOTE: this reads module info via \FrontendContactTestWire::api('modules')->getModuleInfoVerbose()
 * rather than calling FrontendContactManager::getModuleInfo() directly. The static
 * method calls ProcessWire's __() translation function internally, which is only
 * available when a module file is loaded through ProcessWire's own module-loading/
 * compilation - not when required directly the way this test suite's bootstrap.php
 * does for the (fast, DB-less) unit test suite. Going through
 * \FrontendContactTestWire::api('modules') reads the same info from ProcessWire's own
 * module cache instead, which is both safer here and more representative of what
 * ProcessWire itself actually knows about the module.
 *
 * getModuleInfoVerbose() specifically (not the plain getModuleInfo()) because PW's
 * plain/non-verbose module info cache only carries a minimal set of keys (title,
 * version, summary, the singular "permission", ...) - the plural "permissions" array
 * this test also checks is verbose-only data and comes back null from the plain call.
 *
 * NOTE 2: this suite deliberately does not call ProcessWire's own procedural wire()
 * (or ProcessWire\wire()) function - both turned out to be unreliably available on at
 * least one real installation this was tested against. \FrontendContactTestWire::api()
 * (see tests/integration/bootstrap.php) is this suite's own, always-available
 * replacement, built directly on the $wire instance ProcessWire itself guarantees is
 * available after bootstrapping via index.php outside of a normal page request.
 */
final class PermissionIntegrationTest extends TestCase
{
    public function testFrontendContactViewPermissionIsDeclaredInModuleInfo(): void
    {
        $info = \FrontendContactTestWire::api('modules')->getModuleInfoVerbose('FrontendContactManager');

        $this->assertSame('frontendcontact-view', $info['permission']);
        $this->assertArrayHasKey('frontendcontact-view', $info['permissions']);
    }

    public function testFrontendContactViewPermissionExistsInProcessWire(): void
    {
        // this only passes once the module has actually been installed/refreshed in
        // your ProcessWire admin (Modules > Refresh) after picking up this change - see
        // the note in the delivery message. It is the actual regression guard: it fails
        // if the permission is ever accidentally renamed/removed from getModuleInfo()
        // without also being renamed/removed here, or if it was never created.
        $permission = \FrontendContactTestWire::api('permissions')->get('frontendcontact-view');

        $this->assertNotEquals(0, $permission->id, 'The "frontendcontact-view" permission does not exist yet - refresh modules in the PW admin (Modules > Refresh) first.');
    }

    public function testGenericPageEditIsNoLongerDeclaredAsTheGatingPermission(): void
    {
        // regression guard for the actual fix: page-edit must not silently become the
        // gating permission again (e.g. via a careless revert)
        $info = \FrontendContactTestWire::api('modules')->getModuleInfoVerbose('FrontendContactManager');

        $this->assertNotSame('page-edit', $info['permission']);
    }
}
