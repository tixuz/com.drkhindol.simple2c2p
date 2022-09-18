<?php
/*
 * Copyright (C) 2007
 * Licensed to CiviCRM under the Academic Free License version 3.0.
 *
 * Written and contributed by Ideal Solution, LLC (http://www.idealso.com)
 *
 */

/**
 *
 * @package CRM
 * @author Marshal Newrock <marshal@idealso.com>
 */

use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;

/**
 * Simple2c2p payment processor
 */
class CRM_Core_Payment_Simple2c2p extends CRM_Core_Payment
{
    const LENTRXNID = 10;
    const MODULE = 'md';
    const INVOICEID = 'iid';
    const ORDERID = 'oid';
    const CONTACTID = 'cid';
    const CONTRIBUTIONID = 'crid';
    const PARTICIPANTID = 'pid';
    const EVENTID = 'eid';
    protected $_mode;
    protected $_secretKey;
    protected $_merchantID;
    protected $_gatewayURL;
    protected $_doDirectPaymentResult = [];
    const PAYMENT_INQUERY_RESPONCE_SUCCESS = "0000";
    const PAYMENT_INQUERY_RESPONCE_CANCEL = "0003";
    const PAYMENT_INQUERY_RESPONCE = array(
        "0000" => "Successful",
        "0001" => "Transaction is pending", // Pending
        "0003" => "Transaction is cancelled",
        "0999" => "System error",
        "2001" => "Transaction in progress", //In Progress
        "2002" => "Transaction not found",
        "2003" => "Failed To Inquiry",
        "4001" => "Refer to card issuer",
        "4002" => "Refer to issuer's special conditions",
        "4003" => "Invalid merchant ID",
        "4004" => "Pick up card",
        "4005" => "Do not honor",
        "4006" => "Error",
        "4007" => "Pick up card, special condition",
        "4008" => "Honor with ID",
        "4009" => "Request in progress",
        "4010" => "Partial amount approved",
        "4011" => "Approved VIP",
        "4012" => "Invalid Transaction",
        "4013" => "Invalid Amount",
        "4014" => "Invalid Card Number",
        "4015" => "No such issuer",
        "4016" => "Approved, Update Track 3",
        "4017" => "Customer Cancellation",
        "4018" => "Customer Dispute",
        "4019" => "Re-enter Transaction",
        "4020" => "Invalid Response",
        "4021" => "No Action Taken",
        "4022" => "Suspected Malfunction",
        "4023" => "Unacceptable Transaction Fee",
        "4024" => "File Update Not Supported by Receiver",
        "4025" => "Unable to Locate Record on File",
        "4026" => "Duplicate File Update Record",
        "4027" => "File Update Field Edit Error",
        "4028" => "File Update File Locked Out",
        "4029" => "File Update not Successful",
        "4030" => "Format Error",
        "4031" => "Bank Not Supported by Switch",
        "4032" => "Completed Partially",
        "4033" => "Expired Card - Pick Up",
        "4034" => "Suspected Fraud - Pick Up",
        "4035" => "Restricted Card - Pick Up",
        "4036" => "Allowable PIN Tries Exceeded",
        "4037" => "No Credit Account",
        "4038" => "Allowable PIN Tries Exceeded",
        "4039" => "No Credit Account",
        "4040" => "Requested Function not Supported",
        "4041" => "Lost Card - Pick Up",
        "4042" => "No Universal Amount",
        "4043" => "Stolen Card - Pick Up",
        "4044" => "No Investment Account",
        "4045" => "Settlement Success",
        "4046" => "Settlement Fail",
        "4047" => "Cancel Success",
        "4048" => "Cancel Fail",
        "4049" => "No Transaction Reference Number",
        "4050" => "Host Down",
        "4051" => "Insufficient Funds",
        "4052" => "No Cheque Account",
        "4053" => "No Savings Account",
        "4054" => "Expired Card",
        "4055" => "Incorrect PIN",
        "4056" => "No Card Record",
        "4057" => "Transaction Not Permitted to Cardholder",
        "4058" => "Transaction Not Permitted to Terminal",
        "4059" => "Suspected Fraud",
        "4060" => "Card Acceptor Contact Acquirer",
        "4061" => "Exceeds Withdrawal Amount Limits",
        "4062" => "Restricted Card",
        "4063" => "Security Violation",
        "4064" => "Original Amount Incorrect",
        "4065" => "Exceeds Withdrawal Frequency Limit",
        "4066" => "Card Acceptor Call Acquirer Security",
        "4067" => "Hard Capture - Pick Up Card at ATM",
        "4068" => "Response Received Too Late",
        "4069" => "Reserved",
        "4070" => "Settle amount cannot exceed authorized amount",
        "4071" => "Inquiry Record Not Exist",
        "4072" => "Promotion not allowed in current payment method",
        "4073" => "Promotion Limit Reached",
        "4074" => "Reserved",
        "4075" => "Allowable PIN Tries Exceeded",
        "4076" => "Invalid Credit Card Format",
        "4077" => "Invalid Expiry Date Format",
        "4078" => "Invalid Three Digits Format",
        "4079" => "Reserved",
        "4080" => "User Cancellation by Closing Internet Browser",
        "4081" => "Unable to Authenticate Card Holder",
        "4082" => "Reserved",
        "4083" => "Reserved",
        "4084" => "Reserved",
        "4085" => "Reserved",
        "4086" => "ATM Malfunction",
        "4087" => "No Envelope Inserted",
        "4088" => "Unable to Dispense",
        "4089" => "Administration Error",
        "4090" => "Cut-off in Progress",
        "4091" => "Issuer or Switch is Inoperative",
        "4092" => "Financial Insititution Not Found",
        "4093" => "Trans Cannot Be Completed",
        "4094" => "Duplicate Transmission",
        "4095" => "Reconcile Error",
        "4096" => "System Malfunction",
        "4097" => "Reconciliation Totals Reset",
        "4098" => "MAC Error",
        "4099" => "Unable to Complete Payment",
        "4110" => "Settled",
        "4120" => "Refunded",
        "4121" => "Refund Rejected",
        "4122" => "Refund Failed",
        "4130" => "Chargeback",
        "4131" => "Chargeback Rejected",
        "4132" => "Chargeback Failed",
        "4140" => "Transaction Does Not Exist",
        "4200" => "Tokenization Successful",
        "4201" => "Tokenization Failed",
        "5002" => "Timeout",
        "5003" => "Invalid Message",
        "5004" => "Invalid Profile (Merchant) ID",
        "5005" => "Duplicated Invoice",
        "5006" => "Invalid Amount",
        "5007" => "Insufficient Balance",
        "5008" => "Invalid Currency Code",
        "5009" => "Payment Expired",
        "5010" => "Payment Canceled By Payer",
        "5011" => "Invalid Payee ID",
        "5012" => "Invalid Customer ID",
        "5013" => "Account Does Not Exist",
        "5014" => "Authentication Failed",
        "5015" => "Customer paid more than transaction amount",
        "5016" => "Customer paid less than transaction amount",
        "5017" => "Paid Expired",
        "5018" => "Reserved",
        "5019" => "No-Action From WebPay",
        "5998" => "Internal Error",
        "6012" => "Invalid Transaction",
        "6101" => "Invalid request message",
        "6102" => "Required Payload",
        "6103" => "Invalid JWT data",
        "6104" => "Required merchantId",
        "6105" => "Required paymentChannel",
        "6106" => "Required authCode",
        "6107" => "Invalid merchantId",
        "6108" => "Invalid paymentChannel",
        "6109" => "paymentChannel is not configured",
        "6110" => "Unable to retrieve usertoken",
        "7012" => "Invalid Transaction",
        "9004" => "The value is not valid",
        "9005" => "Some mandatory fields are missing",
        "9006" => "This field exceeded its authorized length",
        "9007" => "Invalid merchant",
        "9008" => "Invalid payment expiry",
        "9009" => "Amount is invalid",
        "9010" => "Invalid Currency Code",
        "9012" => "paymentItem name is required",
        "9013" => "paymentItem quantity is required",
        "9014" => "paymentItem amount is required",
        "9015" => "Existing Invoice Number",
        "9035" => "Payment failed",
        "9037" => "Merchant configuration is missing",
        "9038" => "Failed To Generate Token",
        "9039" => "The merchant frontend URL is missing",
        "9040" => "The token is invalid",
        "9041" => "Payment token already used",
        "9042" => "Hash value mismatch",
        "9057" => "Payment options are invalid",
        "9058" => "Payment channel invalid",
        "9059" => "Payment channel unauthorized",
        "9060" => "Payment channel unconfigured",
        "9078" => "Promotion code does not exist",
        "9080" => "Tokenization not allowed",
        "9088" => "SubMerchant is required",
        "9089" => "Duplicated SubMerchant",
        "9090" => "SubMerchant Not Found",
        "9091" => "Invalid Sub Merchant ID",
        "9092" => "Invalid Sub Merchant invoiceNo",
        "9093" => "Existing Sub Merchant Invoice Number",
        "9094" => "Invalid Sub Merchant Amount",
        "9095" => "Sub Merchant Amount mismatch",
        "9901" => "Invalid invoicePrefix",
        "9902" => "allowAccumulate is required",
        "9903" => "maxAccumulateAmount is required",
        "9904" => "recurringInterval or ChargeOnDate is required",
        "9905" => "recurringCount is required",
        "9906" => "recurringInterval or ChargeOnDate is required",
        "9907" => "Invalid ChargeNextDate",
        "9908" => "Invalid ChargeOnDate",
        "9909" => "chargeNextDate is required",
        "9990" => "Request to merchant front end has failed",
        "9991" => "Request merchant secure has failed",
        "9992" => "Request payment secure has failed",
        "9993" => "An unknown error has occured",
        "9994" => "Request DB service has failed",
        "9995" => "Request payment service has failed",
        "9996" => "Request Qwik service has failed",
        "9997" => "Request user preferences has failed",
        "9998" => "Request store card has failed",
        "9999" => "Request to merchant backend has failed"
    );

