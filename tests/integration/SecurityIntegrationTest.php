<?php

declare(strict_types=1);

namespace FrontendContact\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the mail-content and page-save fixes made in this project's
 * security review, run against a real ProcessWire + FrontendForms + FrontendContact
 * installation (see tests/integration/bootstrap.php for setup).
 *
 * These deliberately bypass Form::___isValid() - see SeedsFormSubmissions for why - so
 * they are NOT testing FrontendForms' own validation (required fields, honeypot,
 * captcha, file rules); they test what FrontendContact does with data once it has
 * reached ContactForm::sendEmail()/saveEmail(), which is exactly the surface this
 * session's fixes touched.
 *
 * NOTE on how the outgoing mail is inspected: earlier versions of this test tried to
 * intercept WireMail::send() (and to()/subject()) via wire()->addHookBefore(), the way
 * ProcessWire mail interception is usually done. On this installation none of those
 * hooks ever fired - confirmed with a control hook on the unrelated, well-established
 * Pages::saveReady, which DID fire, so hook registration itself works here; something
 * about WireMail specifically just isn't hookable the way expected on this
 * ProcessWire/PHP version. Rather than keep chasing that, these tests now read the
 * mail's final state directly off ContactForm::getMail() (a public method ContactForm
 * already exposes for exactly this kind of introspection) after sendEmail() returns,
 * instead of capturing it via a hook. This does mean sendEmail() still calls the real
 * $mail->send() internally - on a local/dev install with no mail transport configured,
 * that call fails fast and harmlessly (as observed: no hangs, no delivered mail), but
 * if your test install DOES have a working mail transport configured, these tests will
 * cause a real send attempt every time they run. Point PW_ROOT at a dev/test
 * installation without live mail delivery configured, same as documented in
 * tests/integration/bootstrap.php.
 *
 * Every test that changes the FrontendContact module configuration restores the
 * original configuration in tearDown() - but if a run is interrupted (e.g. killed
 * mid-test), your module configuration may be left in a test state. Check the
 * FrontendContact module configuration screen if anything looks off after an
 * interrupted run.
 */
final class SecurityIntegrationTest extends TestCase
{
    use SeedsFormSubmissions;

    // must match the $id passed to makeRealContactForm() (its default, and this
    // module's own default form ID) - kept as an explicit constant here rather than
    // read back off the ContactForm instance, since this test does not rely on getID()
    // being publicly callable from outside the FrontendForms/ContactForm class hierarchy
    private const FORM_ID = 'contact-form';

    /** @var array<string,mixed>|null original FrontendContact module config, saved in setUp() */
    private ?array $originalConfig = null;

    /** @var int[] page IDs created during a test, deleted in tearDown() */
    private array $createdPageIds = [];

    protected function setUp(): void
    {
        $this->originalConfig = \FrontendContactTestWire::api('modules')->getConfig('FrontendContact');
    }

    protected function tearDown(): void
    {
        if ($this->originalConfig !== null) {
            \FrontendContactTestWire::api('modules')->saveConfig('FrontendContact', $this->originalConfig);
        }
        foreach ($this->createdPageIds as $pageId) {
            $page = \FrontendContactTestWire::api('pages')->get($pageId);
            if ($page && $page->id) {
                $page->delete(true);
            }
        }
        $this->createdPageIds = [];
    }

    private function setModuleConfig(array $overrides): void
    {
        $config = $this->originalConfig;
        foreach ($overrides as $key => $value) {
            $config[$key] = $value;
        }
        \FrontendContactTestWire::api('modules')->saveConfig('FrontendContact', $config);
    }

    // --- #2: HTML injection in the mail body is escaped, not delivered raw -----------

