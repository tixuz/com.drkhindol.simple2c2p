<?php

use CRM_Simple2c2p_ExtensionUtil as E;

use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\{
    Algorithm\KeyEncryption\RSAOAEP,
    Algorithm\ContentEncryption\A256GCM,
    Compression\CompressionMethodManager,
    Compression\Deflate,
    JWEBuilder,
    JWELoader,
    JWEDecrypter,
    JWETokenSupport,
    Serializer\CompactSerializer as EncryptionCompactSerializer,
    Serializer\JWESerializerManager
};

use \Jose\Component\Checker\AlgorithmChecker;
use \Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Signature\{
    Algorithm\PS256,
    JWSBuilder,
    JWSTokenSupport,
    JWSLoader,
    JWSVerifier,
    Serializer\JWSSerializerManager,
    Serializer\CompactSerializer as SignatureCompactSerializer};

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
//    CRM_Core_Error::debug_var('autoload1', $autoload);
    require_once $autoload;
} else {
    $autoload = E::path() . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
//        CRM_Core_Error::debug_var('autoload2', $autoload);
    }
}

use \Firebase\JWT\JWT;

class CRM_Simple2c2p_Utils
{
    const OPEN_CERTIFICATE_FILE_NAME = "public.cer";
    const CLOSED_CERTIFICATE_FILE_NAME = "private.pem";
    const CLOSED_CERTIFICATE_PWD = "octopus8";


    /**
     * @param $invoiceId
     * @return string
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function redirectByInvoiceId($invoiceId): string
    {
//        CRM_Core_Error::debug_var('contribution', $contribution);
        $thanxUrl = self::getThanxUrlViaInvoiceID($invoiceId);
        $failureUrl = self::getFailureUrlViaInvoiceID($invoiceId);
//        CRM_Core_Error::debug_var('url', $url);
        $paymentInquery = self::getPaymentInquiryViaPaymentToken($invoiceId);
        if (array_key_exists('respCode', $paymentInquery)) {
            $resp_code = $paymentInquery['respCode'];
//            CRM_Core_Error::debug_var('paymentInquery', $paymentInquery);

            if ($resp_code === "0000") {
                return $thanxUrl;
            }
            if ($resp_code === "4200") { //Successful tokenization for recurring payments
                return $thanxUrl;
            }
            self::verifyContribution($invoiceId);
            if ($resp_code == "0003") {
                return $failureUrl;
            }
            if ($resp_code == "0001") {
                return $failureUrl;
            }
            if ($resp_code == "2001") {
                return $thanxUrl;
            }
        }
        self::verifyContribution($invoiceId);
        return $failureUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    public static function getThanxUrlViaInvoiceID($invoiceID): string
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
        $thanxUrl = strval($payment_processor_array['subject']);

        if ($thanxUrl == null || $thanxUrl == "") {
            $thanxUrl = CRM_Utils_System::url();
        }
        return $thanxUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    public static function getFailureUrlViaInvoiceID($invoiceID): string
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
//        CRM_Core_Error::debug_var('$payment_processor_array_failure', $payment_processor_array);
        $failureUrl = strval($payment_processor_array['signature']);
        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $failureUrl;
    }

    /**
     * @param $invoiceId
     * @return int
     * @throws CiviCRM_API3_Exception
     */
    protected static function getPaymentProcessorIdViaInvoiceID($invoiceId): int
    {
        try {
            $payment_token = self::getPaymentTokenViaInvoiceID($invoiceId);
//            CRM_Core_Error::debug_var('payment_token_getPaymentProcessorIdViaInvoiceID', $payment_token);
            if (key_exists('payment_processor_id', $payment_token)) {
                $paymentProcessorId = $payment_token['payment_processor_id'];
                return (int)$paymentProcessorId;
            }
            if (!key_exists('payment_processor_id', $payment_token)) {
                $pP = self::getPaymentProcessorViaProcessorName('Simple2c2p');
                $pp = $pP->getPaymentProcessor();
                return (int)$pp['id'];
            }
        } catch (CRM_Core_Exception $e) {
            $pP = self::getPaymentProcessorViaProcessorName('Simple2c2p');
            $pp = $pP->getPaymentProcessor();
            return (int)$pp['id'];
        }

    }