    /**
     * This support variable is used to allow the capabilities supported by the payment processor to be set from unit tests
     *   so that we don't need to create a lot of new processors to test combinations of features.
     * Initially these capabilities are set to TRUE, however they can be altered by calling the setSupports function directly from outside the class.
     * @var bool[]
     */
    protected $supports = [
        'MultipleConcurrentPayments' => FALSE,
        'EditRecurringContribution' => FALSE,
        'CancelRecurringNotifyOptional' => FALSE,
        'BackOffice' => FALSE,
        'NoEmailProvided' => TRUE,
        'CancelRecurring' => FALSE,
        'FutureRecurStartDate' => FALSE,
        'Refund' => FALSE,
    ];

    /**
     * @param $params
     * @return string
     */
    private static function getEmailFromParams($params): string
    {
        $email = "";
        if (array_key_exists("email-5", $params)) {
            $email = $params['email-5'];
        }
        if ($email == "") {
            if (array_key_exists("email", $params)) {
                $email = $params['email'];
            }
        }
        return $email;
    }

    /**
     * @param $params
     * @return array
     */
    private static function getContactIdAndDisplayNameFromParams(&$params): array
    {
        $contact_id = null;
        $displayName = "";
        if (array_key_exists("contactID", $params)) {
            $contact_id = $params['contactID'];
            if ($contact_id) {
                $displayName = CRM_Contact_BAO_Contact::displayName($contact_id);
            }
        }
        return array($contact_id, $displayName);
    }