    public function testHtmlInMessageFieldIsEscapedInTheOutgoingMailBody(): void
    {
        $this->setModuleConfig(['input_sub_action' => 0]); // mail only, no page save

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'attacker@example.com',
            self::FORM_ID . '-message' => '<script>alert(document.cookie)</script> hello',
        ]);

        $form->sendEmail(0);

        $mail = $form->getMail();
        $this->assertStringNotContainsString('<script>', (string) $mail->bodyHTML, 'A raw, unescaped <script> tag reached the outgoing mail body.');
    }

    // --- #3: header values are stripped of CRLF end-to-end ----------------------------

    public function testCrlfInSubjectDoesNotReachTheOutgoingMailHeader(): void
    {
        $this->setModuleConfig(['input_sub_action' => 0]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'attacker@example.com',
            self::FORM_ID . '-message' => 'test message',
            self::FORM_ID . '-subject' => "Hi\r\nX-Injected-Header: evil",
        ]);

        $form->sendEmail(0);

        $mail = $form->getMail();
        // The actual security property is that no raw line break reaches the subject
        // header - once that's true, a literal "\r\n" can no longer split the subject
        // into a second header line, so "X-Injected-Header: evil" becomes inert text
        // sitting inside the (now single-line) subject rather than a working injected
        // header - it is fine, and expected, for that substring to still be visible in
        // the harmless subject content. (An earlier version of this test also asserted
        // the substring was gone entirely, which doesn't reflect an actual security
        // requirement and failed here because FrontendForms' own field-level sanitizer
        // already collapses "\r\n" into a single space before ContactForm ever sees the
        // value - so the module's own sanitizeHeaderValue() had nothing left to strip.)
        $this->assertStringNotContainsString("\r\n", (string) $mail->subject);
        $this->assertStringNotContainsString("\r", (string) $mail->subject);
        $this->assertStringNotContainsString("\n", (string) $mail->subject);
    }

    public function testCrlfInNameDoesNotReachTheFromNameHeader(): void
    {
        $this->setModuleConfig(['input_sub_action' => 0]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'attacker@example.com',
            self::FORM_ID . '-message' => 'test message',
            self::FORM_ID . '-name' => "Eve\r\nBcc: victim@example.com",
        ]);

        $form->sendEmail(0);

        $mail = $form->getMail();
        $this->assertStringNotContainsString("\r\n", (string) $mail->fromName);
        // NOTE: this may pass even without ContactForm's own sanitizeHeaderValue() fix,
        // since FrontendForms' own field-level "text" sanitizer likely also strips
        // control characters - see the note in SecurityIntegrationTest's class docblock.
        // That does not make this test pointless: it verifies the actually-relevant
        // security property (the final header is safe), regardless of which layer
        // enforces it.
    }

    // --- #4: sender (From) address resolution ------------------------------------------

    public function testConfiguredSenderAddressIsUsedAsFrom(): void
    {
        $this->setModuleConfig([
            'input_sub_action' => 0,
            'input_sender_email' => 'noreply@configured-example.com',
        ]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'visitor@example.com',
            self::FORM_ID . '-message' => 'test message',
        ]);

        $form->sendEmail(0);

        $mail = $form->getMail();
        $this->assertSame('noreply@configured-example.com', (string) $mail->from);
    }

    public function testEmptySenderAddressFallsBackToHttpHosts(): void
    {
        $this->setModuleConfig([
            'input_sub_action' => 0,
            'input_sender_email' => '',
        ]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'visitor@example.com',
            self::FORM_ID . '-message' => 'test message',
        ]);

        $form->sendEmail(0);

        $mail = $form->getMail();

        $httpHosts = \FrontendContactTestWire::api('config')->httpHosts;
        $expectedHost = $httpHosts[0] ?? \FrontendContactTestWire::api('config')->httpHost;
        if ($expectedHost === 'localhost') {
            $expectedHost = 'localhost.com';
        }

        $this->assertSame('noreply@' . $expectedHost, (string) $mail->from);
    }

    // --- #6: the "Uploaded files" row in the mail body ---------------------------------

    public function testUploadedFilesRowIsHiddenWhenFileUploadIsDisabled(): void
    {
        $this->setModuleConfig([
            'input_sub_action' => 0,
            'input_fileUploadMultiple_show' => 0,
        ]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'visitor@example.com',
            self::FORM_ID . '-message' => 'test message',
        ]);

        $form->sendEmail(0);

        $body = (string) $form->getMail()->bodyHTML;

        // Check both the English label and its shipped German translation (see the
        // sibling test below for why - the mail body renders in German on a real
        // install, and checking only the English string here would let this test pass
        // even if the "hide when disabled" logic were broken, since a wrongly-shown
        // row would read "Hochgeladene Dateien", not "Uploaded files").
        $this->assertStringNotContainsString(
            'Uploaded files',
            $body,
            'The "Uploaded files" row must not appear in the mail body when file upload is disabled in the module configuration.'
        );
        $this->assertStringNotContainsString(
            'Hochgeladene Dateien',
            $body,
            'The "Uploaded files" row must not appear in the mail body when file upload is disabled in the module configuration (checked via its shipped German translation too).'
        );
    }

    public function testUploadedFilesRowUsesItsOwnLabelAndShowsADashWhenNoFileWasUploaded(): void
    {
        $this->setModuleConfig([
            'input_sub_action' => 0,
            'input_fileUploadMultiple_show' => 1,
        ]);

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'visitor@example.com',
            self::FORM_ID . '-message' => 'test message',
        ]);
        // deliberately not seeding anything for the file field - simulates a real
        // submission where this (optional) field was simply left empty

        $form->sendEmail(0);

        $body = (string) $form->getMail()->bodyHTML;

        // The dedicated label goes through ProcessWire's __() translation function, and
        // this module ships a German translation for it (see
        // languages/de-frontendcontact.csv: "Uploaded files" -> "Hochgeladene Dateien").
        // On an installation whose active language is German (as confirmed against a
        // real install - the rest of the mail body renders in German too, e.g.
        // "Vorname"/"E-Mail"/"Betreff"), the mail therefore shows the translated text,
        // not the literal English string. Accepting either keeps this test verifying
        // what actually matters - that the row uses the module's own dedicated,
        // translatable label rather than whatever label an editor configured on the
        // underlying FrontendForms field itself - without assuming a specific active
        // site language. If you add another language file for this string, add its
        // translation to this list too.
        $dedicatedLabelVariants = ['Uploaded files', 'Hochgeladene Dateien'];
        $foundVariant = null;
        foreach ($dedicatedLabelVariants as $variant) {
            if (str_contains($body, $variant)) {
                $foundVariant = $variant;
                break;
            }
        }
        $this->assertNotNull(
            $foundVariant,
            'The mail body should use the dedicated "Uploaded files" label (or one of its shipped translations: '
            . implode(', ', $dedicatedLabelVariants) . '), not the form field\'s own configured label.'
        );

        $this->assertMatchesRegularExpression(
            '/id="' . preg_quote(self::FORM_ID, '/') . '-fileuploadmultiple"[^>]*>.*?<span class="value">-<\/span>/s',
            $body,
            'When no file was uploaded, the value shown for the uploaded-files row should be a dash ("-"), not empty.'
        );
    }

    // --- #5: an array value for a normally-scalar field does not crash the page save --

    public function testArrayValueForMessageFieldDoesNotCrashPageSaveAndIsStoredEmpty(): void
    {
        $this->setModuleConfig(['input_sub_action' => 1]); // save as page only

        $form = $this->makeRealContactForm();
        $this->seedFormValues($form, [
            self::FORM_ID . '-email' => 'visitor@example.com',
            self::FORM_ID . '-message' => 'a normal, valid message',
        ]);

        // simulate a manipulated request where the (normally scalar) message field
        // arrived as an array - e.g. "contact-form-message[]=a&contact-form-message[]=b"
        $this->overrideStoredValue($form, self::FORM_ID . '-message', ['malicious', 'array', 'value']);

        $pageId = $this->callProtected($form, 'saveEmail');
        if ($pageId) {
            $this->createdPageIds[] = $pageId;
        }

        $this->assertNotSame(0, $pageId, 'saveEmail() did not save a page at all - check that the frontend-contact-message template/parent page exist on this installation.');

        $page = \FrontendContactTestWire::api('pages')->get($pageId);
        $this->assertTrue($page->id > 0);
        $this->assertSame('', (string) $page->fcontact_message, 'An array value for the message field should have been rejected (stored as empty), not saved as-is or crashed.');

        // the email field, which we did NOT tamper with, should have saved normally -
        // proof that only the tampered field was affected, not the whole save.
        //
        // NOTE: ContactForm::createDataPlaceholder() has its own, separate, intentional
        // feature: if the CURRENT ProcessWire user is logged in, it silently overrides
        // whatever was submitted in the email field with that user's own profile email
        // (see the "if ($this->wire('user')->isLoggedin())" block). This test suite runs
        // as a superuser (see tests/integration/bootstrap.php) so that it has the
        // page-add/page-edit rights saveEmail() needs, which means that feature is
        // always active here too - so the expected value has to be computed the same way
        // the module itself computes it, not hardcoded to the value this test seeded.
        $expectedEmail = \FrontendContactTestWire::api('user')->isLoggedin()
            ? (string) \FrontendContactTestWire::api('user')->email
            : 'visitor@example.com';
        $this->assertSame($expectedEmail, (string) $page->fcontact_email);
    }
}
