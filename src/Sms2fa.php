<?php

namespace Cecula\Proprietor;

use CeculaSyncApiClient\SyncSms;
use CeculaSyncApiClient\SyncAccount;
use Cecula\Proprietor\Proprietor;

use Ramsey\Uuid\Uuid;
use Cecula\Proprietor\Responses;

class Sms2fa extends Proprietor
{

    /**
     * Initialize 2FA
     * This method is used for initializing sms two factor authentication.
     *
     * @param string $mobile
     * @return array
     */
    public function init(string $mobile): array
    {
        // Check that user has sufficient balance to send the message
        $syncAccount = new SyncAccount();
        if (!$syncAccount->balanceIsInsufficient()) return Responses::getResponse("CE1804");

        // Generate SMS OTP According to user's configuration
        $otp = $this->generateOtp();
        
        // Apply SMS OTP to specified template
        $message = $this->generateMessage($otp);

        // Initialize SMS Sending Request to valid number
        $syncSms = new SyncSms();
        $responseObj = $syncSms->sendSMS($message, [$mobile]);

        if ($responseObj->status == "1801") {
            $uuid = Uuid::uuid4(); // Generate a reference for the request

            // If message was sent successfully, save the trackingId and OTP Code to storage of of choice
            $this->saveOTP($mobile, $otp, $uuid, $responseObj->report[0]->trackingId);

            // Show success message
            return [
                'code'      => '200',
                'message'   => sprintf('OTP Code has been sent to %s. Persist the reference to database.', $mobile),
                'reference' => $uuid
            ];
        } else {
            return $responseObj;
        }
    }

    /**
     * Generate Message
     * This method generates OTP SMS Message from the message template
     *
     * @param string $otp
     * @return string
     */
    private function generateMessage(string $otp): string
    {
        return str_replace("{code}", $otp, $this->settings->otp->messageTemplate);
    }


    /**
     * Generate OTP
     * This method generates OTP Code according to settings
     *
     * @return string
     */
    private function generateOtp(): string
    {
        $numeric = '0123456789';
        $alphabets = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $alphanumeric = $numeric.$alphabets;

        switch ($this->settings->otp->characters) {
            case "alnum":
            case "alphanumeric":
                return $this->generateOtpHelper($alphanumeric);
                break;
            case "alpha":
            case "alphabets":
                return $this->generateOtpHelper($alphabets);
                break;
            case 'digit':
            case 'digits':
            case 'number':
            case 'numbers':
            default:
                return $this->generateOtpHelper($numeric);
                break;
        }
    }


    /**
     * Geenerate OTP Helper
     * This method receives OTP Character set and generates OTP
     *
     * @param string $characterSet
     * @return string
     */
    private function generateOtpHelper(string $characterSet): string
    {
        $totalChars = strlen($characterSet);
        $otp = '';
        for ($i=0; $i < $this->settings->otp->length; $i++) { 
            $otp .= substr($characterSet, rand(0, $totalChars-1), 1);
        }
        return $otp;
    }


    /**
     * Complete OTP
     * This endpoint is used for completing SMS OTP Verification via hosted sim
     *
     * @param string $trackingId
     * @param string $oneTimePin
     * @return array
     */
    public function complete(string $trackingId, string $oneTimePin): array
    {
        // Validate the tracking ID
        if (!$this->validateTrackingId($trackingId)) return Responses::getResponse("CE204");

        // Validate the submitted OTP
        if(!$this->isValidOTP($oneTimePin)) return Responses::getResponse("CE203");

        // Fetch data for request with the submitted trackingId
        // Query for record of the submitted private Key
        $trackingInfo = $this->getOtpRequestData($trackingId);

        // If record is not found for the submitted Key, return error message
        if (empty($trackingInfo)) return Responses::getResponse("CE205");

        // Check if verification has been completed previously
        if ($trackingInfo->completed === 1) return Responses::getResponse("CE201");

        // Check if OTP Code has expired
        if (time() > (int) $trackingInfo->created + $this->settings->otp->validityPeriod) return Responses::getResponse("CE202");

        // Check that submitted OTP matches the fetched OTP
        if ($trackingInfo['otp'] != $oneTimePin) return Responses::getResponse("CE203");

        // Flag the OTP as verified
        $this->flagOtpVerificationCompleted($trackingId);

        // Output success message
        return [
            'code'      => '200',
            'message'   => sprintf('Mobile number %s has been successfully verified', $trackingInfo->mobile)
        ];
    }


    /**
     * Validate OTP
     * This method is used to validate one time pins submitted by users
     *
     * @param string $otp
     * @return boolean
     */
    private function isValidOTP(string $otp)
    {
        switch ($$this->settings->otp->characters) {
            case "alnum":
                case "alphanumeric":
                    return ctype_alnum($otp);
                    break;
                case "alpha":
                case "alphabets":
                    return ctype_alpha($otp);
                    break;
                case 'digit':
                case 'digits':
                case 'number':
                case 'numbers':
                default:
                    return ctype_digit($otp);
                    break;
        }
    }
}