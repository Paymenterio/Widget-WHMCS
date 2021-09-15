<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\User\Client;

function verifySHA1Signature($orderID, $orderKey, $hash): bool
{
    return (SHA1 ($orderID . '|' . $orderKey) === $hash);
}

$gatewaymodule = "paymenterio";
$GATEWAY = getGatewayVariables($gatewaymodule);

if (!$GATEWAY["type"]) {
    Header('HTTP/1.1 400 Bad Request');
    die("The module is not enabled.");
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    Header('HTTP/1.1 400 Bad Request');
    exit("BadRequest - The request could not be resolved, try again.");
}

$hash = $_GET['hash'];
$body = json_decode(file_get_contents("php://input"), true);
$orderID = 0;
$statusID = 0;
$invoiceID = 0;

if (isset($body['order']) && !empty($body['order'])) {
    $orderID = $body['order'];
}

if (isset($body['status']) && !empty($body['status'])) {
    $statusID = $body['status'];
}

Header('HTTP/1.1 404 Not Found');
if (checkCbInvoiceID($orderID, $GATEWAY["name"])) {
    Header('HTTP/1.1 200 OK');
    $invoiceID = checkCbInvoiceID($orderID, $GATEWAY["name"]);

    $invoice = new WHMCS\Invoice();
    $invoice->setID($invoiceID);
    $data = $invoice->getData();
    $userID = $data['userid'];
    $user = Client::findOrFail($userID);

    $isSignatureValid = verifySHA1Signature($orderID, $user->email, $hash);
    if (!$isSignatureValid) {
        Header('HTTP/1.1 400 Bad Request');
        exit("WrongSignatureException - Signature mismatch.");
    }

    if ($statusID == 5) {
        addInvoicePayment($invoiceID, $body['transaction_hash'], $data['total'], 0, $gatewaymodule);
        logTransaction($GATEWAY["name"], $body, "Successful");
    } else {
        logTransaction($GATEWAY["name"], $body, "Unsuccessful");
    }
    echo 'OK';
}
exit();