    protected static function getPaymentProcessorViaInvoiceID($invoiceId): CRM_Core_Payment_Simple2c2p
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        return $paymentProcessor;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function getPaymentInquiryViaPaymentToken($invoiceID): array
    {
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $decodedTokenResponse = [];
        if (!key_exists('token', $payment_token)) {
            $decodedTokenResponse = [];
            return $decodedTokenResponse;
        }
        $paymentToken = $payment_token['token'];
        $paymentTokenID = $payment_token['id'];
        $paymentProcessorId = $payment_token['payment_processor_id'];
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
        $url = $payment_processor_array['url_site'] . '/payment/4.1/paymentInquiry';
        $secretkey = $payment_processor_array['password'];
        $merchantID = $payment_processor_array['user_name'];
        $payload = [
            "paymentToken" => $paymentToken,
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceID,
            "locale" => "en"];
        $inquiryRequestData = self::encodeJwtData($secretkey, $payload);
        $encodedTokenResponse = self::getEncodedResponse($url, $inquiryRequestData);
        $decodedTokenResponse = self::getDecodedResponse($secretkey, $encodedTokenResponse);
        $decodedTokenResponse['token'] = $paymentToken;
        $decodedTokenResponse['tokenID'] = $paymentTokenID;
        return $decodedTokenResponse;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function getRecurringPaymentIdViaPaymentToken($invoiceID): string
    {
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $recurringPaymentId = $payment_token['billing_first_name'];
        return strval($recurringPaymentId);
    }

    /**
     * @param $secretKey
     * @param $payload
     * @return string
     */
    public static function encodeJwtData($secretKey, $payload): string
    {

        $jwt = JWT::encode($payload, $secretKey);

        $data = '{"payload":"' . $jwt . '"}';

        return $data;
    }


    /**
     * @param $url
     * @param $payload
     * @return \Psr\Http\Message\StreamInterface
     * @throws CRM_Core_Exception
     */
    public static function getEncodedResponse($url, $payload)
    {
        $client = new GuzzleHttp\Client();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';

        try {
            $response = $client->request('POST', $url, [
                'body' => $payload,
                'user_agent' => $user_agent,
                'headers' => [
                    'Accept' => 'text/plain',
                    'Content-Type' => 'application/*+json',
                    'X-VPS-Timeout' => '45',
                    'X-VPS-VIT-Integration-Product' => 'CiviCRM',
                    'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
                ],
            ]);
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            CRM_Core_Error::statusBounce('2c2p Error: Request error ', null, $e->getMessage());
            throw new CRM_Core_Exception('2c2p Error: Request error: ' . $e->getMessage());
        } catch (Exception $e) {
            CRM_Core_Error::statusBounce('2c2p Error: Another error: ', null, $e->getMessage());
            throw new CRM_Core_Exception('2c2p Error: Another error: ' . $e->getMessage());
        }
        return $response->getBody();
    }


    /**
     * @param $secretKey
     * @param $response
     * @return array
     */
    public static function getDecodedResponse($secretKey, $response, $responseType = "payload")
    {
        try {
            $decoded = json_decode($response, true);
        } catch (Exception $e) {
            throw new CRM_Core_Exception('2c2p Error: Not a JSON in Response error: ' . $e->getMessage());
        }
//        CRM_Core_Error::debug_var('decoded', $decoded);
        if (isset($decoded[$responseType])) {
            $payloadResponse = $decoded[$responseType];
            if ($responseType == 'payload') {
                $decoded_array = self::getDecodedPayloadJWT($secretKey, $payloadResponse);
            }
            if ($responseType == 'paymentResponse') {
                $decoded_array = self::getDecodedPayload64($payloadResponse);
            }
            return $decoded_array;
        } else {
            return $decoded;
        }

    }

    /**
     * @param $payloadResponse string
     * @return array
     */
    public static function getDecodedPayload64($payloadResponse): array
    {
        $decodedPayloadString = base64_decode($payloadResponse);
        $decodedPayload = json_decode($decodedPayloadString);
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $secretKey string
     * @param $payloadResponse string
     * @return array
     */
    public static function getDecodedPayloadJWT($secretKey, $payloadResponse): array
    {
        $decodedPayload = JWT::decode($payloadResponse, $secretKey, array('HS256'));
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $invoiceId
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function verifyContribution($invoiceId): void
    {
        //todo recieve only payment
//        CRM_Core_Error::debug_var('invoiceIdinverifyContribution', $invoiceId);
//        Civi::log()->info("start_verifyContribution");
        $trxnId = substr($invoiceId, 0, CRM_Core_Payment_Simple2c2p::LENTRXNID);
        $contribution = self::getContributionByInvoiceId($invoiceId);
        //try to catch info using PaymentToken
        $paymentInquery = self::getPaymentInquiryViaPaymentToken($invoiceId);
        if ($contribution['contribution_recur_id'] != null) {
            self::saveRecurringTokenValue($invoiceId, $paymentInquery);
        }
        $completed_status_id = self::contribution_status_id('Completed');
        $pending_status_id = self::contribution_status_id('Pending');
        $cancelled_status_id = self::contribution_status_id('Cancelled');
        $failed_status_id = self::contribution_status_id('Failed');
        $in_progress_status_id = self::contribution_status_id('In Progress');
        $overdue_status_id = self::contribution_status_id('Overdue');
        $refunded_status_id = self::contribution_status_id('Refunded');
        $partially_paid_status_id = self::contribution_status_id('Partially paid');
        $pending_refund_status_id = self::contribution_status_id('Pending refund');
        $chargeback_paid_status_id = self::contribution_status_id('Chargeback');
        $template_paid_status_id = self::contribution_status_id('Template');


        if ("0000" == $paymentInquery['respCode']) {
            //OK
            if ($contribution['contribution_status_id'] != $completed_status_id) {
                self::setContributionStatusCompleted($invoiceId, $paymentInquery, $contribution, $trxnId);
            }
            return;
        }
        if ("4200" == $paymentInquery['respCode']) {
            //@todo recurring contribution
            Civi::log()->info("startingSettingStatusCompleted");
            if ($contribution['contribution_status_id'] != $completed_status_id) {
                self::setContributionStatusCompleted($invoiceId, $paymentInquery, $contribution, $trxnId);
            }
            Civi::log()->info("endedSettingStatusCompleted");
            return;
        }
        if ("0003" == $paymentInquery['respCode']) {
            Civi::log()->info("startedSettingStatusCancelled");
            if ($contribution['contribution_status_id'] != $cancelled_status_id) {
                self::setContributionStatusCancelledOrFailed($contribution);
            }
            Civi::log()->info("endedSettingStatusCancelled");
            return;
        }
        $decodedTokenResponse = self::getPaymentInquiryViaKeySignature($invoiceId);

        $resp_code = strval($decodedTokenResponse['respCode']);

        if ($resp_code == "15") {
            if ($contribution['contribution_status_id'] != $cancelled_status_id) {
                self::setContributionStatusCancelledOrFailed($contribution);
            }
            return;
        }
        if ($resp_code == "16") {
            if ($contribution['contribution_status_id'] != $cancelled_status_id) {
                self::setContributionStatusCancelledOrFailed($contribution);
            }
            return;
        }
        $contribution_status = $decodedTokenResponse['status'];

        if ($decodedTokenResponse['status'] == "S") {
            if ($decodedTokenResponse['respDesc'] != "No refund records") {
                if ($contribution['contribution_status_id'] == $pending_status_id) {
                    self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
                }
                self::setContributionStatusRefunded($contribution);
                return;
            }
        }
        if ($contribution_status !== "A") {
            if (in_array($contribution_status, [
                "V"])) {
                self::setContributionStatusCancelledOrFailed($contribution);
                return;
            }
            if (in_array($contribution_status, [
                "AR",
                "FF",
                "IP",
                "ROE",
                "EX",
                "CTF"])) {
                self::changeContributionStatusViaDB($invoiceId, $failed_status_id);
                return;
            }

            if ($contribution_status == "RF") {
                if ($contribution['contribution_status_id'] == $pending_status_id) {
                    self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
                }
                self::setContributionStatusRefunded($contribution);
            }

            if (in_array($contribution_status, ["AP", "RP", "VP"])) {
                self::changeContributionStatusViaDB($invoiceId, $pending_status_id);
            }

            if ($contribution_status == "RS") {
                self::changeContributionStatusViaDB($invoiceId, $in_progress_status_id);
            }
            return;
        }
        self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
    }


    /**
     * @param $invoiceId
     * @return array|int|mixed
     * @throws CiviCRM_API3_Exception
     */
    public static function getContributionByInvoiceId($invoiceId)
    {
        $contributionParams = [
            'invoice_id' => $invoiceId,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams);
        if (array_key_exists('values', $contribution)) {
            $contribution = $contribution['values'];
            $contribution = reset($contribution);
            return $contribution;
        } else {
            new CRM_Core_Exception(ts('2c2p - Unvalid Contribution'));
        }
        return null;
    }


    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     */
    public static function saveRecurringTokenValue($invoiceId, array $decodedTokenResponse): void
    {
        $tranRef = $decodedTokenResponse['tranRef'];
        $recurringUniqueID = $decodedTokenResponse['recurringUniqueID'];
        $referenceNo = $decodedTokenResponse['referenceNo'];

        $query = "UPDATE civicrm_payment_token SET 
                                billing_first_name='$recurringUniqueID',
                                 billing_middle_name='$tranRef',
                                 billing_last_name='$referenceNo'
                      where masked_account_number='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
    }

    /**
     * @param $invoiceID
     * @param $paymentProcessorId
     * @return CRM_Financial_DAO_PaymentProcessor
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function getPaymentProcessorViaProcessorID($paymentProcessorId): CRM_Core_Payment_Simple2c2p
    {

        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'id' => $paymentProcessorId,
            'sequential' => 1,
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL; //todo raise error
        }
        $paymentProcessorInfo = array_shift($paymentProcessorInfo);
        $paymentProcessor = new CRM_Core_Payment_Simple2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    /**
     * @param $paymentProcessorName
     * @return CRM_Core_Payment_Simple2c2p
     * @throws CiviCRM_API3_Exception
     */
    public static function getPaymentProcessorViaProcessorName($paymentProcessorName): CRM_Core_Payment_Simple2c2p
    {
//        CRM_Core_Error::debug_var('paymentProcessorName', $paymentProcessorName);
        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'name' => $paymentProcessorName,
            'sequential' => 1,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL;
        }
        $paymentProcessorInfo = array_shift($paymentProcessorInfo);
        $paymentProcessor = new CRM_Core_Payment_Simple2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    /**
     * @param $invoiceID
     * @return array|int
     * @throws CRM_Core_Exception
     */
    public static function getPaymentTokenViaInvoiceID($invoiceID)
    {
        $payment_token = [];
        try {
            $payment_token = civicrm_api3('PaymentToken', 'getsingle', [
                'masked_account_number' => $invoiceID,
            ]);
        } catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Error::debug_var('API error', $e->getMessage() . "\nInvoiceID: $invoiceID\n");
//            throw new CRM_Core_Exception(ts('2c2p - Could not find payment token') . "\nInvoiceID: $invoiceID\n");
        }
        return $payment_token;
    }


    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     * @param $contribution
     * @param $trxnId
     * @throws CRM_Core_Exception
     */
    public static function setContributionStatusCompleted($invoiceId, array $decodedTokenResponse, $contribution, $trxnId): void
    {
        if (key_exists('cardNo', $decodedTokenResponse)) {
            $cardNo = substr($decodedTokenResponse['cardNo'], -4);
        }
        if (key_exists('maskedPan', $decodedTokenResponse)) {
            $cardNo = substr($decodedTokenResponse['maskedPan'], -4);
        }
        if (key_exists('channelCode', $decodedTokenResponse)) {
            $channelCode = $decodedTokenResponse['channelCode'];
        }
        if (key_exists('processBy', $decodedTokenResponse)) {
            $channelCode = $decodedTokenResponse['processBy'];
        }
        $cardTypeId = 2;
        $paymentInstrumentId = null;
        $paymentInstrumentId = 1;
        if ($channelCode == 'VI') {
            $cardTypeId = 1;
        }

//        CRM_Core_Error::debug_var('contribution_status_id', "SUPER");
        $contributionId = $contribution['id'];
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $failed_status_id = self::contribution_status_id('Failed');
        $cancelled_status_id = self::contribution_status_id('Cancelled');
        $pending_status_id = self::contribution_status_id('Pending');
        if (in_array($contribution['contribution_status_id'], [$failed_status_id, $cancelled_status_id])) {
            self::changeContributionStatusViaDB($invoiceId, $pending_status_id);
            //to give possibility to make it fulfiled
        }
        try {
            civicrm_api3('contribution', 'completetransaction',
                ['id' => $contributionId,
                    'trxn_id' => $trxnId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'cancel_date' => "",
                    'cancel_reason' => "",
                    'is_email_receipt' => false,
                    'payment_instrument_id' => $paymentInstrumentId,
                    'processor_id' => $paymentProcessorId]);
        } catch (CiviCRM_API3_Exception $e) {
            if (!stristr($e->getMessage(), 'Contribution already completed')) {
                Civi::log()->debug("2c2p IPN Error Updating contribution: " . $e->getMessage());
            }
//            throw $e;
        }
    }

    /**
     * @param $contribution
     * @param string $reason_message
     * @throws \Exception
     */
    public static function setContributionStatusCancelledOrFailed($contribution, $reason_message = '2c2p Error', $status = 'Cancelled'): void
    {
//        CRM_Core_Error::debug_var('contribution', $contribution);
//        Civi::log()->info("setContributionStatusCancelled1");
        $cancelled_status_id = self::contribution_status_id($status);
        $contribution_status_id = $contribution['contribution_status_id'];
        $reason_message = mb_substr($reason_message, 0, 254);
        CRM_Core_Error::debug_var('contribution_status_id', $contribution_status_id);
        CRM_Core_Error::debug_var('reason_message', $reason_message);
        if ($contribution_status_id == $cancelled_status_id) {
            return;
        }


        $change_result0 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution',
            $contribution['id'],
            'cancel_reason',
            $reason_message);

        try {
            $change_result1 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution',
                $contribution['id'],
                'contribution_status_id',
                $cancelled_status_id);
        } catch (\Exception $e) {
            CRM_Core_Error::debug_var('Error in setContributionStatusCancelledOrFailed: ', $e->getMessage());
        }

        $now = date('YmdHis');
        $change_result2 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution',
            $contribution['id'],
            'cancel_date',
            $now);
        CRM_Core_Error::debug_var('now', $now);

    }

    /**
     * @param $contribution
     * @return bool|int|string|null
     * @throws CiviCRM_API3_Exception
     */
    public static function setContributionRecurStatusCancelledOrFailed(
        $contributionRecur,
        $contributionStatusID = 3,
        $cancelDate = "",
        $cancelReason = ""): void
    {
//        CRM_Core_Error::debug_var('contribution', $contribution);
//        Civi::log()->info("setContributionStatusCancelled1");
        $cancelled_status_id = self::contribution_status_id('Cancelled');

        if ($contributionRecur->contribution_status_id != $contributionStatusID) {
            $change_result1 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                $contributionRecur->id,
                'contribution_status_id',
                $contributionStatusID);
        }

        $now = date('YmdHis');
        if ($cancelDate == "") {
            $cancelDate = $now;
        }
