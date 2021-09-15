<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';


function paymenterio_config() {
    $version = '1.0.1';
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Paymenterio"),
     "shopid" => array("FriendlyName" => "Identyfikator lub Hash sklepu", "Type" => "text", "Size" => "20"),
     "apikey" => array("FriendlyName" => "Klucz API", "Type" => "password", "Size" => "60"),
     "version" => array( "Type" => "html", "Size" => "25", "Description" => checkVersion($version))
    );
	return $configarray;
}

function paymenterio_link($params) {

    $shopID = $params['shopid'];
    $apiKey = $params['apikey'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $orderID = $params['invoiceid'];
    $email = $params['clientdetails']['email'];
    $gatewayParams = getGatewayVariables('paymenterio');
    $systemUrl = $gatewayParams['systemurl'];

    $urls = getReturnUrlsForOrder($orderID, $email, $params, $systemUrl);
    $shop = new Shop($shopID, $apiKey);

    try {
        $paymentData = $shop->createPayment(
            1,
            $orderID,
            getAmountForOrder($amount, $currency),
            getNameForOrder($orderID),
            $urls['successUrl'],
            $urls['failUrl'],
            $urls['notifyUrl']
        );
    } catch (Exception $e) {
        exit ($e);
    }
    
    $code = '<form method="GET" action="'.$paymentData->payment_link.'">';

    $code .= '<input type="submit" value="Zapłać z Paymenterio" />';
    $code .= '</form>';
    
    return $code;
}

 function checkVersion($version)
{
    $currentVersion = '1.0.1';
    if ($currentVersion == $version)
    {
        return '<p>Posiadasz najnowszą wersję modułu płatności Paymenterio dla WHMCS.</p>';
    }

    return '<p>Aktualnie posiadasz wersję ' . $version . ' jednak jest już dostępna nowsza wersja ' . $currentVersion . '.</p><p> Możesz ją pobrać korzystając z najnowszych repozytoriów Paymenterio. Przejdź do <a href="https://paymenterio.com">www.paymenterio.com</a>, aby dowiedzieć się więcej.<p>';
}

function getAmountForOrder($total, $currency)
{
    return array(
        "value"=>$total,
        "currencyCode"=>$currency
    );
}

function getNameForOrder($orderID) {
    return "Płatność za zamówienie {$orderID}";
}

function getReturnUrlsForOrder($orderID, $orderEmail, $params, $systemUrl)
{
    $successUrl = $params['returnurl']."&paymentsuccess=true";
    $failUrl = $params['returnurl']."&paymentfailed=true";
    return array(
        'successUrl' =>  $successUrl,
        'failUrl' => $failUrl,
        'notifyUrl' => buildNotifyUrl($orderID, $orderEmail, $systemUrl)
    );
}

function buildNotifyUrl($orderID, $orderEmail, $systemUrl) {
    $actionURL = $systemUrl . 'modules/gateways/callback/paymenterio.php';
    $actionURL .= '?hash=' . SignatureGenerator::generateSHA1Signature($orderID, $orderEmail);
    return $actionURL;
}

class SignatureGenerator {

    /**
     * Create SHA1 Hash from input parametrs
     *
     * @param mixed $data
     * @param string $key
     *
     * @return string
     *
     */

    public static function generateSHA1Signature($orderID, $orderKey): string
    {
        return SHA1 ($orderID . '|' . $orderKey);
    }

    public static function verifySHA1Signature($orderID, $orderKey, $hash): bool
    {
        return (SHA1 ($orderID . '|' . $orderKey) === $hash);
    }

    public static function createStringFromArray($data){

        if(!is_array($data)){
            return $data;
        }

        $pureString = "";

        foreach($data as $element){
            $pureString .= self::createStringFromArray($element);
        }

        return $pureString;
    }

}

interface Data {
    public function toArray();
}

class CurlConnection
{
    const CHARSET = 'utf-8', ENCODING_IN = 'application/json', ENCODING_OUT = 'application/json', USER_AGENT = 'PAYMENTERIO SDK REQUEST';
    /**
     *
     * @var string $endpoint
     */
    private $endpoint;

    /**
     *
     * @var string $apiKey
     */
    private $apiKey;

    /**
     *
     * @staticvar cURL $ch
     */
    private static $ch;

    /**
     *
     * @param string $endpoint
     */
    public function __construct($endpoint, $apiKey)
    {
        $this->setEndpoint($endpoint);
        $this->apiKey = $apiKey;
    }

    /**
     *
     * @param string $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }
    public function prepareRequest()
    {
        $headers = array(
            "Accept: " . self::ENCODING_IN,
            "Content-type: " . self::ENCODING_OUT . ';charset=' . self::CHARSET,
            "apiKey: " . $this->apiKey
        );

        curl_setopt(self::$ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt(self::$ch, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     *
     * @param string $url
     * @param array $args
     * @return mixed
     */
    public function get($url, $args = array())
    {
        return $this->request($url, "GET", $args);
    }

    /**
     *
     * @param string $url
     * @param array $args
     * @return mixed
     */
    public function post($url, $args = array())
    {
        return $this->request($url, "POST", $args);
    }

    /**
     *
     * @param string $url
     * @param array $args
     * @return mixed
     */
    public function put($url, $args = array())
    {
        return $this->request($url, "PUT", $args);
    }

    /**
     *
     * @param string $url
     * @param string $type
     * @param array $args
     * @throws Exception
     * @return mixed
     */
    private function request($url, $type, $args)
    {
        self::$ch = curl_init();
        $this->prepareRequest();
        switch ($type) {

            case "GET":
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, "GET");
                $url .= '?' . http_build_query($args);
                break;

            case "POST":
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt(self::$ch, CURLOPT_POSTFIELDS, json_encode($args));
                break;

            case "PUT":
                curl_setopt(self::$ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt(self::$ch, CURLOPT_POSTFIELDS, json_encode($args));
                break;
        }
        curl_setopt(self::$ch, CURLOPT_URL, $this->endpoint . $url);

        $response = curl_exec(self::$ch);

        $httpCode = curl_getinfo(self::$ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200 && $httpCode !== 204) {
            throw new Exception(strip_tags($response), $httpCode);
        }
        curl_close(self::$ch);

        return json_decode($response);
    }
}

class Transaction implements Data
{
    /**
     *
     * @var int $system
     * @var string $shop
     * @var int $order
     * @var float $amount_value
     * @var string $amount_currencyCode
     * @var string $name
     * @var string $success_url
     * @var string $fail_url
     * @var string $notify_url
     */

    public $system;
    public $shop;
    public $order;
    public $amount;
    public $currency;
    public $name;
    public $success_url;
    public $fail_url;
    public $notify_url;

    /**
     *
     * @param int $system
     * @param string $shop
     * @param string $order
     * @param float $amount_value
     * @param string $amount_currencyCode
     * @param string $name
     * @throws Exception
     */
    public function __construct(int $system, string $shop, string $order, $amount, string $name, string $successUrl, string $failUrl, string $notifyUrl)
    {
        if (empty($system) || empty($amount) || empty($shop) || empty($order) || empty($name) || empty($successUrl) || empty($failUrl) || empty($notifyUrl)) {
            throw new Exception("Required params not set");
        }
        $amount = $amount->toArray();
        $this->system = $system;
        $this->shop = $shop;
        $this->order = $order;
        $this->amount = $amount['amount.value'];
        $this->currency = $amount['amount.currencyCode'];
        $this->name = $name;

        $this->success_url = $successUrl;
        $this->fail_url = $failUrl;
        $this->notify_url = $notifyUrl;
    }

    /**
     *
     * @see PaymenterioData::toArray()
     */
    public function toArray()
    {
        $array = array();
        foreach ($this as $key => $value) {
            if (!is_object($value)) {
                $array [str_replace("_", ".", $key)] = $value;
            } else {
                $array = array_merge($array, $value->toArray());
            }
        }

        return $array;
    }
}

class Amount implements Data
{
    /**
     *
     * @var string $value
     * @var string $currencyCode
     */
    private $value, $currencyCode;

    /**
     *
     * @param float $value
     * @param string $currencyCode
     */
    function __construct($value, $currencyCode)
    {

        if (!is_numeric($value)) {
            throw new Exception("Amount value not numeric");
        }

        if (strlen($currencyCode) !== 3) {
            throw new Exception("Currency code not valid");
        }

        $this->value = number_format($value, 2, ".", "");
        $this->currencyCode = $currencyCode;
    }
    public static function fromArray($array)
    {
        return new Amount($array['value'], $array['currencyCode']);
    }

    /**
     *
     * @see PaymenterioData::toArray()
     */
    public function toArray()
    {
        $array = array();
        foreach ($this as $key => $value) {
            $array["amount." . $key] = $value;
        }
        return $array;
    }
}

class Shop
{
    const productionEndpoint = 'https://api.paymenterio.pl/v1/';
    /**
     *
     * @var string $shopID
     * @var string $apiKey
     * @var CurlConnection $curlConnection
     * @var Transaction $transaction
     */
    private $shopID;
    private $apiKey;
    private $curlConnection;

    /**
     *
     * @param string $pointId
     * @param string $pointKey
     * @param boolean $production
     * @throws Exception
     */
    public function __construct($shopID, $apiKey)
    {
        if (empty($shopID) || empty($apiKey)) {
            throw new Exception("Configuration required params not set");
        }

        if (strlen($apiKey) < 30 && strlen($apiKey) > 50) {
            throw new Exception("Payment API Key invalid value");
        }

        $this->shopID = $shopID;
        $this->apiKey = $apiKey;
        $this->curlConnection = new CurlConnection(self::productionEndpoint, $apiKey);
    }

    /**
     *
     * @param int $system
     * @param string $orderID
     * @param Amount | array $amount
     * @param string $name
     * @throws Exception
     * @return mixed
     */
    public function createPayment(int $system, string $orderID, $amount, string $name, string $successUrl, string $failUrl, string $notifyUrl, $fake = false)
    {
        try {

            if (! ($amount instanceof Amount)) {
                $amount = Amount::fromArray($amount);
            }

            $transactionData = new Transaction($system, $this->shopID, $orderID, $amount, $name, $successUrl, $failUrl, $notifyUrl);

            if ($fake) {
                $paymentData = array(
                    'status' => 5,
                    'order' => $orderID
                );
                return json_decode(json_encode($paymentData));
            }
            return $this->curlConnection->post("pay", $transactionData);
        } catch (Exception $exception) {
            throw new Exception("Create Payment Exception " . $exception->getMessage());
        }
    }
}