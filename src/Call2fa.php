<?php

namespace Cecula\Proprietor;

use CeculaSyncApiClient\SyncAccount;
use Ramsey\Uuid\Uuid;
use Cecula\Proprietor\Proprietor;

class Call2fa extends Proprietor
{
    private SyncAccount $syncAccount;

    // The amount of time service will spend waiting for call
    private static int $callWaitTime = 30;

    // The webhook file will usually be located at the project root directory
    private static string $webhookFile = "synchook.php";

    function __construct()
    {
        parent::__construct();

        $this->syncAccount = new SyncAccount();
    }



    /**
     * Initialize Call
     * This method initializes the call verification process. The method will:
     * 1. Submit a dynamicWebhook where call notification should be submitted to
     * 2. Store the mobile number to database for tracking
     *
     * @param string $mobile
     * @return object
     */
    public function init(string $mobile): object
    {
        // Ensure Webhook file exists
        if (!$this->webhookFileExists()) {
            return Responses::getResponse("CE302");
        }

        // Set endpoint for receiving missed call notification when mobile number calls.
        // Register call request and webhook to service.
        $this->syncAccount->setDynamicWebhook($this->getDynamicWebhookUrl(), $mobile, 'CALL', self::$callWaitTime);

        // Generate Uuid
        $uuid = Uuid::uuid4();

        // Persist the UUID with the local database.
        $this->saveCallVerificationRequest($mobile, $uuid);

        // Fetch the provider's mobile number from service. [Internet connection is required]
        $providerMobile = $this->syncAccount->getSimMSISDN();

        return $this->jsonify([
            'code'    => 200,
            'msisdn'  => $providerMobile->msisdn,
            'message' => sprintf("%s is awaiting call from %s", $providerMobile->msisdn, $mobile ),
            'reference' => (string) $uuid
        ]);
    }



    /**
     * Update Call Status
     * This method is used to save inbound call notification to database where call
     * has originated from a mobile number being verified.
     *
     * @param string $callerMobile     The client mobile number originating the call
     * @param string $receivingMobile  Hosted SIM receiving call
     * @return object
     */
    public function hook(string $callerMobile, string $receivingMobile): object
    {
        // If caller mobile is not supplied, return error

        // If receiving Mobile is not supplied, return error
        // Get mobile number information. Using mobile number for query
        $mobileInfo = $this->getCallVerificationDataByMobile($callerMobile);

        // If record exists, flag status to callReceived
        if (empty($mobileInfo)) {
            # Do Nothing
        } else {
            // Confirm that call originated from this clientNumber
            $this->setCallVerificationStatusReceived($callerMobile);

            return $this->jsonify([
                'code' => 200,
                'message' => 'Success'
            ]);
        }
    }



    /**
     * Complete Call Verification
     * This method is called to confirm the status of call verification. The method
     * returns true when it is able to find data on database that has been updated
     * by hook to indicate that call was received.
     *
     * @param string $trackingId
     * @return object
     */
    public function complete(string $trackingId): object
    {
        // Load info for the mobile number using the tracking Id (uuid)
        $mobileInfo = $this->getCallVerificationDataById((string) $trackingId);

        // Check the status of the mobile number.
        if (empty($mobileInfo)) {
            // Do Nothing!
        } else {
            if ($mobileInfo->call_received) {
                // If status is CALL_RECEIVED, update status to verified and return success
                $this->flagMobileNumberVerified((string) $trackingId);

                $response = [
                    'code'      => 200,
                    'message'   => "Your mobile number has been successfully verified"
                ];
            } else {
                // If status is not CALL_RECEIVED, return WAITING
                $response = [
                    'code'      => "CE303",
                    'message'   => sprintf("Awaiting call from %s", $mobileInfo->mobile)
                ];
            }
            return $this->jsonify($response);
        }
    }


    

    /**
     * Get Dynamic Webhook
     *
     * @return string
     */
    private function getDynamicWebhookUrl(): string
    {
        // Check that webhook file exists. If file does not exist, create one
        

        return sprintf("https://%s/%s", gethostname(), self::$webhookFile);
    }


    /**
     * Check WebhookFile Exists
     * This method will check for webhook file in project root directory.
     * If the webhook file is not found there, it copies the file from vendor root directory
     *
     * @return boolean
     */
    private function webhookFileExists(): bool
    {
        $path_to_webhook = __DIR__."/../".self::$webhookFile;
        $path_to_live_webhook = strstr(__DIR__, 'vendor') ? __DIR__."/../../../".self::$webhookFile : $path_to_webhook;

        if (!file_exists($path_to_live_webhook)) {
            return copy($path_to_webhook, $path_to_live_webhook);
        } else {
            return true;
        }
    }
}