    private function getReturnUrl($params, $component = 'contribute')
    {
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
        $processor_id = $this->_paymentProcessor['id']; //Get processor_name from 2C2P PGW Dashboard
        $qfKey = CRM_Utils_Array::value('qfKey', $params);
        $invoice_id = CRM_Utils_Array::value('invoiceID', $params);
        if (!isset($params['orderID'])) {
            $params['orderID'] = substr($invoice_id, 0, CRM_Core_Payment_Simple2c2p::LENTRXNID);
        }
        $order_id = CRM_Utils_Array::value('orderID', $params);
        $contact_id = CRM_Utils_Array::value('cid', $params);
        $contribution_id = CRM_Utils_Array::value('contributionID', $params);
        $destination = CRM_Utils_Array::value('destination', $params);
        $qfKey = CRM_Utils_Array::value('qfKey', $params);
        $module = 'contribute';
        $pid = CRM_Utils_Array::value('participantID', $params);
        $eid = CRM_Utils_Array::value('eventID', $params);
        if ($component == 'event') {
            $module = 'event';
        }

        $query = [
            'processor_id' => $processor_id,
            'processor_name' => $processor_name,
            self::MODULE => $module,
            self::INVOICEID => $invoice_id,
            self::ORDERID => $order_id,
            self::CONTACTID => $contact_id,
            self::CONTRIBUTIONID => $contribution_id,
            self::PARTICIPANTID => $pid,
            self::EVENTID => $eid,
            'destination' => $destination,
            'qfKey' => $qfKey,
        ];

        $url = CRM_Utils_System::getNotifyUrl(
            'civicrm/payment/ipn/' . $processor_id,
            $query,
            TRUE,
            NULL,
            FALSE,
            TRUE
        );
        return $url;
//        return (stristr($url, '.')) ? $url : '';

    }


