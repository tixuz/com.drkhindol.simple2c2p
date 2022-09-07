<?php
require_once 'simple2c2p.civix.php';
// phpcs:disable
use CRM_Simple2c2p_ExtensionUtil as E;
// phpcs:enable

/**
 * This is a generic test class for the extension (implemented with PHPUnit).
 */
class CRM_Simple2c2p_Simple2c2pUnitTest extends \PHPUnit\Framework\TestCase
{

    /**
     * The setup() method is executed before the test is executed (optional).
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * The tearDown() method is executed after the test was executed (optional).
     *
     * This can be used for cleanup.
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Simple example test case.
     *
     * Note how the function name begins with the word "test".
     */
    public function testExample(): void
    {
        self::assertTrue(TRUE, "The argument must be true to pass the test");
        $shortname = E::SHORT_NAME;
        self::assertEquals('simple2c2p', $shortname);
    }

}
