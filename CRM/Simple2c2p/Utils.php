<?php

use CRM_Simple2c2p_ExtensionUtil as E;

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
        self::write_log($contribution_status_id, 'setContributionStatusCancelledOrFailed contribution_status_id');
        self::write_log($reason_message, 'setContributionStatusCancelledOrFailed reason_message');

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
        self::write_log($now, 'setContributionStatusCancelledOrFailed now');
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
     * @param $input
     * @param $preffix_log
     */
    public static function write_log($input, $preffix_log)
    {
        $simple2c2p_settings = CRM_Core_BAO_Setting::getItem("Simple2c2p Settings", 'simple2c2p_settings');
        if ($simple2c2p_settings['save_log'] == '1') {
            $masquerade_input = $input;
            if (is_array($masquerade_input)) {
                $fields_to_hide = ['Signature'];
                foreach ($fields_to_hide as $field_to_hide) {
                    unset($masquerade_input[$field_to_hide]);
                }
                Civi::log()->debug($preffix_log . "\n" . print_r($masquerade_input, TRUE));
                return;
            }
            Civi::log()->debug($preffix_log . "\n" . $masquerade_input);
            return;
        }
    }

}