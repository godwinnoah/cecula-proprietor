<?php
namespace Cecula\Proprietor;

class Responses
{
    private static $responses = [
        "CE201" => [
            'code'     => 'CE201',
            'message'  => 'Your mobile number has already been verified'
        ],
        "CE1804" => [
            'code'      => "CE1804",
            'message'   => 'Your Cecula Balance is low. Kindly top up your credit.'
        ],
        "CE202" => [
            'code'     => 'CE202',
            'message'  => 'Your OTP has expired'
        ],
        "CE203" => [
            'code'     => 'CE203',
            'message'  => 'Invalid OTP'
        ],
        "CE204" => [
            'code'     => 'CE204',
            'message'  => 'You have submitted an invalid tracking ID'
        ],
        "CE205" => [
            'code'     => 'CE205',
            'message'  => 'Tracking ID does not reference any authentication request'
        ],
        "CE301" => [
            'code'     => 'CE301',
            'message'  => 'Unknown Caller'
        ],
        "CE302" => [
            'code'     => 'CE302',
            'message'  => 'Webhook File not found.'
        ]
    ];
    
    public static function getResponse(string $code)
    {
        return self::$responses[$code];
    }
}