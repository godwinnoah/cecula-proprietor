<?php

namespace Cecula\Proprietor\Sms2fa;

use CeculaSyncApiClient\SyncSms;
use CeculaSyncApiClient\SyncAccount;
use Cecula\Proprietor\Proprietor;

use Ramsey\Uuid\Uuid;
use Cecula\Proprietor\Responses;

class Sms2fa extends Proprietor
{
    private $settings;

    public function __construct()
    {
        $this->settings = $this->credentials;
    }

    public function init(string $mobile, string $templateName = 'default'): array
    {
        // TODO: Validate Number
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

            // TODO: If message was sent successfully, save the trackingId and OTP Code to storage of of choice

            // TODO: Return a trackingId (persisted to storage), a success code, and success message
            return [
                'code'      => 'CE200',
                'message'   => sprintf('OTP Code has been sent to %s. Persist the reference to database.', $mobile),
                'reference' => Uuid::uuid4()
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


    public function complete(string $trackingId, string $oneTimePin): array
    {
        // Validate the tracking ID
        if (!$this->validateTrackingId($trackingId)) return Responses::getResponse("CE204");

        // Validate the submitted OTP
        if(!$this->isValidOTP($oneTimePin)) return Responses::getResponse("CE203");

        // Fetch data for request with the submitted trackingId
        $trackingInfo = [];

        // TODO: If record is not found for the submitted trackingId, return error message
        if (empty($trackingInfo)) {
            return [
                'code'     => '',
                'message'  => 'You have submitted an invalid tracking ID'
            ];
        }

        // Check if verification has been completed previously
        if ($trackingInfo['completed'] === 1) return Responses::getResponse("CE201");

        // Check if OTP Code has expired
        if (time() > (int) $trackingInfo['created'] + $this->settings->otp->validityPeriod) return Responses::getResponse("CE202");

        // Check that submitted OTP matches the fetched OTP
        if ($trackingInfo['otp'] != $oneTimePin) return Responses::getResponse("CE203");

        // TODO: Flag the OTP as verified

        // TODO: Output success message
        return [
            'code'     => '',
            'message'  => ''
        ];
    }


    /**
     * Validate Tracking ID
     * This method is used to validate tracking ID
     *
     * @param string $trackingId
     * @return boolean
     */
    private function validateTrackingId(string $trackingId): bool
    {
        $pattern = "/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/";
        preg_match($pattern, $trackingId, $matches);
        return count($matches) > 0;
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