//        CRM_Core_Error::debug_var('cancelDate', $cancelDate);
        $change_result2 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
            $contributionRecur->id,
            'cancel_date',
            $cancelDate);
        if ($cancelReason != "") {
            $change_result2 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                $contributionRecur->id,
                'cancel_reason',
                $cancelReason);
        }
    }

    /**
     * @param $contribution
     * @throws CiviCRM_API3_Exception
     */
    public static function setContributionStatusRefunded($contribution): void
    {
        $contribution_status_id = self::contribution_status_id('Refunded');
        if ($contribution['contribution_status_id'] == $contribution_status_id) {
            return;
        }

        $change_result1 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution',
            $contribution['id'],
            'contribution_status_id',
            $contribution_status_id);

        $now = date('YmdHis');
        if (!array_key_exists('cancel_date', $contribution) || $contribution['cancel_date'] == null || $contribution['cancel_date'] == "") {
            $change_result2 = CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution',
                $contribution['id'],
                'cancel_date',
                $now);
        }
    }

    /**
     * @param $name
     * @return int|string|null
     */
    public static function contribution_status_id($name)
    {
        return CRM_Utils_Array::key($name, \CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));
    }

    /**
     * @param $invoiceId
     * @param $contribution_status_id
     */
    protected static function changeContributionStatusViaDB($invoiceId, $contribution_status_id): void
    {
        $query = "UPDATE civicrm_contribution SET 
                                contribution_status_id=$contribution_status_id 
                      where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
    }


    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function getPaymentInquiryViaKeySignature(
        $invoiceID,
        $processType = "I",
        $request_type = "PaymentProcessRequest",
        $version = "3.8",
        $recurringUniqueID = ""
    ): array
    {
        $paymentProcessor = self::getPaymentProcessorViaProcessorName('Simple2c2p');
        $payment_processor = $paymentProcessor->getPaymentProcessor();
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $date = date('Y-m-d h:i:s');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
        if ($request_type == "RecurringMaintenanceRequest") {
            $version = "2.1";
        }

        $payment_inquiry = array(
            'version' => $version,
            'processType' => $processType,
            'invoiceNo' => $invoiceID,
            'timeStamp' => $time_stamp,
            'merchantID' => $merchant_id,
            'actionAmount' => "",
            'request_type' => $request_type,

        );
        if ($recurringUniqueID != "") {
            $payment_inquiry["recurringUniqueID"] = $recurringUniqueID;
        }
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
        $response_body_contents = $response->getBody()->getContents();

//        CRM_Core_Error::debug_var('response_body_contents_before', $response_body_contents);
        $path_to_2c2p_certificate = self::getOpenCertificatePath();
        $path_to_merchant_pem = self::getClosedCertificatePath();
        $merchant_password = self::getClosedCertificatePwd();
        $answer =
            self::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
//        CRM_Core_Error::debug_var('answer', $answer);

        return $answer;
    }


    /**
     * @param array $payment_inquiry
     * @return \Psr\Http\Message\ResponseInterface
     * @throws CRM_Core_Exception
     */
    public static function getPaymentResponseViaKeySignature(
        array $payment_inquiry): \Psr\Http\Message\ResponseInterface
    {

        $invoiceId = $payment_inquiry['invoiceNo'];
        $receiverPublicCertPath = self::getOpenCertificatePath();
        $senderPrivateKeyPath = self::getClosedCertificatePath();
        $senderPrivateKeyPassword = self::getClosedCertificatePwd(); //private key password

        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceId);

        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
        $merchantID = $payment_processor_array['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $payment_processor_array['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $url = $payment_processor_array['url_api'];

        try {
            $keyEncryptionAlgorithmManager = new AlgorithmManager([
                new RSAOAEP(),
            ]);

        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid keyEncryptionAlgorithmManager') . $e->getMessage());
        }


// The content encryption algorithm manager with the A256CBC-HS256 algorithm.
        try {
            $contentEncryptionAlgorithmManager = new AlgorithmManager([
                new A256GCM(),
            ]);
        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid contentEncryptionAlgorithmManager') . $e->getMessage());
        }
//        CRM_Core_Error::debug_var('contentEncryptionAlgorithmManager', '2');

// The compression method manager with the DEF (Deflate) method.
        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);

// We instantiate our JWE Builder.
        $jwencryptedBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );


// Our key.
        $receiverPublicCertKey = JWKFactory::createFromCertificateFile(
            $receiverPublicCertPath, // The filename
        );


        $stringToHashOne = "";
        $stringXML = "<" . $payment_inquiry["request_type"] . ">";
        foreach ($payment_inquiry as $key => $value) {
            if ($key != "request_type") {
                $stringToHashOne = $stringToHashOne . $value;
                $stringXML = $stringXML . "\n<" . $key . ">" . $value . "</" . $key . ">";
            }
        }
        $hashone = strtoupper(hash_hmac('sha1', $stringToHashOne, $secretKey, false));    //Compute hash value
        $stringXML = $stringXML . "<hashValue>$hashone</hashValue>";
        $stringXML = $stringXML . "\n</" . $payment_inquiry["request_type"] . ">";
        $xml = $stringXML;

//        CRM_Core_Error::debug_var('xml', $xml);

        $jw_encrypted_response = $jwencryptedBuilder
            ->create()// We want to create a new JWE
            ->withPayload($xml)// We set the payload
            ->withSharedProtectedHeader([
                'alg' => 'RSA-OAEP', // Key Encryption Algorithm
                'enc' => 'A256GCM',  // Content Encryption Algorithm
                'typ' => 'JWT'
            ])
            ->addRecipient($receiverPublicCertKey)// We add a recipient (a shared key or public key).
            ->build();

        $encryption_serializer = new EncryptionCompactSerializer(); // The serializer
        $jwe_request_payload = $encryption_serializer->serialize($jw_encrypted_response, 0); // We serialize the recipient at index 0 (we only have one recipient).

        // The algorithm manager with the HS256 algorithm.
        $signatureAlgorithmManager = new AlgorithmManager([
            new PS256(),
        ]);

        // Our key.
        //echo "jwk:\n";
        $jw_signature_key = JWKFactory::createFromKeyFile(
            $senderPrivateKeyPath,
            $senderPrivateKeyPassword
        );


        $jwsBuilder = new JWSBuilder($signatureAlgorithmManager);

        $jw_signed_request = $jwsBuilder
            ->create()// We want to create a new JWS
            ->withPayload($jwe_request_payload)// We set the payload
            ->addSignature($jw_signature_key, [
                'alg' => 'PS256',
                'typ' => 'JWT'
            ])// We add a signature with a simple protected header
            ->build();


        $signature_serializer = new \Jose\Component\Signature\Serializer\CompactSerializer(); // The serializer

        $jw_signed_payload = $signature_serializer->serialize($jw_signed_request, 0); // We serialize the signature at index 0 (we only have one signature).

        $client = new GuzzleHttp\Client();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';
