<?php
namespace Cecula\Proprietor;

class Responses
{
    private static $responses = [
        "CE201" => [
            'code'     => 'CE201',
            'message'  => 'Your mobile number has already been verified'
        ],
        [
            'code'      => "CE1804",
            'message'   => 'Your Cecula Balance is low. Kindly top up your credit.'
        ],
        [
            'code'     => 'CE202',
            'message'  => 'Your OTP has expired'
        ],
        [
            'code'     => 'CE203',
            'message'  => 'Invalid OTP'
        ],
        [
            'code'     => 'CE204',
            'message'  => 'You have submitted an invalid tracking ID'
        ],
        [
            'code'     => 'CE205',
            'message'  => 'Tracking ID does not reference any authentication request'
        ]
    ];
    
    public static function getResponse(string $code)
    {
        return self::$responses[$code];
    }
}