    /**
     * @param $result
     * @return mixed
     */
    private static function getEmptyComplitedPaymentResult()
    {
        // The function needs to cope with the possibility of it being zero
        // this is because historically it was thought some processors
        // might want to do something with $0 amounts. It is unclear if this is the
        // case but it is baked in now.
        $result = [];
        $result['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
        $result['payment_status'] = 'Completed';
        return $result;
    }

    /**
     * @param $params
     * @return mixed
     */
    private static function castParamsToPropertyBug(&$params)
    {
        return \Civi\Payment\PropertyBag::cast($params);
    }

    /**
     * Set result from do Direct Payment for test purposes.
     *
     * @param array $doDirectPaymentResult
     *  Result to be returned from test.
     */
    public function setDoDirectPaymentResult($doDirectPaymentResult)
    {
        $this->_doDirectPaymentResult = $doDirectPaymentResult;
        if (empty($this->_doDirectPaymentResult['trxn_id'])) {
            $this->_doDirectPaymentResult['trxn_id'] = [];
        } else {
            $this->_doDirectPaymentResult['trxn_id'] = (array)$doDirectPaymentResult['trxn_id'];
        }
    }

    /**
     * Constructor.
     *
     * @param string $mode
     *   The mode of operation: live or test.
     *
     * @param array $paymentProcessor
     */
    public function __construct($mode, &$paymentProcessor)
    {
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_secretKey = $paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $this->_merchantID = $paymentProcessor['user_name'];  //Get MerchantID when opening account with 2C2P
        $this->_gatewayURL = $this->_paymentProcessor['url_site'];

    }

    /**
     * Does this payment processor support refund?
     *
     * @return bool
     */
    public function supportsRefund()
    {
        return $this->supports['Refund'];
    }

    /**
     * Should the first payment date be configurable when setting up back office recurring payments.
     *
     * We set this to false for historical consistency but in fact most new processors use tokens for recurring and can support this
     *
     * @return bool
     */
    public function supportsFutureRecurStartDate()
    {
        return $this->supports['FutureRecurStartDate'];
    }

    /**
     * Can more than one transaction be processed at once?
     *
     * In general processors that process payment by server to server communication support this while others do not.
     *
     * In future we are likely to hit an issue where this depends on whether a token already exists.
     *
     * @return bool
     */
    protected function supportsMultipleConcurrentPayments()
    {
        return $this->supports['MultipleConcurrentPayments'];
    }

    /**
     * Checks if back-office recurring edit is allowed
     *
     * @return bool
     */
    public function supportsEditRecurringContribution()
    {
        return $this->supports['EditRecurringContribution'];
    }

    /**
     * Are back office payments supported.
     *
     * e.g paypal standard won't permit you to enter a credit card associated
     * with someone else's login.
     * The intention is to support off-site (other than paypal) & direct debit but that is not all working yet so to
     * reach a 'stable' point we disable.
     *
     * @return bool
     */
    protected function supportsBackOffice()
    {
        return $this->supports['BackOffice'];
    }

    /**
     * Does the processor work without an email address?
     *
     * The historic assumption is that all processors require an email address. This capability
     * allows a processor to state it does not need to be provided with an email address.
     * NB: when this was added (Feb 2020), the Manual processor class overrides this but
     * the only use of the capability is in the webform_civicrm module.  It is not currently
     * used in core but may be in future.
     *
     * @return bool
     */
    protected function supportsNoEmailProvided()
    {
        return $this->supports['NoEmailProvided'];
    }

    /**
     * Does this processor support cancelling recurring contributions through code.
     *
     * If the processor returns true it must be possible to take action from within CiviCRM
     * that will result in no further payments being processed. In the case of token processors (e.g
     * IATS, eWay) updating the contribution_recur table is probably sufficient.
     *
     * @return bool
     */
    protected function supportsCancelRecurring()
    {
        return $this->supports['CancelRecurring'];
    }

    /**
     * Does the processor support the user having a choice as to whether to cancel the recurring with the processor?
     *
     * If this returns TRUE then there will be an option to send a cancellation request in the cancellation form.
     *
     * This would normally be false for processors where CiviCRM maintains the schedule.
     *
     * @return bool
     */
    protected function supportsCancelRecurringNotifyOptional()
    {
        return $this->supports['CancelRecurringNotifyOptional'];
    }

    /**
     * Set the return value of support functions. By default it is TRUE
     *
     */
    public function setSupports(array $support)
    {
        $this->supports = array_merge($this->supports, $support);
    }

    /**
     * Make a payment by interacting with an external payment processor.
     *
     * @param array|PropertyBag $params
     *   This may be passed in as an array or a \Civi\Payment\PropertyBag
     *   It holds all the values that have been collected to make the payment (eg. amount, address, currency, email).
     *
     * These values are documented at https://docs.civicrm.org/dev/en/latest/extensions/payment-processors/create/#available-parameters
     * h
     *   You can explicitly cast to PropertyBag and then work with that to get standardised keys and helpers to interact with the values passed in.
     *   See
     *   Also https://docs.civicrm.org/dev/en/latest/extensions/payment-processors/create/#introducing-propertybag-objects explains how to interact with params as a property bag.
     *   Passed by reference to comply with the parent function but **should not be altered**.
     * @param string $component
     *   Component is either 'contribution' or 'event' and is primarily used to determine the url
     *   to return the browser to. (Membership purchases come through as 'contribution'.)
     *
     * @return array
     *   Result array:
     *   - MUST contain payment_status (Completed|Pending)
     *   - MUST contain payment_status_id
     *   - MAY contain trxn_id
     *   - MAY contain fee_amount
     *   See: https://lab.civicrm.org/dev/financial/-/issues/141
     *
     * @throws PaymentProcessorException
     */
    public function doPayment(&$params, $component = 'contribute')
    {

        /* @var \Civi\Payment\PropertyBag $propertyBag */
        $propertyBag = self::castParamsToPropertyBug($params);

        if ($propertyBag->getAmount() == 0) {
            return self::getEmptyComplitedPaymentResult();
        }

        $this->_component = $component;

        if (!defined('CURLOPT_SSLCERT')) {
            throw new PaymentProcessorException('2c2p - Gateway requires curl with SSL support');
        }

        if ($this->_paymentProcessor['billing_mode'] != CRM_Core_Payment_Simple2c2p::BILLING_MODE_NOTIFY) {
            throw new PaymentProcessorException('2c2p - Direct payment not implemented');
        }


        CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $propertyBag);


        $webPaymentUrl = $this->get_webPaymentUrl($params, $component);


        // Print the tpl to redirect and send POST variables to Getaway.

        $this->gotoPaymentGateway($webPaymentUrl);

        CRM_Utils_System::civiExit();

        exit;

    }

