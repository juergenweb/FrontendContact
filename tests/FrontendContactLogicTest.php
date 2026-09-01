<?php

declare(strict_types=1);

namespace FrontendContact\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\FrontendContact;
use ReflectionClass;
use ReflectionMethod;

/**
 * Isolated unit tests for the pure decision-logic helper methods on FrontendContact
 * (the main module class), plus its two static, dependency-free methods.
 *
 * These tests do NOT require a running ProcessWire installation - see
 * tests/bootstrap.php for how FrontendContact is made loadable in isolation. Only the
 * methods below are covered, because they are the only ones in FrontendContact.module
 * whose logic does not itself depend on ProcessWire's runtime (database, session,
 * fields/pages lookups, admin UI, ...):
 *
 * - getDefaultData()               - static, returns the literal default config array
 * - extractDefaultEmailFieldName() - parses the field name out of the input_defaultPWField_to token
 * - isMissingDefaultEmail()        - decides whether a default recipient e-mail is configured
 * - getEmailValue()                - static; only the branches that don't need a live PW field/page lookup
 *
 * Everything else (hooks, admin config screen, page/field lookups, mail template
 * lookups) is integration-level behavior that needs a real ProcessWire instance and is
 * out of scope for this suite.
 */
final class FrontendContactLogicTest extends TestCase
{
    private function makeModule(): FrontendContact
    {
        $ref = new ReflectionClass(FrontendContact::class);
        /** @var FrontendContact $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        return $instance;
    }

    private function callProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    // --- getDefaultData() --------------------------------------------------------------

    public function testGetDefaultDataContainsExpectedDefaultsForKeySettings(): void
    {
        $data = FrontendContact::getDefaultData();

        // regression guard for a few of the settings this session touched directly -
        // not an exhaustive check of every key, but enough to catch an accidental
        // removal/typo of the ones that matter for the fixes made in this session
        $this->assertSame('text', $data['input_emailtype']);
        $this->assertSame('', $data['input_default_to']);
        $this->assertSame('', $data['input_sender_email']);
        $this->assertSame('', $data['input_filemaxuploadsize']);
        $this->assertSame(0, $data['input_sub_action']);
        $this->assertSame(0, $data['input_log_submission']);
    }

    public function testGetDefaultDataReturnsAnArray(): void
    {
        $data = FrontendContact::getDefaultData();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    // --- extractDefaultEmailFieldName() -------------------------------------------------

    public function testExtractDefaultEmailFieldNameParsesFieldtypeEmailToken(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'extractDefaultEmailFieldName', ['123_myemailfield']);

        $this->assertSame('myemailfield', $result);
    }

    public function testExtractDefaultEmailFieldNameParsesFieldtypeImprintToken(): void
    {
        $module = $this->makeModule();

        // the "_fieldtypeimprint" suffix lives at index 2, the field name stays at index 1
        $result = $this->callProtected($module, 'extractDefaultEmailFieldName', ['123_myemailfield_fieldtypeimprint']);

        $this->assertSame('myemailfield', $result);
    }

    public function testExtractDefaultEmailFieldNameReturnsNullForEmptyToken(): void
    {
        $module = $this->makeModule();

        // Regression test for the fixed bug: explode('_', '') yields [''], so index 1
        // does not exist. This used to be accessed directly ($parts[1]) and had to be
        // made null-safe ($parts[1] ?? null).
        $result = $this->callProtected($module, 'extractDefaultEmailFieldName', ['']);

        $this->assertNull($result);
    }

    public function testExtractDefaultEmailFieldNameReturnsNullWhenTokenHasNoUnderscore(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'extractDefaultEmailFieldName', ['123']);

        $this->assertNull($result);
    }

    // --- isMissingDefaultEmail() ---------------------------------------------------------

    public function testIsMissingDefaultEmailIsFalseWhenPwFieldIsConfigured(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'isMissingDefaultEmail', [[
            'input_emailtype' => 'pwfield',
            'input_defaultPWField_to' => '123_myemailfield',
        ]]);

        $this->assertFalse($result);
    }

    public function testIsMissingDefaultEmailIsTrueWhenPwFieldTypeButNoFieldSelected(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'isMissingDefaultEmail', [[
            'input_emailtype' => 'pwfield',
            'input_defaultPWField_to' => '',
        ]]);

        $this->assertTrue($result);
    }

    public function testIsMissingDefaultEmailIsFalseWhenManualAddressIsConfigured(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'isMissingDefaultEmail', [[
            'input_emailtype' => 'text',
            'input_default_to' => 'office@example.com',
        ]]);

        $this->assertFalse($result);
    }

    public function testIsMissingDefaultEmailIsTrueWhenTextTypeButNoAddressEntered(): void
    {
        $module = $this->makeModule();

        $result = $this->callProtected($module, 'isMissingDefaultEmail', [[
            'input_emailtype' => 'text',
            'input_default_to' => '',
        ]]);

        $this->assertTrue($result);
    }

    public function testIsMissingDefaultEmailFallsBackToTextBranchWhenEmailtypeKeyIsMissing(): void
    {
        $module = $this->makeModule();

        // defensive fallback (?? null) added so this doesn't throw on a malformed config
        $result = $this->callProtected($module, 'isMissingDefaultEmail', [[
            'input_default_to' => 'office@example.com',
        ]]);

        $this->assertFalse($result);
    }

    // --- getEmailValue() (branches that don't require a live pages()/fields() lookup) ---

    public function testGetEmailValueReturnsManualAddressWhenNoPwFieldIsSelected(): void
    {
        $result = FrontendContact::getEmailValue([
            'input_defaultPWField_to' => '',
            'input_default_to' => 'office@example.com',
        ]);

        $this->assertSame('office@example.com', $result);
    }

    public function testGetEmailValueReturnsNullWhenNeitherOptionIsConfigured(): void
    {
        $result = FrontendContact::getEmailValue([
            'input_defaultPWField_to' => '',
        ]);

        $this->assertNull($result);
    }

    public function testGetEmailValueReturnsEmptyStringWhenDefaultToKeyExistsButIsBlank(): void
    {
        // documents the actual current behavior: an existing-but-blank input_default_to
        // is returned as '', not converted to null
        $result = FrontendContact::getEmailValue([
            'input_defaultPWField_to' => '',
            'input_default_to' => '',
        ]);

        $this->assertSame('', $result);
    }
}