//        CRM_Core_Error::debug_var('signed_payload', $jw_signed_payload);
        try {
            $response = $client->request('POST', $url, [
                'body' => $jw_signed_payload,
                'user_agent' => $user_agent,
                'headers' => [
                    'Accept' => 'text/plain',
                    'Content-Type' => 'application/*+json',
                    'X-VPS-Timeout' => '45',

                ],
            ]);
//            CRM_Core_Error::debug_var('response', $response->getBody()->getContents());
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            CRM_Core_Error::debug_var('guzzle_error', "\n2:\n" . $e->getMessage());
            CRM_Core_Error::debug_var('guzzle_error_xml', $stringXML);
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }
        return $response;
    }

    /**
     * @return string
     */
    private static function getOpenCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::OPEN_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private static function getClosedCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::CLOSED_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private static function getClosedCertificatePwd()
    {
        return self::CLOSED_CERTIFICATE_PWD;
    }


    /**
     * @param $response_body_contents
     * @param $path_to_2c2p_certificate
     * @param $path_to_merchant_pem
     * @param $merchant_password
     * @param $merchant_secret
     * @throws CRM_Core_Exception
     */
    public static function getPaymentFrom2c2pResponse($response_body_contents,
                                                      $path_to_2c2p_certificate,
                                                      $path_to_merchant_pem,
                                                      $merchant_password,
                                                      $merchant_secret): array
    {
        //credentials part
        $receiverPublicCertPath = $path_to_2c2p_certificate;
        try {
            $receiverPublicCertKey = JWKFactory::createFromCertificateFile(
                $receiverPublicCertPath, // The filename
            );
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());
        }

        $senderPrivateKeyPath = $path_to_merchant_pem;

        $senderPrivateKeyPassword = $merchant_password;
        try {
            $jw_signature_key = JWKFactory::createFromKeyFile(
                $senderPrivateKeyPath,
                $senderPrivateKeyPassword
            );
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }

        $secretKey = $merchant_secret;    //Get SecretKey from 2C2P PGW Dashboard
        //end credentials part

        $response_body = $response_body_contents;

        $signatureAlgorithmManager = new AlgorithmManager([
            new PS256(),
        ]);


        // We instantiate our JWS Verifier.
        $jwsVerifier = new JWSVerifier(
            $signatureAlgorithmManager
        );

        $signature_serializer = new SignatureCompactSerializer(); // The serializer

        $signatureSerializerManager = new JWSSerializerManager([
            $signature_serializer,
        ]);

        $headerSignatureCheckerManager = new HeaderCheckerManager(
            [
                new AlgorithmChecker(['PS256']),
            ],
            [
                new JWSTokenSupport(), // Adds JWS token type support
            ]
        );

        $jw_signed_response = $signature_serializer->unserialize($response_body);
        try {
            $isVerified = $jwsVerifier->verifyWithKey($jw_signed_response, $receiverPublicCertKey, 0);
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }
        if ($isVerified) {
            $jwsLoader = new JWSLoader(
                $signatureSerializerManager,
                $jwsVerifier,
                $headerSignatureCheckerManager
            );
            try {
                $jwsigned_response_loaded = $jwsLoader->loadAndVerifyWithKey((string)$response_body, $receiverPublicCertKey, $signature, null);
            } catch (Exception $e) {
                throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

            }
            $encrypted_serialized_response = $jwsigned_response_loaded->getPayload();
        } else {
            throw new CRM_Core_Exception(ts("2c2p Error: Not Verified "));
        }

        $encryption_serializer = new EncryptionCompactSerializer(); // The serializer

        try {

            $encryptionSerializerManager = new JWESerializerManager([
                $encryption_serializer,
            ]);

            $jw_encrypted_response = $encryption_serializer->unserialize($encrypted_serialized_response);

            // The key encryption algorithm manager with the A256KW algorithm.
            $keyEncryptionAlgorithmManager = new AlgorithmManager([
                new RSAOAEP(),
            ]);

            // The content encryption algorithm manager with the A256CBC-HS256 algorithm.
            $contentEncryptionAlgorithmManager = new AlgorithmManager([
                new A256GCM(),
            ]);

            // The compression method manager with the DEF (Deflate) method.
            $compressionMethodManager = new CompressionMethodManager([
                new Deflate(),
            ]);

            // We instantiate our JWE Decrypter.
            $jweDecrypter = new JWEDecrypter(
                $keyEncryptionAlgorithmManager,
                $contentEncryptionAlgorithmManager,
                $compressionMethodManager
            );
            $headerCheckerManagerE = new HeaderCheckerManager(
                [
                    new AlgorithmChecker(['RSA-OAEP']),
                ],
                [
                    new JWETokenSupport(), // Adds JWS token type support
                ]
            );

            $success = $jweDecrypter->decryptUsingKey($jw_encrypted_response, $jw_signature_key, 0);
//            CRM_Core_Error::debug_var('success_within_getPaymentFrom2c2pResponse: ', strval($success));

            $jweLoader = new JWELoader(
                $encryptionSerializerManager,
                $jweDecrypter,
                $headerCheckerManagerE
            );
            $jw_encrypted_response = $jweLoader->loadAndDecryptWithKey($encrypted_serialized_response,
                $jw_signature_key,
                $recipient);
            $unencrypted_payload = $jw_encrypted_response->getPayload();

        } catch (Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - JWE Error: ') . $e->getMessage());
        }

        $answer = self::unencryptPaymentAnswer($unencrypted_payload, $secretKey);
//            CRM_Core_Error::debug_var('answer_within_getPaymentFrom2c2pResponse', $answer);
        return $answer;
    }


    /**
     * @param string|null $unencrypted_payload
     * @param $secretKey
     * @return array
     * @throws CRM_Core_Exception
     * @todo?
     */
    protected static function unencryptRecurringPaymentAnswer(?string $unencrypted_payload, $secretKey): array
    {
        $resXml = simplexml_load_string($unencrypted_payload);
        $array = json_decode(json_encode((array)simplexml_load_string($resXml)), $resXml);
//        print_r($array);
        $res_version = (string)$resXml->version;
        $res_timeStamp = (string)$resXml->timeStamp;
        $res_respCode = (string)$resXml->respCode;
        $res_respReason = (string)$resXml->respReason;
        $res_recurringUniqueID = (string)$resXml->recurringUniqueID;
        $res_recurringStatus = (string)$resXml->recurringStatus;
        $res_invoicePrefix = (string)$resXml->invoicePrefix;
        $res_currency = (int)$resXml->currency;
        $res_amount = (int)$resXml->amount;
        $res_maskedCardNo = (string)$resXml->maskedCardNo;
        $res_allowAccumulate = (boolean)$resXml->allowAccumulate;
        $res_maxAccumulateAmount = (int)$resXml->maxAccumulateAmount;
        $res_recurringInterval = (int)$resXml->recurringInterval;
        $res_recurringCount = (int)$resXml->recurringCount;
        $res_currentCount = (int)$resXml->currentCount;
        $res_chargeNextDate = (string)$resXml->chargeNextDate;


//Compute response hash
        $res_stringToHash =
            $res_version
            . $res_respCode
            . $res_recurringUniqueID
            . $res_recurringStatus
            . $res_invoicePrefix
            . $res_currency
            . $res_amount
            . $res_maskedCardNo
            . $res_allowAccumulate
            . $res_maxAccumulateAmount
            . $res_recurringInterval
            . $res_recurringCount
            . $res_currentCount
            . $res_chargeNextDate;

        $res_responseHash = strtoupper(hash_hmac('sha1', $res_stringToHash, $secretKey, false));    //Compute hash value

        if (strtolower($resXml->hashValue) != strtolower($res_responseHash)) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid Response Hash'));
        }

        $answer = [
            'version' => $res_version,
            'timeStamp' => $res_timeStamp,
            'respCode' => $res_respCode,
            'respReason' => $res_respReason,
            'recurringUniqueID' => $res_recurringUniqueID,
            'recurringStatus' => $res_recurringStatus,
            'invoicePrefix' => $res_invoicePrefix,
            'currency' => $res_currency,
            'amount' => $res_amount,
            'maskedCardNo' => $res_maskedCardNo,
            'allowAccumulate' => $res_allowAccumulate,
            'maxAccumulateAmount' => $res_maxAccumulateAmount,
            'recurringInterval' => $res_recurringInterval,
            'recurringCount' => $res_recurringCount,
            'currentCount' => $res_currentCount,
            'chargeNextDate' => $res_chargeNextDate,
        ];
        return $answer;
    }


    /**
     * @param string|null $unencrypted_payload
     * @param $secretKey
     * @return array
     * @throws CRM_Core_Exception
     */
    protected static function unencryptPaymentAnswer(?string $unencrypted_payload, $secretKey): array
    {

        $answer = json_decode(json_encode((array)simplexml_load_string($unencrypted_payload)), $unencrypted_payload);
        $answer = array_map(function ($o) {
            if (is_array($o)) {
                if (sizeof($o) == 0) {
                    return "";
                } else {
                    return array_map("strval", $o);
                }
            }
            return (string)$o;
        }, $answer);
        return $answer;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function setPaymentInquiryViaKeySignature($invoiceID, $status = "", $amount = ""): array
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);

        $payment_processor = $paymentProcessor->getPaymentProcessor();
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];

        $date = date('Y-m-d h:i:s');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
        if ($status === "V") {
            $payment_inquiry = array(
                'version' => "3.8",
                'processType' => "V",
                'invoiceNo' => $invoiceID,
                'timeStamp' => $time_stamp,
                'merchantID' => $merchant_id,
                'actionAmount' => "",
                'request_type' => "PaymentProcessRequest"
            );
        }
        if ($status === "R") {
            $payment_inquiry = array(
                'version' => "3.8",
                'processType' => "R",
                'invoiceNo' => $invoiceID,
                'timeStamp' => $time_stamp,
                'merchantID' => $merchant_id,
                'actionAmount' => $amount,
                'request_type' => "PaymentProcessRequest"
            );
        }
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
        $response_body_contents = $response->getBody()->getContents();
//        CRM_Core_Error::debug_var('response_body_contents', $response_body_contents);

        $path_to_2c2p_certificate = self::getOpenCertificatePath();
        $path_to_merchant_pem = self::getClosedCertificatePath();
        $merchant_password = self::getClosedCertificatePwd();
        $answer =
            self::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