    /**
     * Submit a refund payment
     *
     * @param array $params
     *   Assoc array of input parameters for this transaction.
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     *
     */
    public function doRefund(&$params)
    {
    }

    /**
     * This function checks to see if we have the right config values.
     *
     * @return string
     *   the error message if any
     */
    public function checkConfig()
    {
        return NULL;
    }

    /**
     * Get an array of the fields that can be edited on the recurring contribution.
     *
     * Some payment processors support editing the amount and other scheduling details of recurring payments, especially
     * those which use tokens. Others are fixed. This function allows the processor to return an array of the fields that
     * can be updated from the contribution recur edit screen.
     *
     * The fields are likely to be a subset of these
     *  - 'amount',
     *  - 'installments',
     *  - 'frequency_interval',
     *  - 'frequency_unit',
     *  - 'cycle_day',
     *  - 'next_sched_contribution_date',
     *  - 'end_date',
     *  - 'failure_retry_day',
     *
     * The form does not restrict which fields from the contribution_recur table can be added (although if the html_type
     * metadata is not defined in the xml for the field it will cause an error.
     *
     * Open question - would it make sense to return membership_id in this - which is sometimes editable and is on that
     * form (UpdateSubscription).
     *
     * @return array
     */
    public function getEditableRecurringScheduleFields()
    {
        return ['amount', 'next_sched_contribution_date'];
    }

    /**
     * @return bool
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     * this function handles notification from the payment server
     */
    public function handlePaymentNotification()
    {
        $q = explode('/', $_GET['q']);
        $paymentProcessorID = array_pop($q);

        $params = array_merge($_GET, $_REQUEST);
        $paymentProcessor =
            civicrm_api3(
                'payment_processor',
                'getsingle',
                ['id' => $paymentProcessorID]
            );
        $this->_paymentProcessor = $paymentProcessor;
        $this->_secretKey = $paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $this->_merchantID = $paymentProcessor['user_name'];  //Get MerchantID when opening account with 2C2P
        $this->_gatewayURL = $this->_paymentProcessor['url_site'];

        $this->processPaymentNotification($params);
    }

