<?php

use CRM_Simple2c2p_ExtensionUtil as E;

//use Jose\Component\KeyManagement\JWKFactory;
//use Jose\Component\Core\AlgorithmManager;
//use Jose\Component\Encryption\{
//    Algorithm\KeyEncryption\RSAOAEP,
//    Algorithm\ContentEncryption\A256GCM,
//    Compression\CompressionMethodManager,
//    Compression\Deflate,
//    JWEBuilder,
//    JWELoader,
//    JWEDecrypter,
//    JWETokenSupport,
//    Serializer\CompactSerializer as EncryptionCompactSerializer,
//    Serializer\JWESerializerManager
//};
//
//use \Jose\Component\Checker\AlgorithmChecker;
//use \Jose\Component\Checker\HeaderCheckerManager;
//use Jose\Component\Signature\{
//    Algorithm\PS256,
//    JWSBuilder,
//    JWSTokenSupport,
//    JWSLoader,
//    JWSVerifier,
//    Serializer\JWSSerializerManager,
//    Serializer\CompactSerializer as SignatureCompactSerializer};

//$autoload = __DIR__ . '/vendor/autoload.php';
//if (file_exists($autoload)) {
////    CRM_Core_Error::debug_var('autoload1', $autoload);
//    require_once $autoload;
//} else {
//    $autoload = E::path() . '/vendor/autoload.php';
//    if (file_exists($autoload)) {
//        require_once $autoload;
////        CRM_Core_Error::debug_var('autoload2', $autoload);
//    }
//}

use \Firebase\JWT\JWT;

class CRM_Simple2c2p_Utils
{
    const OPEN_CERTIFICATE_FILE_NAME = "public.cer";
    const CLOSED_CERTIFICATE_FILE_NAME = "private.pem";
    const CLOSED_CERTIFICATE_PWD = "octopus8";


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
     * @param $name
     * @return int|string|null
     */
    public static function contribution_status_id($name)
    {
        return CRM_Utils_Array::key($name, \CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));
    }


}