//        CRM_Core_Error::debug_var('answersetPaymentInquiryViaKeySignature', $answer);

        return $answer;
    }

    /**
     * get_scheduled_contributions
     *
     * Gets recurring contributions that are scheduled to be processed today
     *
     * @return array An array of contribtion_recur objects
     */
    public static function get_scheduled_contribution_recurs($payment_processor_array)
    {
        $scheduled_today = new CRM_Contribute_BAO_ContributionRecur();
        // Only get contributions for the current processor
        $scheduled_today->payment_processor_id = $payment_processor_array['id'];

        // Only get contribution that are on or past schedule
        $scheduled_today->whereAdd("`next_sched_contribution_date` <= now()");
        $completed_status_id = self::contribution_status_id('Completed');
        $pending_status_id = self::contribution_status_id('Pending');
        $in_progress_status_id = self::contribution_status_id('In Progress');

        // Don't get cancelled or failed contributions
        $status_ids = implode(', ', [
            $completed_status_id,
            $in_progress_status_id,
            $pending_status_id]);
        $scheduled_today->whereAdd("`contribution_status_id` IN ({$status_ids})");

        // Exclude contributions that never completed
        $t = $scheduled_today->tableName();
        $ct = CRM_Contribute_BAO_Contribution::getTableName();
        $scheduled_today->whereAdd("EXISTS (SELECT 1 FROM `{$ct}`
        WHERE `contribution_status_id` = $completed_status_id AND `{$t}`.id = `{$ct}`.`contribution_recur_id`)");
        $status_ids = implode(', ', [
            $completed_status_id,
            $in_progress_status_id,
            $pending_status_id
        ]);

        // Exclude contributions that have already been processed
        $scheduled_today->find();

        $scheduled_contributions = [];

        while ($scheduled_today->fetch()) {
            $scheduled_contributions[] = clone $scheduled_today;
        }

        return $scheduled_contributions;
    }

    /**
     * get_scheduled_failed_contributions
     *
     * Gets recurring contributions that are failed and to be processed today
     *
     * @return array An array of contribtion_recur objects
     */
    public static function get_scheduled_failed_contributions($payment_processor)
    {
        $maxFailRetry = 5;
//        $maxFailRetry = Civi::settings()
//            ->get('Simple2c2p_recurring_contribution_max_retry');

        $scheduled_today = new CRM_Contribute_BAO_ContributionRecur();
        $scheduled_today->whereAdd("`next_sched_contribution_date` <= now()");
        $completed_status_id = self::contribution_status_id('Completed');
        $in_progress_status_id = self::contribution_status_id('In Progress');

        // Only get contributions for the current processor
        $scheduled_today->payment_processor_id = $payment_processor['id'];

        $scheduled_today->whereAdd("`failure_retry_date` <= now()");

        $scheduled_today->contribution_status_id = $in_progress_status_id;
        $scheduled_today->whereAdd("`failure_count` < " . $maxFailRetry);
        $scheduled_today->whereAdd("`failure_count` > 0");

        // CIVIEWAY-124: Exclude contributions that never completed
        $t = $scheduled_today->tableName();
        $ct = CRM_Contribute_BAO_Contribution::getTableName();
        $scheduled_today->whereAdd("EXISTS (SELECT 1 FROM `{$ct}` WHERE `contribution_status_id` = $completed_status_id AND `{$t}`.id = `contribution_recur_id`)");

        // Exclude contributions that have already been processed
        $scheduled_today->whereAdd("NOT EXISTS (SELECT 1 FROM `{$ct}` WHERE `{$ct}`.`receive_date` >= `{$t}`.`failure_retry_date` AND `{$t}`.id = `{$ct}`.`contribution_recur_id`)");

        $scheduled_today->find();

        $scheduled_failed_contributions = [];

        while ($scheduled_today->fetch()) {
            $scheduled_failed_contributions[] = clone $scheduled_today;
        }

        return $scheduled_failed_contributions;
    }

    public static function update_recurring_contribution_status($next_sched_contribution_date, $contributionRecurID)
    {
        $d_now = new DateTime();
//        CRM_Core_Error::debug_var('next_sched_in_update', $next_sched_contribution_date);
        $next_sched_iso = CRM_Utils_Date::isoToMysql($next_sched_contribution_date);
//        CRM_Core_Error::debug_var('next_sched_iso', $next_sched_iso);

        if ($next_sched_contribution_date) {
            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                $contributionRecurID,
                'next_sched_contribution_date',
                $next_sched_iso);
        } else {
            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                $contributionRecurID,
                'contribution_status_id',
                self::contribution_status_id('Completed'));
            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                $contributionRecurID,
                'end_date',
                CRM_Utils_Date::isoToMysql($d_now));
        }
    }

    public static function mark_recurring_contribution_Failed($contributionRecur)
    {
        $today = new DateTime();
        $retryDelayInDays = 1;
//        $retryDelayInDays = Civi::settings()
//            ->get('Simple2c2p_recurring_contribution_retry_delay');
        $today->modify("+" . $retryDelayInDays . " days");
        $today->setTime(0, 0, 0);

        try {
            civicrm_api3('Activity', 'create', [
                'source_contact_id' => $contributionRecur->contact_id,
                'activity_type_id' => 'Simple2c2p Transaction Failed',
                'source_record' => $contributionRecur->id,
            ]);
        } catch (CiviCRM_API3_Exception $e) {
            /* Failing to create the failure activity should not prevent the
               ContributionRecur entity from being updated. Log it and move on. */
            \Civi::log()->debug('Simple2c2p: Couldn\'t record failure activity: ' . $e->getMessage());
        }

        civicrm_api3('ContributionRecur', 'create', [
            'id' => $contributionRecur->id,
            'failure_count' => (++$contributionRecur->failure_count),
            'failure_retry_date' => $today->format("Y-m-d H:i:s"),
            // CIVIEWAY-125: Don't actually mark as failed, because that causes the UI
            // to melt down.
            // 'contribution_status_id' => _contribution_status_id('Failed'),
        ]);
    }

    /**
     * @param $payment_processor
     * @param $paymentObject
     * @return bool
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function process_recurring_payments($payment_processor)
    {
        // If an ewayrecurring job is already running, we want to exit as soon as possible.
        $lock = \Civi\Core\Container::singleton()
            ->get('lockManager')
            ->create('worker.Simple2c2precurring');
        if (!$lock->isFree() || !$lock->acquire()) {
            Civi::log()->warning("Detected processing race for scheduled payments, aborting");
            return FALSE;
        }

        //payment/contribution statuses

        $completed_status_id = self::contribution_status_id('Completed');
        $pending_status_id = self::contribution_status_id('Pending');
        $cancelled_status_id = self::contribution_status_id('Cancelled');
        $failed_status_id = self::contribution_status_id('Failed');
        $in_progress_status_id = self::contribution_status_id('In Progress');
        $overdue_status_id = self::contribution_status_id('Overdue');
        $refunded_status_id = self::contribution_status_id('Refunded');
        $partially_paid_status_id = self::contribution_status_id('Partially paid');
        $pending_refund_status_id = self::contribution_status_id('Pending refund');
        $chargeback_status_id = self::contribution_status_id('Chargeback');
        $template_status_id = self::contribution_status_id('Template');


        // Process today's scheduled contributions.
        $scheduled_recurring_contributions = self::get_scheduled_contribution_recurs($payment_processor);
        //        //We don't have failed contributions to work with

        Civi::log()->debug("Have found " . sizeof($scheduled_recurring_contributions) . " recurring contributions to do!\n");

        foreach ($scheduled_recurring_contributions as $contributionRecur) {

            if ($contributionRecur->payment_processor_id != $payment_processor['id']) {
                Civi::log()->debug("Sorry, this contributionRecur is not our processors: " . $contributionRecur->id . "\n");
                continue;
            }
            $contibutionsToCheck = 0;
            $contributionRecurID = $contributionRecur->id;
            $invoicePrefix = "";
//            CRM_Core_Error::debug_var('contributionRecurID01', $contributionRecurID);

            //check if contribution_recur is OK?
            $contributionRecurInvoiceId = $contributionRecur->invoice_id;
            $recurringUniqueID = "";

            try {

                $paymentInquiry = CRM_Simple2c2p_Utils::getPaymentInquiryViaPaymentToken($contributionRecurInvoiceId);
                if (!key_exists('token', $paymentInquiry)) {
                    Civi::log()->debug("Sorry, this contributionRecur $contributionRecurID has no Payment Token\n");
                }
                if (key_exists('token', $paymentInquiry)) {
//                    CRM_Core_Error::debug_var('paymentInquiry', $paymentInquiry);
                    $paymentTokenID = (string)$paymentInquiry['tokenID'];
                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                        $contributionRecurID,
                        'payment_token_id',
                        $paymentTokenID);
                    $recurringUniqueID = (string)$paymentInquiry['recurringUniqueID'];
                    if ($recurringUniqueID == "") {
                        $recurringUniqueID = (string)self::getRecurringPaymentIdViaPaymentToken($contributionRecurInvoiceId);
                    }
                }
            } catch (CRM_Core_Exception $e) {
                Civi::log()->debug($e->getMessage());
                Civi::log()->debug("Sorry, this contributionRecur $contributionRecurID has no recurringUniqueID\n");
            }
            if ($recurringUniqueID === "") {
                $note = new CRM_Core_BAO_Note();
                $note->entity_table = 'civicrm_contribution_recur';
                $note->contact_id = $contributionRecur->contact_id;
                $note->entity_id = $contributionRecur;
                $note->subject = ts('Contribution Error');
                $note->note = "Sorry, this contributionRecur $contributionRecurID has no recurringUniqueID\n";
                $note->save();
                self::setContributionRecurStatusCancelledOrFailed($contributionRecur,
                    $failed_status_id,
                    "",
                    "The contributionRecur $contributionRecurInvoiceId has no recurringUniqueID, check it via Admin Dashboard, Please",
                    );
                continue;
                //go to next scheduled contribution
            }
            if ($recurringUniqueID != "") {
                $processType = "I";
//$request_type = "PaymentProcessRequest";
                $version = "2.1";
                $request_type = "RecurringMaintenanceRequest";
                $recurringPaymentInquery = CRM_Simple2c2p_Utils::getPaymentInquiryViaKeySignature(
                    $contributionRecurInvoiceId,
                    $processType,
                    $request_type,
                    $version,
                    $recurringUniqueID
                );
//                CRM_Core_Error::debug_var('recurringPaymentInquery', $recurringPaymentInquery);
                $invoicePrefix = $recurringPaymentInquery['invoicePrefix'];
                CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                    $contributionRecurID,
                    'trxn_id',
                    $recurringUniqueID);
                if ($recurringPaymentInquery['recurringStatus'] == "N") { //Contribution finished
                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                        $contributionRecurID,
                        'contribution_status_id',
                        $completed_status_id);
                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                        $contributionRecurID,
                        'end_date',
                        $recurringPaymentInquery['chargeNextDate']);
                }
                if ($recurringPaymentInquery['recurringStatus'] == "Y") { //Contribution finished
                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                        $contributionRecurID,
                        'contribution_status_id',
                        $in_progress_status_id);
                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
                        $contributionRecurID,
                        'next_sched_contribution_date',
                        $recurringPaymentInquery['chargeNextDate']);
                }
                $contibutionsToCheck = $recurringPaymentInquery['currentCount'];
//                continue;
            }
//            continue;
            // Re-check schedule time, in case contributionRecur already processed.

            /* Get the number of Completed Contributions
            already recorded for this Schedule. */
            $madeContributions = civicrm_api3('Contribution', 'get', [
                'options' => ['limit' => 0, 'sort' => "id ASC"],
                'sequential' => 1,
                'contribution_status_id' => ['NOT IN' => [$chargeback_status_id, $template_status_id]],
//                'return' => ['total_amount', 'tax_amount'],
                'contribution_recur_id' => $contributionRecurID,
            ]);
            $completedContributionsCount = $madeContributions['count'];
            $madeContributions = $madeContributions['values'];
            $firstContribution = array_shift($madeContributions);
            Civi::log()->debug("Completed Contributions Count for $contributionRecurID: $completedContributionsCount\n");
            Civi::log()->debug("Contributions to check for $contributionRecurID: $contibutionsToCheck\n");
            Civi::log()->debug("Invoice Prefix to check for $contributionRecurID: $contibutionsToCheck\n");
            $contributionNumber = 1;
            while ($contibutionsToCheck > 0) {
                $number = substr('00000' . $contributionNumber, -5);
                $current_invoice_id = $invoicePrefix . $number;//
                Civi::log()->debug("Current Invoice Number to check for $contributionRecurID: $current_invoice_id\n");
                //get contribution from CiviCRM
                $currentContributionsAPI = civicrm_api3('Contribution', 'get', [
//                    'options' => ['limit' => 0, 'sort' => "id ASC"],
                    'sequential' => 1,
                    'invoice_id' => (string)$current_invoice_id,
                ]);
                CRM_Core_Error::debug_var('currentContributionsAPI', $currentContributionsAPI);
                CRM_Core_Error::debug_var('current_invoice_id', $current_invoice_id);

                //get contribution from 2c2p
                if ($currentContributionsAPI['count'] <= 0) {
                    $apiContribution = self::getPaymentInquiryViaKeySignature($current_invoice_id);
//                    CRM_Core_Error::debug_var('apiContribution', $apiContribution);
                    $currentContribution = [];
                    Civi::log()->debug("We should create Current Invoice Number $contributionRecurID: $current_invoice_id\n");
                    $new_contribution = self::processNewContributionFromRecurring(
                        $current_invoice_id,
                        $contributionRecur,
                        $firstContribution,
                        $apiContribution);
                }
                if ($currentContributionsAPI['count'] > 0) {
                    Civi::log()->debug("We skip Current Invoice Number $contributionRecurID: $current_invoice_id\n");
                }
//                $paymentProcessorInfo = array_shift($paymentProcessorInfo);
                $contributionNumber = $contributionNumber + 1;
                $contibutionsToCheck = $contibutionsToCheck - 1;
            }

        }

        $lock->release();
        return TRUE;
    }