    /**
     * @return bool
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     * this function handles notification from the payment server
     */
    public function processPaymentNotification($params)
    {
//        $failureUrl = strval();
        CRM_Core_Error::debug_var('getbackparams', $params);
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
        $processor_id = $this->_paymentProcessor['id']; //Get processor_name from 2C2P PGW Dashboard
        $payment_instrument_id = $this->_paymentProcessor['payment_instrument_id'];
        $qfKey = CRM_Utils_Array::value('qfKey', $params);
        $invoice_id = CRM_Utils_Array::value(self::INVOICEID, $params);
        $order_id = CRM_Utils_Array::value(self::ORDERID, $params);
        $contact_id = CRM_Utils_Array::value(self::CONTACTID, $params);
        $contribution_id = CRM_Utils_Array::value(self::CONTRIBUTIONID, $params);
        $destination = CRM_Utils_Array::value('destination', $params);
        $module = CRM_Utils_Array::value(self::MODULE, $params);
        $participant_id = CRM_Utils_Array::value(self::PARTICIPANTID, $params);
        $event_id = CRM_Utils_Array::value(self::EVENTID, $params);
        $contribution = self::getContribution($contribution_id);
        $okUrl = "https://kun.uz";
        $notOKUrl = "https://qalampir.uz";
        $encodedPaymentResponse = $params['paymentResponse'];
        $paymentResponse = CRM_Simple2c2p_Utils::getDecodedPayload64($encodedPaymentResponse);
        CRM_Core_Error::debug_var('getbackparams', $paymentResponse);
        $token = self::getPaymentTokenViaInvoiceID($invoice_id);
        $payment_inquery_responce = $this->getPaymentInquiryViaPaymentToken($invoice_id, $token);
        CRM_Core_Error::debug_var('payment_inquery', $payment_inquery_responce);
        $totalAmount = CRM_Utils_Array::value('amount', $payment_inquery_responce);
        $last4CardsOfCardIfReturnedHere = substr($payment_inquery_responce['cardNo'], -4);
        $redirectUrl = $notOKUrl;
        $respCode = strval(CRM_Utils_Array::value('respCode', $paymentResponse));
        $respDesc = strval(CRM_Utils_Array::value('respDesc', $paymentResponse));
        $payment_inquery_respCode = strval(CRM_Utils_Array::value('respCode', $payment_inquery_responce));
        $payment_inquery_respDesc = strval(CRM_Utils_Array::value('respDesc', $payment_inquery_responce));
        $reject_status = 'Failed';
        if ('0003' === $payment_inquery_respCode) {
            $reject_status = 'Cancelled';
        }
        if ('2000' != $respCode) {
            $reason_message = '2c2p Error: ' . $respCode . ': ' . $respDesc;
            CRM_Simple2c2p_Utils::setContributionStatusCancelledOrFailed($contribution, $reason_message, $reject_status);
            self::status_bounce_2c2p($reason_message, $redirectUrl);
        }
        if ('0000' != $payment_inquery_respCode) {
            $reason_message = '2c2p Payment Error: ' . $payment_inquery_respCode . ': ' . $payment_inquery_respDesc;
            CRM_Simple2c2p_Utils::setContributionStatusCancelledOrFailed($contribution, $reason_message, $reject_status);
            self::status_bounce_2c2p($reason_message, $redirectUrl);
        }
        if ($module == 'contribute') {
            civicrm_api3('Payment', 'create', [
                'contribution_id' => $contribution_id,
                'total_amount' => $totalAmount,
                'payment_instrument_id' => $this->_paymentProcessor['payment_instrument_id'],
                'trxn_id' => $order_id,
                'credit_card_pan' => $last4CardsOfCardIfReturnedHere,
            ]);
            $redirectUrl = $okUrl;
        }
        if ($module == 'event') {
            $participantId = CRM_Utils_Array::value('pid', $_GET);
            $eventId = CRM_Utils_Array::value('eid', $_GET);
            $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participant_id AND event_id=$event_id";
            CRM_Core_DAO::executeQuery($query);
            civicrm_api3('Payment', 'create', [
                'contribution_id' => $contribution_id,
                'total_amount' => $totalAmount,
                'payment_instrument_id' => $payment_instrument_id,
                'trxn_id' => $order_id,
                'credit_card_pan' => $last4CardsOfCardIfReturnedHere,
            ]);
            $redirectUrl = $okUrl;
        }
        CRM_Utils_System::redirect($redirectUrl);
    }

