<?php
// Hooks up to the Sync Cloud Server
$input = file_get_contents("php://input");

require_once __DIR__."/vendor/autoload.php";

use Cecula\Proprietor\Call2fa;

header("Content-Type: application/json");
// Receive Input
try{
    $jsonObject = json_decode($input);
    $call2fa = new Call2fa();
    $hookStatus = $call2fa->hook($jsonObject->originator, $jsonObject->receiver);
    // Extend code
    echo json_encode(
        [
            'status' => 'done',
            'responseCode' => $hookStatus->code
        ]
    );
} catch (Exception $e){
    error_log(sprintf("Error %s: %s", $e->getCode(), $e->getMessage()));
}