//    /**
//     * @param $contributionRecurID
//     * @param $currentContributionID
//     * @param $contributionRecur
//     * @param $madeContributionsFirst
//     * @param $pending_status_id
//     * @param $failed_status_id
//     * @param $completed_status_id
//     * @param $in_progress_status_id
//     * @return array
//     * @throws CiviCRM_API3_Exception
//     */
//    private static function processPresentContributionFromRecurring(
//        $contributionRecur,
//        $currentContribution,
//        $pending_status_id,
//        $failed_status_id,
//        $completed_status_id,
//        $in_progress_status_id
//        ): array
//    {
//        try {
//
//            $new_contribution_record = $currentContribution;
//            $contribution_recieve_date = CRM_Utils_Date::isoToMysql(date('Y-m-d H:i:s')); //todo
//            Civi::log()->debug("Creating Contribution $invoice_id for $contributionRecurID contributionRecur record\n");
//            $new_contribution_record['contact_id'] = $contributionRecur->contact_id;
//            $new_contribution_record['receive_date'] = $contribution_recieve_date;
//            $new_contribution_record['total_amount'] = $contributionTotalAmount;
//            $new_contribution_record['contribution_recur_id'] = $contributionRecurID;
//            $new_contribution_record['payment_instrument_id'] = $contributionRecur->payment_instrument_id;
//            $new_contribution_record['invoice_id'] = $invoice_id;
//            $new_contribution_record['invoice_number'] = $contributionRecur->invoice_id;
//            $new_contribution_record['campaign_id'] = $contributionRecur->campaign_id;
//            $new_contribution_record['financial_type_id'] = $contributionRecur->financial_type_id;
//            $new_contribution_record['payment_processor'] = $contributionRecur->payment_processor_id;
//            $new_contribution_record['payment_processor_id'] = $contributionRecur->payment_processor_id;
//
//
//            $precedent = new CRM_Contribute_BAO_Contribution();
//            $precedent->id = $madeContributionsFirst['id'];
//
//            $contributionSource = '';
//            $contributionPageId = '';
//            $contributionIsTest = 0;
//
//            if ($precedent->find(TRUE)) {
//                $contributionSource = $precedent->source;
//                $contributionPageId = $precedent->contribution_page_id;
//                $contributionIsTest = $precedent->is_test;
//            }
//
//            try {
//                $financial_type = civicrm_api3(
//                    'FinancialType', 'getsingle', [
//                    'sequential' => 1,
//                    'return' => "name",
//                    'id' => $contributionRecur->financial_type_id,
//                ]);
//            } catch (CiviCRM_API3_Exception $e) { // Most likely due to FinancialType API not being available in < 4.5 - try DAO directly
//                $ft_bao = new CRM_Financial_BAO_FinancialType();
//                $ft_bao->id = $contributionRecur->financial_type_id;
//                $found = $ft_bao->find(TRUE);
//                $financial_type = (array)$ft_bao;
//            }
//
//
//            if (!isset($financial_type['name'])) {
//                throw new Exception (
//                    "Financial type could not be loaded for {$contributionRecur->id}"
//                );
//            }
//
//            $new_contribution_record['source'] = "Simple2c2p Recurring {$financial_type['name']}:\n{$contributionSource}";
//            $new_contribution_record['contribution_page_id'] = $contributionPageId;
//            $new_contribution_record['is_test'] = $contributionIsTest;
//
//            // Retrieve the eWAY token
//
////                if (!empty($contributionRecur->payment_token_id)) {
////                    try {
////                        $token = civicrm_api3('PaymentToken', 'getvalue', [
////                            'return' => 'token',
////                            'id' => $contributionRecur->payment_token_id,
////                        ]);
////                    } catch (CiviCRM_API3_Exception $e) {
////                        $token = $contributionRecur->processor_id;
////                    }
////                } else {
////                    $token = $contributionRecur->processor_id;
////                }
//
////                if (!$token) {
//////                    throw new CRM_Core_Exception(E::ts('No token found for Recurring Contribution %1', [1 => $contributionRecur->id]));
////                }
//
//            $p2c2pResponse = self::getPaymentInquiryViaKeySignature($invoice_id);
//            CRM_Core_Error::debug_var('p2c2pResponseToDo', $p2c2pResponse);
//            $p2c2pResponse = [];//@todo
//            $p2c2pResponse['TransactionID'] = $invoice_id;//@todo
//            $p2c2pResponse['Errors'] = [];//@todo
//            $p2c2pResponse['ResponseMessages'] = [];//@todo
//            $p2c2pResponse['Status'] = TRUE;//@todo
//            $new_contribution_record['trxn_id'] = $p2c2pResponse['TransactionID'];
//
//            $responseErrors = $p2c2pResponse['Errors'];
//
//            if ($p2c2pResponse['Status']) {
//                $responseMessages = $p2c2pResponse['ResponseMessages']; //@todo
//                $responseErrors = array_merge($responseMessages, $responseErrors);
//            }
//
//            if (count($responseErrors)) {
//                // Mark transaction as failed
//                $new_contribution_record['contribution_status_id'] = $failed_status_id;
//                self::mark_recurring_contribution_Failed($contributionRecur);
//            } else {
//                // send_receipt_email($new_contribution_record->id);
//                $new_contribution_record['contribution_status_id'] = $completed_status_id;
//
//                $new_contribution_record['is_email_receipt'] = 0;
//
//                if ($contributionRecur->failure_count > 0
//                    && $contributionRecur->contribution_status_id == $failed_status_id) {
//                    // Failed recurring contributionRecur completed successfuly after several retry.
//                    self::update_recurring_contribution_status($next_sched_contribution_date, $contributionRecurID);
//                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                        $contributionRecur->id,
//                        'contribution_status_id',
//                        $in_progress_status_id);
//
//                    try {
//                        civicrm_api3('Activity', 'create', [
//                            'source_contact_id' => $contributionRecur->contact_id,
//                            'activity_type_id' => 'Simple2c2p Transaction Succeeded',
//                            'source_record' => $contributionRecur->id,
//                            'details' => 'Transaction Succeeded after '
//                                . $contributionRecur->failure_count . ' retries',
//                        ]);
//                    } catch (CiviCRM_API3_Exception $e) {
//                        \Civi::log()->debug('Simple2c2p Recurring: Couldn\'t record success activity: ' . $e->getMessage());
//                    }
//                }
//
//                CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                    $contributionRecur->id, 'failure_count', 0);
//
//                CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                    $contributionRecur->id, 'failure_retry_date', '');
//            }
//
//            $api_action = (
//            $new_contribution_record['contribution_status_id'] == $completed_status_id
//                ? 'completetransaction'
//                : 'create'
//            );
//
//            $updated = civicrm_api3('Contribution', $api_action, $new_contribution_record);
//
//            $updated_contribution_record = reset($updated['values']);
//
//            // The invoice_id does not seem to be recorded by
//            // Contribution.completetransaction, so let's update it directly.
//            if ($api_action === 'completetransaction') {
//                $updated = civicrm_api3('Contribution', 'create', [
//                    'id' => $updated_contribution_record['id'],
//                    'invoice_id' => $invoice_id,
//                ]);
//                $updated_contribution_record = reset($updated['values']);
//            }
//
//            if (count($responseErrors)) {
//                $note = new CRM_Core_BAO_Note();
//
//                $note->entity_table = 'civicrm_contribution';
//                $note->contact_id = $contributionRecur->contact_id;
//                $note->entity_id = $updated_contribution_record['id'];
//                $note->subject = ts('Transaction Error');
//                $note->note = implode("\n", $responseErrors);
//
//                $note->save();
//            }
//
//            // Civi::log()->debug("Save contributionRecur with trxn_id {$new_contribution_record->trxn_id}");
//
//        } catch (Exception $e) {
//            Civi::log()->warning("Processing payment {$contributionRecur->id} for {$contributionRecur->contact_id}: " . $e->getMessage());
//
//            // already talk to Simple2c2p? then we need to check the payment status
//            if ($p2c2pResponse['Status']) {
//                $new_contribution_record['contribution_status_id'] = $pending_status_id;
//            } else {
//                $new_contribution_record['contribution_status_id'] = $failed_status_id;
//            }
//
//            $updated = civicrm_api3('Contribution', 'create', $new_contribution_record);
//            $updated_contribution_record = reset($updated['values']);
//            // CIVIEWAY-147 there is an unknown system error that happen after civi talks to eway
//            // It might be a cache cleaning task happening at the same time that break this task
//            // Defer the query later to update the contributionRecur status
//            if ($p2c2pResponse['Status']) {
////                    $ewayParams = [
////                        'access_code' => $eWayResponse->TransactionID,
////                        'contribution_id' => $new_contribution_record['id'],
////                        'payment_processor_id' => $contributionRecur->payment_processor_id,
////                    ];
////                    civicrm_api3('EwayContributionTransactions', 'create', $ewayParams);
//            } else {
//                // Just mark it failed when eWay have no info about this at all
//                self::mark_recurring_contribution_Failed($contributionRecur);
//            }
//
//            $note = new CRM_Core_BAO_Note();
//
//            $note->entity_table = 'civicrm_contribution';
//            $note->contact_id = $contributionRecur->contact_id;
//            $note->entity_id = $updated_contribution_record['id'];
//            $note->subject = ts('Contribution Error');
//            $note->note = $e->getMessage();
//
//            $note->save();
//        }
//        return array($e, $note);
//    }

    /**
     * @param $contributionRecur
     * @param $currentContribution
     * @param $apiContribution
     * @param $pending_status_id
     * @param $failed_status_id
     * @param $completed_status_id
     * @param $in_progress_status_id
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public static function processNewContributionFromRecurring(
        $invoice_id,
        $contributionRecur,
        $firstContribution,
        $apiContribution): array
    {

        $completed_status_id = self::contribution_status_id('Completed');
        $pending_status_id = self::contribution_status_id('Pending');
        $cancelled_status_id = self::contribution_status_id('Cancelled');
        $failed_status_id = self::contribution_status_id('Failed');
        $in_progress_status_id = self::contribution_status_id('In Progress');
        $overdue_status_id = self::contribution_status_id('Overdue');
        $refunded_status_id = self::contribution_status_id('Refunded');
        $partially_paid_status_id = self::contribution_status_id('Partially paid');
        $pending_refund_status_id = self::contribution_status_id('Pending refund');
        $chargeback_paid_status_id = self::contribution_status_id('Chargeback');
        $template_paid_status_id = self::contribution_status_id('Template');
        $apiContributionRespStatus = $apiContribution['respCode'];
        $apiContributionStatus = $apiContribution['status'];
        $contributionRecurID = $contributionRecur->id;
        $updated_contribution_record = [];
        $new_contribution_record = [];
        CRM_Core_Error::debug_var('invoice_id', $invoice_id);
        CRM_Core_Error::debug_var('contributionRecur', $contributionRecur);
        CRM_Core_Error::debug_var('firstContribution', $firstContribution);
        CRM_Core_Error::debug_var('apiContribution', $apiContribution);
//        CRM_Core_Error::debug_var('updated_contribution_record1', $updated_contribution_record);
        if (empty($firstContribution['tax_amount'])) {
            $firstContribution['tax_amount'] = 0;
        }
        $madeContributionTaxAmount = $firstContribution['tax_amount'];
        $contributionAmount = $contributionRecur->amount;
        $contributionTotalAmount = $contributionAmount - $madeContributionTaxAmount;
        $contribution_params = [
            'contribution_recur_id' => $contributionRecurID,
            'contribution_status_id' => $pending_status_id,
            'total_amount' => $contributionTotalAmount,
            'invoice_id' => $invoice_id,
            'is_email_receipt' => 0,
        ];

        try {

//                $madeContributionsFirst = $madeContributions[0];
            try {
                Civi::log()->info("Try to make repeat_contributionAPI");
                $repeat_contributionAPI = civicrm_api3('Contribution', 'repeattransaction', $contribution_params);
                $new_contribution_record_array = $repeat_contributionAPI['values'];
                $repeat_contribution = array_shift($new_contribution_record_array);
            } catch (CiviCRM_API3_Exception $e) {
                Civi::log()->warning("Having error in repeattransaction payment {$contributionRecur->id} for {$contributionRecur->contact_id}: " . $e->getMessage());
                $repeat_contribution = [];
            }

            $new_contribution_record = (array)$repeat_contribution;
//            $new_contribution_record['id'] = $updated_contribution_record['id'];
            Civi::log()->debug("Creating Contribution $invoice_id for $contributionRecurID contributionRecur record\n");
            if (key_exists('transactionDateTime', $apiContribution)) {
                $contribution_recieve_date = $apiContribution['transactionDateTime'];
            }
            if (!key_exists('transactionDateTime', $apiContribution)) {
                $contribution_recieve_date = CRM_Utils_Date::isoToMysql(date('Y-m-d H:i:s'));
            }
            $new_contribution_record['receive_date'] = $contribution_recieve_date;
            $new_contribution_record['contact_id'] = $contributionRecur->contact_id;
            $new_contribution_record['total_amount'] = $contributionTotalAmount;
            $new_contribution_record['financial_type_id'] = $contributionRecur->financial_type_id;
            $new_contribution_record['contribution_recur_id'] = $contributionRecurID;
            $new_contribution_record['payment_instrument_id'] = $contributionRecur->payment_instrument_id;
            $new_contribution_record['invoice_id'] = $invoice_id;
            $new_contribution_record['invoice_number'] = $contributionRecur->invoice_id;
            $new_contribution_record['campaign_id'] = $contributionRecur->campaign_id;
            $new_contribution_record['payment_processor'] = $contributionRecur->payment_processor_id;
            $new_contribution_record['payment_processor_id'] = $contributionRecur->payment_processor_id;
            $new_contribution_record['is_template'] = $firstContribution['is_template'];
            $new_contribution_record['non_deductible_amount'] = $firstContribution['non_deductible_amount'];

            $contributionSource = $firstContribution['contribution_source'];
            $contributionPageId = $firstContribution['contribution_page_id'];
            $contributionIsTest = $firstContribution['is_test'];
            $financial_type = $firstContribution['financial_type'];

            $new_contribution_record['source'] = "Simple2c2p Recurring {$financial_type}:\n{$contributionSource}";
            $new_contribution_record['contribution_page_id'] = $contributionPageId;
            $new_contribution_record['is_test'] = $contributionIsTest;
            if (key_exists('referenceNo', $apiContribution)) {
                $new_contribution_record['trxn_id'] = $apiContribution['referenceNo'];
            }
            if (!key_exists('referenceNo', $apiContribution)) {
                $new_contribution_record['trxn_id'] = $invoice_id;
            }
            $new_contribution_record['is_email_receipt'] = 0;

            if ($apiContributionRespStatus != '00') {
                // Mark transaction as failed
                $new_contribution_record['contribution_status_id'] = $failed_status_id;
//                $new_contribution_record['is_email_receipt'] = 0;
            }

            if ($apiContributionRespStatus == '00') {
                // send_receipt_email($new_contribution_record->id);
                if ($apiContributionStatus == "S") {
                    $new_contribution_record['contribution_status_id'] = $completed_status_id;
                }
                if ($apiContributionStatus == "A") {
                    $new_contribution_record['contribution_status_id'] = $completed_status_id;
                }
                if ($apiContributionStatus == "V") {
                    $new_contribution_record['contribution_status_id'] = $cancelled_status_id;
                }
                if (in_array($apiContributionStatus, [
                    "AR",
                    "FF",
                    "IP",
                    "ROE",
                    "EX",
                    "CTF"])) {
                    $new_contribution_record['contribution_status_id'] = $failed_status_id;
                }

                if ($apiContributionStatus == "RF") {
                    $new_contribution_record['contribution_status_id'] = $refunded_status_id;
                }

                if (in_array($apiContributionStatus, ["AP", "RP", "VP"])) {
                    $new_contribution_record['contribution_status_id'] = $pending_status_id;
                }

                if ($apiContributionStatus == "RS") {
                    $new_contribution_record['contribution_status_id'] = $in_progress_status_id;
                }
            }

            $api_action = (
            $new_contribution_record['contribution_status_id'] == $completed_status_id
                ? 'completetransaction'
                : 'create'
            );
            CRM_Core_Error::debug_var('new_contribution_record', $new_contribution_record);
            CRM_Core_Error::debug_var('api_action', $api_action);
            $updatedAPI = civicrm_api3('Contribution', $api_action, $new_contribution_record);
            $updated_contribution_record = reset($updatedAPI['values']);
            CRM_Core_Error::debug_var('completed_contribution_record', $updated_contribution_record);

            // Many things does not seem to be recorded by
            // Contribution.completetransaction, so let's update it directly.
            if ($api_action === 'completetransaction') {
                $updatedAPI = civicrm_api3('Contribution', 'create', $new_contribution_record);
                $updated_contribution_record = reset($updatedAPI['values']);
                CRM_Core_Error::debug_var('completed_saved_contribution_record', $updated_contribution_record);
            }

            if ($apiContributionRespStatus != '00') {
                $note = new CRM_Core_BAO_Note();

                $note->entity_table = 'civicrm_contribution';
                $note->contact_id = $contributionRecur->contact_id;
                $note->entity_id = $updated_contribution_record['id'];
                $note->subject = ts('Transaction Error');
                $note->note = "Check https://developer.2c2p.com/docs/response-code-payment-maintenance-result-code\n
                Current error code for repeat_contributionAPI $invoice_id : $apiContributionRespStatus";
                $note->save();
            }

            // Civi::log()->debug("Save contributionRecur with trxn_id {$new_contribution_record->trxn_id}");

        } catch (Exception $e) {
            Civi::log()->warning("Having error in Processing payment {$contributionRecur->id} for {$contributionRecur->contact_id}: " . $e->getMessage());
            $new_contribution_record['contact_id'] = $contributionRecur->contact_id;
            $new_contribution_record['total_amount'] = $contributionTotalAmount;
            $new_contribution_record['financial_type_id'] = $contributionRecur->financial_type_id;

            // already talk to Simple2c2p? then we need to check the payment status
            if ($apiContributionRespStatus == '00') {
                $new_contribution_record['contribution_status_id'] = $pending_status_id;
            } else {
                $new_contribution_record['contribution_status_id'] = $failed_status_id;
            }

            $updatedAPI = civicrm_api3('Contribution', 'create', $new_contribution_record);
            $updated_contribution_record = reset($updatedAPI['values']);
            $note = new CRM_Core_BAO_Note();
//            CRM_Core_Error::debug_var('updated_contribution_record4', $updated_contribution_record);

            $note->entity_table = 'civicrm_contribution';
            $note->contact_id = $contributionRecur->contact_id;
            $note->entity_id = $updated_contribution_record['id'];
            $note->subject = ts("Contribution Error for") . "  $invoice_id";
            $note->note = $e->getMessage();
            $note->save();
        }
        return $updated_contribution_record;
    }

    /**
     * @param $form
     */
    public static function add_UpdateFromServerButtonToContributionViewForm(&$form)
    {
//                CRM_Core_Error::debug_var('form', $form);
        $isHasAccess = FALSE;
        $action = 'update';
        $id = $form->get('id');
        try {
            $isHasAccess = Civi\Api4\Contribution::checkAccess()
                ->setAction($action)
                ->addValue('id', $id)
                ->execute()->first()['access'];
        } catch (API_Exception $e) {
            $isHasAccess = FALSE;
        }
        if ($isHasAccess) {

            $contribution = Civi\Api4\Contribution::get(TRUE)
                ->addWhere('id', '=', $id)->addSelect('*')->execute()->first();
            if (empty($contribution)) {
                CRM_Core_Error::statusBounce(ts('Access to contribution not permitted'));
            }
            // We just cast here because it was traditionally an array called values - would be better
            // just to use 'contribution'.
            $values = (array)$contribution;
            $invoiceId = $values['invoice_id'];
//            $contributionStatus = CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $values['contribution_status_id']);
//            if ($contributionStatus == 'Pending') {
//                CRM_Core_Error::debug_var('values', $values);
//                CRM_Core_Error::debug_var('contributionStatus', $contributionStatus);
            if (isset($form->get_template_vars()['linkButtons'])) {
                $linkButtons = $form->get_template_vars()['linkButtons'];
                $urlParams = "reset=1&invoiceId={$invoiceId}";
                $linkButtons[] = [
                    'title' => ts('Update Status from 2c2p'),
//                'name' => ts('Update Status from 2c2p'),
                    'url' => 'civicrm/Simple2c2p/checkpending',
                    'qs' => $urlParams,
                    'icon' => 'fa-pencil',
                    'accessKey' => 'u',
                    'ref' => '',
                    'name' => '',
                    'extra' => '',
                ];
                $form->assign('linkButtons', $linkButtons ?? []);
            }
        }
    }

    /**
     * @param $objectId
     * @return bool
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function Simple2c2p_cancel_related_2c2p_record($objectId)
    {
        CRM_Core_Error::debug_var('started_canceling_related', date("Y-m-d H:i:s"));
        CRM_Core_Payment_Simple2c2p::setCancelledContributionStatus($objectId);
        CRM_Core_Error::debug_var('ended_canceling_related', date("Y-m-d H:i:s"));
        return TRUE;
    }

    /**
     * @param $op
     * @param $objectName
     * @param $objectId
     * @param $objectRef
     * @return bool
     */
    public static function cancelServer2c2pPayment($op, $objectName, $objectId, $objectRef): bool
    {
//    CRM_Core_Error::debug_var('started_civicrm_post', date("Y-m-d H:i:s"));
//    CRM_Core_Error::debug_var('op', $op);
//    CRM_Core_Error::debug_var('objectName', $objectName);
        if ($op === 'edit' && $objectName === 'Contribution') {
            if (in_array(CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution',
                'contribution_status_id',
                $objectRef->contribution_status_id),
                ['Cancelled', 'Failed']
            )) {
//            CRM_Core_Error::debug_var('objectName', $objectName);
//            CRM_Core_Error::debug_var('objectId', $objectId);
//            CRM_Core_Error::debug_var('objectRef', $objectRef);
                CRM_Simple2c2p_Utils::Simple2c2p_cancel_related_2c2p_record((int)$objectId);
                return TRUE;
            }
        } elseif ($op = 'create') {
//        CRM_Core_Error::debug_var('objectName', $objectName);
//        CRM_Core_Error::debug_var('objectId', $objectId);
//        CRM_Core_Error::debug_var('objectRef', $objectRef);
//        CRM_Core_Error::debug_var('ended_create_civicrm_post', date("Y-m-d H:i:s"));
            return TRUE;
        } else {
//        CRM_Core_Error::debug_var('objectName', $objectName);
//        CRM_Core_Error::debug_var('objectId', $objectId);
//        CRM_Core_Error::debug_var('objectRef', $objectRef);
//        CRM_Core_Error::debug_var('ended_something_else', date("Y-m-d H:i:s"));
            return TRUE;

        }
        CRM_Core_Error::debug_var('ended_civicrm_post', date("Y-m-d H:i:s"));
        return TRUE;
    }

//    function process_2c2p_payment(
//        $contribution_invoice_id,
//        $amount_in_cents,
//        $invoice_reference,
//        $invoice_description)
//    {
//
//        static $prev_response = NULL;
//
//        $paymentTransaction = [
//            'Customer' => [
//                'TokenCustomerID' => substr($managed_customer_id, 0, 16)
//            ],
//            'Payment' => [
//                'TotalAmount' => substr($amount_in_cents, 0, 10),
//                'InvoiceDescription' => substr(trim($invoice_description), 0, 64),
//                'InvoiceReference' => substr($invoice_reference, 0, 64),
//            ],
//            'TransactionType' => \Eway\Rapid\Enum\TransactionType::MOTO
//        ];
//        $eWayResponse = $eWayClient->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $paymentTransaction);
//
//        if (isset($prev_response) && $prev_response->getAttribute('TransactionID') == $eWayResponse->getAttribute('TransactionID')) {
//            throw new Exception (
//                'eWay ProcessPayment returned duplicate transaction number: ' .
//                $prev_response->getAttribute('TransactionID') . ' vs ' . $eWayResponse->getAttribute('TransactionID')
//            );
//        }
//
//        $prev_response = &$eWayResponse;
//
//        return $eWayResponse;
//    }


}