    /**
     * @param string $reason_message
     * @param string $redirectUrl
     */
    static function status_bounce_2c2p(string $reason_message = "2c2p Error", string $redirectUrl = null)
    {
        try {
            if ($redirectUrl) {
                CRM_Core_Error::statusBounce($reason_message, $redirectUrl);
            } else {
                CRM_Core_Error::statusBounce($reason_message);
            }
        } catch (\Exception $e) {
            CRM_Core_Error::debug_var('error', $e->getMessage());
        }
        return;
    }


    /**
     * Cancel a recurring subscription.
     *
     * Payment processor classes should override this rather than implementing cancelSubscription.
     *
     * A PaymentProcessorException should be thrown if the update of the contribution_recur
     * record should not proceed (in many cases this function does nothing
     * as the payment processor does not need to take any action & this should silently
     * proceed. Note the form layer will only call this after calling
     * $processor->supports('cancelRecurring');
     *
     * @param \Civi\Payment\PropertyBag $propertyBag
     *
     * @return array
     *
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public
    function doCancelRecurring(PropertyBag $propertyBag)
    {
        return ['message' => ts('Recurring contribution cancelled')];
    }

    /**
     * Get a value for the transaction ID.
     *
     * Value is made up of the max existing value + a random string.
     *
     * Note the random string is likely a historical workaround.
     *
     * @return string
     */
    protected
    function getTrxnID()
    {
        $string = $this->_mode;
        $trxn_id = CRM_Core_DAO::singleValueQuery("SELECT MAX(trxn_id) FROM civicrm_contribution WHERE trxn_id LIKE '{$string}_%'") ?? '';
        $trxn_id = str_replace($string, '', $trxn_id);
        $trxn_id = (int)$trxn_id + 1;
        return $string . '_' . $trxn_id . '_' . uniqid();
    }

    /**
     * @param $params
     * @param $component
     * @param $merchantID
     * @return array
     */
    private
    function preparePaymentPayload($params, $component): array
    {
        $merchantID = $this->_paymentProcessor['user_name'];  //Get MerchantID when opening account with 2C2P
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $email = self::getEmailFromParams($params);
        $amount = $params['amount'];
        $displayName = $params['displayName'];
        $currency = 'SGD'; //works only with SGD
        $frontendReturnUrl = $this->getReturnUrl($params, $component);


        $payload = array(
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceNo,
            "description" => $description,
            "amount" => $amount,
            "currencyCode" => $currency,
//            "request3DS" => "N",
            "frontendReturnUrl" => $frontendReturnUrl,
//            "backendReturnUrl" => $frontendReturnUrl,
            "uiParams" => [
                "userInfo" => [
                    "email" => $email,
                    "name" => $displayName
                ]
            ],
        );
        return $payload;
    }

    /**
     * @param array $payload
     * @return array
     *
     * @throws PaymentProcessorException
     */
    private
    function getPaymentTokenResponse(array $payload): array
    {
        $secretKey = $this->_secretKey;    //Get SecretKey from 2C2P PGW Dashboard
        $gatewayUrl = $this->_gatewayURL;
        $tokenUrl = $gatewayUrl . '/payment/4.1/PaymentToken';  //Get url_site from 2C2P PGW Dashboard
        $encodedTokenRequest = CRM_Simple2c2p_Utils::encodeJwtData($secretKey, $payload);
        $encodedTokenResponse = CRM_Simple2c2p_Utils::getEncodedResponse($tokenUrl, $encodedTokenRequest);
        $decodedTokenResponse = CRM_Simple2c2p_Utils::getDecodedResponse($secretKey, $encodedTokenResponse);

        $respCode = strval(CRM_Utils_Array::value('respCode', $decodedTokenResponse));
        $respDesc = CRM_Utils_Array::value('respDesc', $decodedTokenResponse);
        $webPaymentUrl = CRM_Utils_Array::value('webPaymentUrl', $decodedTokenResponse);
        $paymentToken = CRM_Utils_Array::value('paymentToken', $decodedTokenResponse);
        if ($respCode == '0000') {
            return [$webPaymentUrl, $paymentToken];
        } else {
            throw new PaymentProcessorException('2c2p Error: respCode: ' . $respCode . '; respDesc: ' . $respDesc);
        }


    }

