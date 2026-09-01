<?php

declare(strict_types=1);

namespace FrontendContact\Tests\Integration;

use FrontendContact\ContactForm;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Shared helpers for integration tests that drive ContactForm's sendEmail()/saveEmail()
 * without going through Form::___isValid() (which also handles honeypot/captcha/
 * multi-step logic that depends on your site's own FrontendForms configuration, and is
 * not something these tests are trying to verify).
 *
 * Instead this seeds ProcessWire's own wire('input')->post store and then calls
 * FrontendForms' FormValueStore::setValues() directly (the same method ___isValid()
 * calls internally to populate Form::getValues()) - so ContactForm::createDataPlaceholder()
 * sees exactly the values a real submission would produce, including running through
 * each field's own configured sanitizers, without needing to satisfy validation rules
 * (required fields, honeypot, captcha, ...) that are FrontendForms' concern, not
 * FrontendContact's.
 */
trait SeedsFormSubmissions
{
    /** @var string[] POST keys most recently seeded, so the next test starts clean */
    private array $seededPostKeys = [];

    /**
     * Get a fully constructed, real ContactForm instance via the actual module API -
     * exactly how a real page render would get one.
     */
    private function makeRealContactForm(string $id = 'contact-form'): ContactForm
    {
        return \FrontendContactTestWire::api('modules')->get('FrontendContact')->getForm($id);
    }

    /**
     * Populate wire('input')->post with the given values (keyed by the form's prefixed
     * field name, e.g. "contact-form-email") and run them through FrontendForms' own
     * FormValueStore::setValues(), so $form->getValues() / createDataPlaceholder()
     * return this data exactly as they would for a real submission.
     * @param ContactForm $form
     * @param array<string, mixed> $values
     */
    private function seedFormValues(ContactForm $form, array $values): void
    {
        $post = \FrontendContactTestWire::api('input')->post;

        // clear whatever a previous test in this run seeded, since ->post
        // is a single, shared object for the whole bootstrapped ProcessWire instance
        foreach ($this->seededPostKeys as $key) {
            unset($post->$key);
        }

        foreach ($values as $key => $value) {
            $post->set($key, $value);
        }
        $this->seededPostKeys = array_keys($values);

        $valueStore = $this->getValueStore($form);
        $valueStore->setValues();
    }

    /**
     * Directly overwrite one already-seeded field's stored value with $rawValue,
     * bypassing FrontendForms' own per-field sanitizer chain (FormValueStore::setValues()
     * would itself choke on an array value for a scalar-sanitized field - that is a
     * separate, third-party concern, not what these tests are checking). This is how
     * this suite simulates "a malicious/malformed value reached ContactForm" precisely,
     * without tripping over FrontendForms' own value-collection step first.
     *
     * Call seedFormValues() first (with a normal, valid value for $key, among others),
     * then this to override just that one key.
     */
    private function overrideStoredValue(ContactForm $form, string $key, mixed $rawValue): void
    {
        $valueStore = $this->getValueStore($form);

        $valuesRef = new ReflectionProperty($valueStore, 'values');
        $valuesRef->setAccessible(true);
        $values = $valuesRef->getValue($valueStore);
        $values[$key] = $rawValue;
        $valuesRef->setValue($valueStore, $values);
    }

    private function getValueStore(ContactForm $form): object
    {
        $ref = new ReflectionProperty($form, 'valueStore');
        $ref->setAccessible(true);
        return $ref->getValue($form);
    }

    private function callProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }
}
