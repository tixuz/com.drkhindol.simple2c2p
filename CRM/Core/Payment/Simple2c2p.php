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

    protected $_mode;
    protected $_doDirectPaymentResult = [];

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
        'BackOffice' => TRUE,
        'NoEmailProvided' => TRUE,
        'CancelRecurring' => TRUE,
        'FutureRecurStartDate' => TRUE,
        'Refund' => TRUE,
    ];

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
     * @throws \Civi\Payment\Exception\PaymentProcessorException
     */
    public function doPayment(&$params, $component = 'contribute')
    {
        /* @var \Civi\Payment\PropertyBag $propertyBag */
        $propertyBag = self::castParamsToPropertyBug($params);

        if ($propertyBag->getAmount() == 0) {
            $result = self::getEmptyComplitedPaymentResult();
            return $result;
        }
        $this->_component = $component;
        $statuses = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id', 'validate');


        // Prepare whatever data the 3rd party processor requires to take a payment.
        // The contents of the array below are just examples of typical things that
        // might be used.
        $processorFormattedParams = [
            'authentication_key' => $this->getPaymentProcessor()['user_name'],
            'amount' => $propertyBag->getAmount(),
            'order_id' => $propertyBag->getter('contributionID', TRUE, ''),
            // getNotifyUrl helps you construct the url to tell an off-site
            // processor where to send payment notifications (IPNs/webhooks) to.
            // Not all 3rd party processors need this.
            'notifyUrl' => $this->getNotifyUrl(),
            // etc. depending on the features and requirements of the 3rd party API.
        ];
        if ($propertyBag->has('description')) {
            $processorFormattedParams['description'] = $propertyBag->getDescription();
        }


        CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $propertyBag);
        // This means we can test failing transactions by setting a past year in expiry. A full expiry check would
        // be more complete.
        if (!empty($params['credit_card_exp_date']['Y']) && CRM_Utils_Time::date('Y') >
            CRM_Core_Payment_Form::getCreditCardExpirationYear($params)) {
            throw new PaymentProcessorException(ts('Invalid expiry date'));
        }

        if (!empty($this->_doDirectPaymentResult)) {
            $result = $this->_doDirectPaymentResult;
            if (empty($result['payment_status_id'])) {
                $result['payment_status_id'] = array_search('Pending', $statuses);
                $result['payment_status'] = 'Pending';
            }
            if ($result['payment_status_id'] === 'failed') {
                throw new PaymentProcessorException($result['message'] ?? 'failed');
            }
            $result['trxn_id'] = array_shift($this->_doDirectPaymentResult['trxn_id']);
            return $result;
        }

        $result['trxn_id'] = $this->getTrxnID();

        // Add a fee_amount so we can make sure fees are handled properly in underlying classes.
        $result['fee_amount'] = 1.50;
        $result['description'] = $this->getPaymentDescription($params);

        if (!isset($result['payment_status_id'])) {
            if (!empty($propertyBag->getIsRecur())) {
                // See comment block.
                $result['payment_status_id'] = array_search('Pending', $statuses);
                $result['payment_status'] = 'Pending';
            } else {
                $result['payment_status_id'] = array_search('Completed', $statuses);
                $result['payment_status'] = 'Completed';
            }
        }

        return $result;
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
    public function doCancelRecurring(PropertyBag $propertyBag)
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
    protected function getTrxnID()
    {
        $string = $this->_mode;
        $trxn_id = CRM_Core_DAO::singleValueQuery("SELECT MAX(trxn_id) FROM civicrm_contribution WHERE trxn_id LIKE '{$string}_%'") ?? '';
        $trxn_id = str_replace($string, '', $trxn_id);
        $trxn_id = (int)$trxn_id + 1;
        return $string . '_' . $trxn_id . '_' . uniqid();
    }

}