    /**
     * @param $params
     * @param $simple2c2pToken
     * @return mixed
     * @throws PaymentProcessorException
     */
    private
    function save2c2pToken($params, $simple2c2pToken, $webPaymentUrl)
    {
        list($contact_id, $displayName) = self::getContactIdAndDisplayNameFromParams($params);
        $email = self::getEmailFromParams($params);
        $date = date("Y-m-d H:i:s");
        $expirydate = date("Y-m-d H:i:s", strtotime($date . '+30 minutes'));
        $merchantID = $this->_paymentProcessor['user_name'];  //Get MerchantID when opening account with 2C2P
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        try {
            $paymentToken = civicrm_api3('PaymentToken', 'create', [
                'contact_id' => $contact_id,
                'token' => $simple2c2pToken,
                'expiry_date' => $expirydate,
                'masked_account_number' => $invoiceNo,
                'billing_first_name' => $merchantID ?? NULL,
                'billing_middle_name' => mb_substr($description, 0, 254),
                'billing_last_name' => mb_substr($displayName, 0, 254) ?? NULL,
                'payment_processor_id' => $this->_paymentProcessor['id'],
                'created_id' => CRM_Core_Session::getLoggedInContactID() ?? $contact_id,
                'ip_address' => substr($webPaymentUrl, 0, 254),
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            throw new PaymentProcessorException('2c2p Error: ' . $e->getMessage());
        }
        return $paymentToken;
    }

    /**
     * @param $params
     * @param $component
     * @return mixed
     */
    private
    function get_webPaymentUrl(&$params, $component)
    {
        list($contact_id, $displayName) = self::getContactIdAndDisplayNameFromParams($params);
        $params['cid'] = $contact_id;
        $params['displayName'] = $displayName;
        $params['destination'] = 'front';

        $payload = $this->preparePaymentPayload($params, $component);
        CRM_Core_Error::debug_var('first_payload', $payload);
        list($webPaymentUrl, $simple2c2pToken) = $this->getPaymentTokenResponse($payload);

        $paymentToken = $this->save2c2pToken($params, $simple2c2pToken, $webPaymentUrl);

        return $webPaymentUrl;
    }

    /**
     * @param $invoiceId
     * @return array|int|mixed
     * @throws CiviCRM_API3_Exception
     * @throws PaymentProcessorException
     */
    public static function getContribution($contribution_id)
    {
        $contributionParams = [
            'id' => $contribution_id,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams);
        if (array_key_exists('values', $contribution)) {
            $contribution = $contribution['values'];
            $contribution = reset($contribution);
            return $contribution;
        } else {
            new PaymentProcessorException('2c2p Error: Unvalid Contribution');
        }
        throw new PaymentProcessorException('2c2p Error: Zero Contribution');
    }

    /**
     * @param $webPaymentUrl
     */
    public function gotoPaymentGateway($webPaymentUrl): void
    {
        $template = CRM_Core_Smarty::singleton();
        $tpl = 'CRM/Core/Payment/Simple2c2p.tpl';
        $template->assign('webPaymentUrl', $webPaymentUrl);
        print $template->fetch($tpl);
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public function getPaymentInquiryViaPaymentToken($invoice_id, $token): array
    {

        $url = $this->_gatewayURL . '/payment/4.1/paymentInquiry';
        $secret_key = $this->_secretKey;
        $merchant_id = $this->_merchantID;
        $payload = [
            "paymentToken" => $token,
            "merchantID" => $merchant_id,
            "invoiceNo" => $invoice_id,
            "locale" => "en"];
        $inquiryRequestData = CRM_Simple2c2p_Utils::encodeJwtData($secret_key, $payload);
        $encodedTokenResponse = CRM_Simple2c2p_Utils::getEncodedResponse($url, $inquiryRequestData);
        $decodedTokenResponse = CRM_Simple2c2p_Utils::getDecodedResponse($secret_key, $encodedTokenResponse);
        return $decodedTokenResponse;
    }

    /**
     * @param $invoiceID
     * @return array|int
     * @throws CRM_Core_Exception
     */
    public
    static function getPaymentTokenViaInvoiceID($invoiceID)
    {
        $payment_token = [];
        $token = "";
        try {
            $payment_token = civicrm_api3('PaymentToken', 'getsingle', [
                'masked_account_number' => $invoiceID,
            ]);
            if ($payment_token) {
                $token = $payment_token['token'];
            }
        } catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Error::debug_var('API error', $e->getMessage() . "\nInvoiceID: $invoiceID\n");
//            throw new CRM_Core_Exception(ts('2c2p - Could not find payment token') . "\nInvoiceID: $invoiceID\n");
        }
        return $token;
    }


}