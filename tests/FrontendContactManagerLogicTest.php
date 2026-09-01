<?php

declare(strict_types=1);

namespace FrontendContact\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\FrontendContactManager;
use ReflectionClass;
use ReflectionMethod;

/**
 * Isolated unit tests for the pure decision-logic helper methods on
 * FrontendContactManager.
 *
 * These tests do NOT require a running ProcessWire installation - see
 * tests/bootstrap.php for how FrontendContactManager is made loadable in isolation.
 * Only the four methods below are covered, because they are the only ones in
 * FrontendContactManager.module whose logic does not itself depend on ProcessWire's
 * runtime (database, session, admin UI, ...):
 *
 * - buildSubmissionSelectors() - assembles the PW selector array from already-sanitized filter values
 * - calculatePaginationStart() - zero-based row offset for the current pagination page
 * - shouldSliceSubmissions()   - whether the result set needs to be sliced for the current page
 * - calculateRowNumber()       - the displayed (1-based) row number for a submission
 *
 * Everything else (rendering the filter form/table, querying pages, admin UI) is
 * integration-level behavior that needs a real ProcessWire instance and is out of
 * scope for this suite.
 */
final class FrontendContactManagerLogicTest extends TestCase
{
    private function makeManager(): FrontendContactManager
    {
        $ref = new ReflectionClass(FrontendContactManager::class);
        /** @var FrontendContactManager $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        return $instance;
    }

    private function callProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    // --- buildSubmissionSelectors() ---------------------------------------------------

    public function testBuildSubmissionSelectorsAlwaysIncludesTheTemplateFilter(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'buildSubmissionSelectors', [[]]);

        $this->assertSame(['template' => 'template=frontend-contact-message'], $result);
    }

    public function testBuildSubmissionSelectorsAddsOnlyTheGivenFilters(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'buildSubmissionSelectors', [[
            'subject' => 'hello world',
        ]]);

        $this->assertSame([
            'template' => 'template=frontend-contact-message',
            'subject' => 'title%=hello world',
        ], $result);
        // no unrelated filter keys should appear
        $this->assertArrayNotHasKey('mail', $result);
        $this->assertArrayNotHasKey('firstname', $result);
        $this->assertArrayNotHasKey('lastname', $result);
    }

    public function testBuildSubmissionSelectorsCombinesAllFourFilters(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'buildSubmissionSelectors', [[
            'subject' => 'a subject',
            'mail' => 'jane@example.com',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
        ]]);

        $this->assertSame([
            'template' => 'template=frontend-contact-message',
            'subject' => 'title%=a subject',
            'mail' => 'fcontact_email%=jane@example.com',
            'firstname' => 'fcontact_firstname%=Jane',
            'lastname' => 'fcontact_lastname%=Doe',
        ], $result);
    }

    public function testBuildSubmissionSelectorsUsesValuesAsIsWithoutReSanitizing(): void
    {
        $manager = $this->makeManager();

        // this method must not sanitize on its own - it trusts that the caller
        // (getAllSubmissions()) already ran every value through selectorValue()
        $result = $this->callProtected($manager, 'buildSubmissionSelectors', [[
            'mail' => 'already-sanitized-value',
        ]]);

        $this->assertSame('fcontact_email%=already-sanitized-value', $result['mail']);
    }

    // --- calculatePaginationStart() ---------------------------------------------------

    public function testCalculatePaginationStartForFirstPage(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'calculatePaginationStart', [1, 10]);

        $this->assertSame(0, $result);
    }

    public function testCalculatePaginationStartForThirdPage(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'calculatePaginationStart', [3, 10]);

        $this->assertSame(20, $result);
    }

    public function testCalculatePaginationStartWithNonDefaultPageSize(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'calculatePaginationStart', [2, 25]);

        $this->assertSame(25, $result);
    }

    // --- shouldSliceSubmissions() ------------------------------------------------------

    public function testShouldSliceSubmissionsIsFalseWhenEverythingFitsOnOnePage(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'shouldSliceSubmissions', [5, 10]);

        $this->assertFalse($result);
    }

    public function testShouldSliceSubmissionsIsFalseWhenCountExactlyMatchesPageSize(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'shouldSliceSubmissions', [10, 10]);

        $this->assertFalse($result);
    }

    public function testShouldSliceSubmissionsIsTrueWhenThereAreMoreItemsThanFitOnOnePage(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'shouldSliceSubmissions', [11, 10]);

        $this->assertTrue($result);
    }

    // --- calculateRowNumber() -----------------------------------------------------------

    public function testCalculateRowNumberWithoutOffsetStartsAtOne(): void
    {
        $manager = $this->makeManager();

        $result = $this->callProtected($manager, 'calculateRowNumber', [0, 0, false]);

        $this->assertSame(1, $result);
    }

    public function testCalculateRowNumberWithoutOffsetIgnoresStart(): void
    {
        $manager = $this->makeManager();

        // $useOffset = false means the result set was not sliced, so $start must be
        // ignored even if it is non-zero
        $result = $this->callProtected($manager, 'calculateRowNumber', [2, 20, false]);

        $this->assertSame(3, $result);
    }

    public function testCalculateRowNumberWithOffsetAddsStart(): void
    {
        $manager = $this->makeManager();

        // page 3 with 10 items per page -> start = 20, third row on that page (index 2)
        $result = $this->callProtected($manager, 'calculateRowNumber', [2, 20, true]);

        $this->assertSame(23, $result);
    }
}
