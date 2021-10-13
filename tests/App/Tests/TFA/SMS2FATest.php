<?php

namespace App\Tests\TFA;

use PHPUnit\Framework\TestCase;
use Cecula\Proprietor\Sms2fa;
use CeculaSyncApiClient\SyncApiClient;

class SMS2FATest extends TestCase
{
    private Sms2fa $sms2fa;

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanValidateAlphabeticalOTP(): void
    {
        # code...
        
    }


    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanValidateAlphanumericOTP(): void
    {
        # code...
    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanValidateNumericOTP():void
    {

    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanDetectInvalidTrackingId(): void
    {
        # code...
    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanDetectvalidTrackingId(): void
    {
        # code...
    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanDetectWhenBalanceIsNotSufficient(): void
    {
        # code...
    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanGenerateOTPToSpecification(): void
    {
        # code...
    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faCanGenerateMessageFromTemplateAndOTP(): void
    {

    }

    /**
     * @test
     *
     * @return void
     */
    public function sms2faReturnsEpectedResponseMessageAfterSendingOTP(): void
    {
        # code...
    }
}