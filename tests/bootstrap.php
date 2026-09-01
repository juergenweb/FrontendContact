<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap for the isolated logic tests of ContactForm.php.
 *
 * ContactForm.php declares "class ContactForm extends \FrontendForms\Form", and the real
 * Form class (from the FrontendForms library) in turn extends ProcessWire's Wire class -
 * pulling in the full ProcessWire runtime (database, hooks, modules, ...). None of that is
 * needed to test the pure decision logic covered by this suite (sanitizeHeaderValue(),
 * resolveSenderAddress(), sanitizeCustomFieldValue()): every test instantiates ContactForm
 * via ReflectionClass::newInstanceWithoutConstructor(), so no constructor of ContactForm,
 * Form, or Wire ever runs.
 *
 * What IS required, though, is that the class "FrontendForms\Form" exists and can be
 * loaded, because PHP resolves "extends Form" as soon as ContactForm.php is included -
 * before any object is even created. This minimal stand-in provides just enough of a
 * Form/Wire shape for that to work: a no-op constructor-free class plus a fake wire()
 * method that test cases can pre-load with fake "fuel" (e.g. a fake sanitizer) via the
 * public $wireValues property, so that the one branch of sanitizeCustomFieldValue() that
 * does call $this->wire('sanitizer') can be tested too.
 *
 * If you run this suite against a site that already has the real FrontendForms library
 * autoloaded, this stub is simply never used for that class (first declaration wins) -
 * but that's not the intended use here: these tests are meant to run standalone, without
 * a ProcessWire/FrontendForms installation.
 */

namespace FrontendForms {
    class Form
    {
        /** @var array<string, mixed> fake wire() "fuel", settable per test */
        public array $wireValues = [];

        public function wire($name = null, $value = null, bool $lock = false)
        {
            if ($name === null) {
                return $this;
            }
            return $this->wireValues[$name] ?? null;
        }
    }
}

/*
 * Same idea as above, for FrontendContactManager.module and FrontendContact.module: they
 * declare "class FrontendContactManager extends Process implements Module,
 * ConfigurableModule" and "class FrontendContact extends WireData implements Module,
 * ConfigurableModule" respectively - all types from ProcessWire core. Minimal stand-ins
 * so those declarations can load standalone. Process and WireData both share the same
 * fake wire() mechanism as the real ProcessWire\Wire base class they both actually
 * extend, so tests can pre-load fake "fuel" the same way for either class.
 *
 * None of the methods actually covered by tests in either file call wire() (they are the
 * pure decision-logic pieces extracted specifically to avoid that dependency), but the
 * wire() stand-in is included here too for consistency and for any future tests.
 */
namespace ProcessWire {
    interface Module
    {
    }

    interface ConfigurableModule
    {
    }

    class Wire
    {
        /** @var array<string, mixed> fake wire() "fuel", settable per test */
        public array $wireValues = [];

        public function wire($name = null, $value = null, bool $lock = false)
        {
            if ($name === null) {
                return $this;
            }
            return $this->wireValues[$name] ?? null;
        }
    }

    class Process extends Wire
    {
    }

    class WireData extends Wire
    {
    }
}

namespace {
    require __DIR__ . '/../ContactForm.php';
    require __DIR__ . '/../FrontendContactManager.module';
    require __DIR__ . '/../FrontendContact.module';
}
