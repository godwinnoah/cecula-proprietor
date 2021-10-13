<?php

namespace Cecula\Proprietor;

use PDO;
use PDOException;
use CeculaSyncApiClient\SyncApiClient;
use DateTime;
use PhpParser\Node\Stmt\TryCatch;
use Ramsey\Uuid\Type\Time;

class Proprietor extends SyncApiClient
{
    protected $pdo;

    // Name of table where sms otp requests will be persisted
    protected $smsOtpTable;

    // Name of table where call verification request will be persisted
    protected $callVerificationTable;

    protected $totalUnprocessedRecords = 0;

    // Holds sync cloud configuration settings
    protected $settings;

    public function __construct()
    {
        parent::__construct();

        $this->settings = json_decode($this->getCredentials());

        $this->smsOtpTable = isset($this->settings->database->smsOtpTableName) ? $this->settings->database->smsOtpTableName : "otp_requests";

        $this->callVerificationTable = isset($this->settings->database->callVerificationTableName) ? $this->settings->database->callVerificationTableName : "call_waitlists";

        $this->pdo = new PDO(sprintf("sqlite:%s/verifications.db", __DIR__));

        $this->totalUnprocessedRecords = $this->countRecords();

        // Create webhook file if it does not exist
        if (!file_exists("synchook.php")) {
            
        }
    }



    /**
     * Create SMS OTP Table
     * This method creates the sms OTP table
     *
     * @return void
     */
    private function createSmsOtpTable(): void
    {

        $sms_otp_table = "
        CREATE TABLE ".$this->smsOtpTable." (
            uuid CHAR(16) NOT NULL,
            mobile VARCHAR(16) NOT NULL,
            otp VARCHAR(255) NOT NULL,
            syncref INT NOT NULL,
            completed INT NOT NULL DEFAULT 0,
            created DATETIME NOT NULL,
            modified DATETIME NOT NULL
        )";

        printf("Creating table %s\n", $this->smsOtpTable);
        $this->pdo->query($sms_otp_table);
    }


    /**
     * Create Call Verification Table
     *
     * @return void
     */
    private function createCallVerificationTable(): void
    {
        $call_verification_table = "
        CREATE TABLE ".$this->callVerificationTable." (
            uuid CHAR(16) NOT NULL,
            mobile VARCHAR(16) NOT NULL,
            call_received INT(1) NOT NULL DEFAULT 0,
            completed INT NOT NULL DEFAULT 0,
            created DATETIME NOT NULL,
            modified DATETIME NOT NULL
        )";

        printf("Creating table %s\n", $this->callVerificationTable);
        $this->pdo->query($call_verification_table);
    }


    /**
     * Save Call Verification Request
     *
     * @param string $mobile
     * @param string $uuid
     * @return void
     */
    protected function saveCallVerificationRequest(string $mobile, string $uuid): bool
    {
        $stmt = $this->pdo->prepare(sprintf("INSERT INTO %s (uuid, mobile, created, modified) VALUES (?, ?, ?, ?)", $this->callVerificationTable));
        return $stmt->execute([$uuid, $mobile, time(), time()]);
    }


    /**
     * Get Call Verification Data by Mobile
     *
     * @param string $mobile
     * @return void
     */
    protected function getCallVerificationDataByMobile(string $mobile): object | array
    {
        $stmt = $this->pdo->prepare(sprintf("SELECT uuid, mobile, call_received, completed FROM %s WHERE mobile=?", $this->callVerificationTable ));
        $stmt->execute([$mobile]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    /**
     * Flag Call Verification Status Received
     *
     * @param string $mobile
     * @return mixed
     */
    public function setCallVerificationStatusReceived(string $mobile): mixed
    {
        $stmt = $this->pdo->prepare("UPDATE ".$this->callVerificationTable." SET call_received= :call_received WHERE mobile=:mobile");
        return $stmt->execute([
            ':call_received' => 1,
            ':mobile' => $mobile
        ]);
    }


    /**
     * Get Call Verification Data by Id
     *
     * @param string $trackingId
     * @return void
     */
    protected function getCallVerificationDataById(string $trackingId): object | array
    {
        $stmt = $this->pdo->prepare(sprintf("SELECT mobile, call_received, completed FROM %s WHERE uuid=?", $this->callVerificationTable));
        $stmt->execute([$trackingId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    /**
     * Flag Mobile Number Verified
     *
     * @param string $trackingId
     * @return boolean
     */
    protected function flagMobileNumberVerified(string $trackingId): bool
    {
        $stmt = $this->pdo->prepare(sprintf("UPDATE %s SET completed=1 WHERE uuid=?", $this->callVerificationTable));
        return $stmt->execute([$trackingId]);
    }



    /**
     * Counts the number of records in sms otp table
     *
     * @return integer
     */
    protected function countRecords(): int
    {
        try {
            $stmt = $this->pdo->query(sprintf("SELECT COUNT(*) totalRecords FROM %s", $this->smsOtpTable));
            $record = $stmt->fetch(PDO::FETCH_OBJ);
            return $record->totalRecords;
        } catch (\PDOException $e) {
            if (strstr($e->getMessage(), 'no such table')) {
                error_log(sprintf("Creating table %s...\n", $this->smsOtpTable));
                $this->createSmsOtpTable();
                error_log("Creating call verification table...\n");
                $this->createCallVerificationTable();
                return 0;
            } else {
                throw new PDOException($e->getMessage(), $e->getCode());
            }
        }
    }



    /**
     * Persist SMS OTP
     * This method is used to persist private key,
     *
     * @param string $mobile
     * @param string $privateKey
     * @param int $syncReference
     * @return void
     */
    protected function saveOTP(string $mobile, string $otp, string $privateKey, int $syncReference): void
    {
        $stm = $this->pdo->prepare(sprintf("INSERT INTO %s (uuid, mobile, otp, syncref, created, modified) VALUE (?, ?, ?, ?, ?, ?)", $this->smsOtpTable));
        $stm->execute([$privateKey, $mobile, $otp, $syncReference, time(), time()]);
    }



    /**
     * Get OTP Request Data
     * This method fetches the DATA for a submitted OTP Code
     *
     * @param string $uuid
     * @return void
     */
    protected function getOtpRequestData(string $uuid): object | array
    {
        $stmt = $this->pdo->prepare(sprintf("SELECT mobile, otp, syncref, completed, created FROM %s WHERE uuid=?", $this->smsOtpTable));
        $stmt->execute([$uuid]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    /**
     * Flag SMS OTP Verification Completed
     *
     * @param string $uuid
     * @return void
     */
    protected function flagOtpVerificationCompleted(string $uuid): bool
    {
        $stmt = $this->pdo->prepare(sprintf("UPDATE %s SET completed=1 WHERE uuid=?", $this->smsOtpTable));
        return $stmt->execute([$uuid]);
    }



    /**
     * Validate Tracking ID
     * This method is used to validate tracking ID
     *
     * @param string $trackingId
     * @return boolean
     */
    protected function validateTrackingId(string $trackingId): bool
    {
        $pattern = "/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/";
        preg_match($pattern, $trackingId, $matches);
        return count($matches) > 0;
    }

}