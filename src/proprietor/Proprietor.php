<?php

namespace Cecula\Proprietor;

use PDO;
use PDOException;
use CeculaSyncApiClient\SyncApiClient;

class Proprietor extends SyncApiClient
{
    protected $pdo;

    // Name of table where sms otp requests will be persisted
    protected $smsOtpTable;

    protected $totalUnprocessedRecords = 0;

    // Holds sync cloud configuration settings
    protected $settings;

    public function __construct()
    {
        $this->settings = $this->credentials;

        $this->smsOtpTable = isset($this->settings->database->smsOtpTableName) ? $this->settings->database->smsOtpTableName : "otp_requests";

        $this->pdo = new PDO(sprintf("sqlite:%s/verifications.db", __DIR__));

        $this->totalUnprocessedRecords = $this->countRecords();
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
            created DATETIME NOT NULL DEFAULT UNIX_TIMESTAMP,
            modified DATETIME NOT NULL DEFAULT UNIX_TIMESTAMP
        )";

        $this->pdo->query($sms_otp_table);
    }



    /**
     * Counts the number of records in sms otp table
     *
     * @return integer
     */
    public function countRecords(): int
    {
        try {
            $stmt = $this->pdo->query(sprintf("SELECT COUNT(*) totalRecords FROM %s", $this->smsOtpTable));
            $record = $stmt->fetch(PDO::FETCH_OBJ);
            return $record->totalRecords;
        } catch (\PDOException $e) {
            if (strstr($e->getMessage(), 'no such table')) {
                printf("Creating table %s...\n", $this->smsOtpTable);
                $this->createSmsOtpTable();
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
    protected function saveOTP(string $mobile, string $otp, string $privateKey, int $syncReference)
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
}