<?php
require_once 'simple2c2p.civix.php';

// phpcs:disable
use CRM_Simple2c2p_ExtensionUtil as E;
use Civi\Test\EndToEndInterface;


// phpcs:enable

/**
 * This is a generic test class for the extension (implemented with PHPUnit).
 * @group e2e
 */
class CRM_Simple2c2p_Simple2c2pUnitTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface
{

    /**
     * Configure the headless environment.
     */
    public function setUpHeadless()
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
        return Civi\Test::e2e()
            ->installMe(__DIR__)
            ->apply();
    }

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
//        self::assertTrue(TRUE, "The argument must be true to pass the test");
        $shortname = E::SHORT_NAME;
        self::assertEquals('simple2c2p', $shortname);
    }

    /**
     * @test
     */
    public function paymentProcessor_billingMode_ReturnError(): void
    {
        $facePaymentProcessor = [];
        $facePaymentProcessor['billing_mode'] = CRM_Core_Payment_Simple2c2p::BILLING_MODE_BUTTON;
        $pp = new CRM_Core_Payment_Simple2c2p('test', $facePaymentProcessor);
        $this->expectException(\Civi\Payment\Exception\PaymentProcessorException::class);
        $emptyParams = [];
        $pp->doPayment($emptyParams);
        self::expectExceptionMessage('2c2p - Direct payment not implemented');
    }

    /**
     * @test
     */
    public function paymentProcessor_amountIsNull_ReturnOK(): void
    {
        $facePaymentProcessor = [];
        $facePaymentProcessor['billing_mode'] = CRM_Core_Payment_Simple2c2p::BILLING_MODE_NOTIFY;
        $pp = new CRM_Core_Payment_Simple2c2p('test', $facePaymentProcessor);
        $emptyParams = [];
        $emptyParams['amount'] = 0;
        $result['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
        $result['payment_status'] = 'Completed';
        $gotResult = $pp->doPayment($emptyParams);
        self::assertEquals($result, $gotResult);
    }


}
