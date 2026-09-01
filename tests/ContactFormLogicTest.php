<?php

declare(strict_types=1);

namespace FrontendContact\Tests;

use FrontendContact\ContactForm;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Isolated unit tests for the pure decision-logic helper methods on ContactForm.
 *
 * These tests do NOT require a running ProcessWire/FrontendForms installation - see
 * tests/bootstrap.php for how ContactForm is made loadable in isolation. Only the three
 * methods below are covered, because they are the only ones in ContactForm.php whose
 * logic does not itself depend on ProcessWire's runtime (database, session, hooks, ...):
 *
 * - sanitizeHeaderValue()       - strips CRLF/NUL from a value before it is used in a mail header
 * - resolveSenderAddress()      - resolves the From address (configured value / httpHosts / httpHost fallback)
 * - sanitizeCustomFieldValue()  - sanitizes a single custom-field value before it is saved to a PW page
 *
 * Everything else in ContactForm.php (form field rendering, page creation, sending mail,
 * ...) is integration-level behavior that needs a real ProcessWire instance and is out of
 * scope for this suite.
 */
final class ContactFormLogicTest extends TestCase
{
    private function makeContactForm(): ContactForm
    {
        $ref = new ReflectionClass(ContactForm::class);
        /** @var ContactForm $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        return $instance;
    }

    private function callProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    // --- sanitizeHeaderValue() ------------------------------------------------------

    public function testSanitizeHeaderValueStripsCarriageReturnAndLineFeed(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'sanitizeHeaderValue', [
            "Mustermann\r\nBcc: attacker@evil.com",
        ]);

        $this->assertSame('MustermannBcc: attacker@evil.com', $result);
        $this->assertStringNotContainsString("\r", $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testSanitizeHeaderValueStripsNullByte(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'sanitizeHeaderValue', ["Some\0Value"]);

        $this->assertSame('SomeValue', $result);
    }

    public function testSanitizeHeaderValueTrimsSurroundingWhitespace(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'sanitizeHeaderValue', ['  Hello World  ']);

        $this->assertSame('Hello World', $result);
    }

    public function testSanitizeHeaderValueLeavesOrdinaryValueUnchanged(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'sanitizeHeaderValue', ['Jürgen Kern']);

        $this->assertSame('Jürgen Kern', $result);
    }

    // --- resolveSenderAddress() ------------------------------------------------------

    public function testResolveSenderAddressUsesConfiguredSenderWhenPresent(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            'noreply@mycompany.com',
            ['example.com'],
            'example.com',
        ]);

        $this->assertSame('noreply@mycompany.com', $result);
    }

    public function testResolveSenderAddressFallsBackToFirstHttpHostWhenNotConfigured(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            '',
            ['example.com', 'other.example.com'],
            'evil-spoofed-host.tld', // must be ignored - httpHosts takes priority
        ]);

        $this->assertSame('noreply@example.com', $result);
    }

    public function testResolveSenderAddressFallsBackToRequestHostWhenHttpHostsIsEmpty(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            '',
            [],
            'my-fallback-host.example',
        ]);

        $this->assertSame('noreply@my-fallback-host.example', $result);
    }

    public function testResolveSenderAddressNormalizesLocalhost(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            '',
            [],
            'localhost',
        ]);

        $this->assertSame('noreply@localhost.com', $result);
    }

    public function testResolveSenderAddressSanitizesConfiguredSenderAgainstHeaderInjection(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            "attacker@evil.com\r\nBcc: victim@example.com",
            [],
            'example.com',
        ]);

        $this->assertSame('attacker@evil.comBcc: victim@example.com', $result);
        $this->assertStringNotContainsString("\r\n", $result);
    }

    public function testResolveSenderAddressSanitizesFallbackHostAgainstHeaderInjection(): void
    {
        $form = $this->makeContactForm();

        $result = $this->callProtected($form, 'resolveSenderAddress', [
            '',
            [],
            "example.com\r\nBcc: victim@example.com",
        ]);

        $this->assertSame('noreply@example.comBcc: victim@example.com', $result);
        $this->assertStringNotContainsString("\r\n", $result);
    }

    // --- sanitizeCustomFieldValue() --------------------------------------------------

    public function testSanitizeCustomFieldValuePassesThroughWhenNoSanitizerConfigured(): void
    {
        $form = $this->makeContactForm();

        // '' is the sanitizer entry used for FieldtypeOptions-backed fields
        // (select/checkbox/radio "multiple") in $fieldsmapping - array values are
        // expected here and must be left untouched for PW to sanitize on save.
        $result = $this->callProtected($form, 'sanitizeCustomFieldValue', ['', ['a', 'b', 'c']]);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function testSanitizeCustomFieldValueRejectsArrayForScalarSanitizer(): void
    {
        $form = $this->makeContactForm();

        // Regression test for the fixed "TODO: sanitize array values" bug: a scalar
        // sanitizer (e.g. "text") must never be called directly on an array, since that
        // throws a TypeError. An array reaching here means a manipulated request
        // (e.g. "fieldname[]=a&fieldname[]=b" against a normal text input).
        $result = $this->callProtected($form, 'sanitizeCustomFieldValue', ['text', ['a', 'b']]);

        $this->assertSame('', $result);
    }

    public function testSanitizeCustomFieldValueDelegatesScalarValueToConfiguredSanitizer(): void
    {
        $form = $this->makeContactForm();

        // inject a fake "sanitizer" fuel object via the stubbed wire(), to verify the
        // scalar branch actually delegates to $this->wire('sanitizer')->$sanitizer()
        $fakeSanitizer = new class {
            public array $calls = [];

            public function text($value)
            {
                $this->calls[] = $value;
                return strtoupper((string) $value);
            }
        };
        $form->wireValues['sanitizer'] = $fakeSanitizer;

        $result = $this->callProtected($form, 'sanitizeCustomFieldValue', ['text', 'hello']);

        $this->assertSame('HELLO', $result);
        $this->assertSame(['hello'], $fakeSanitizer->calls);
    }
}
