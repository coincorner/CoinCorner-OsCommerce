<?php
require('includes/application_top.php');
global $db;
global $order;

$raw_post = file_get_contents('php://input');
$decoded  = json_decode($raw_post, true);
$order_id = $decoded['OrderId'];
$API_Key_Request = $decoded['APIKey'];
$UserId = MODULE_PAYMENT_COINCORNER_APP_ID;
$Api_Key = MODULE_PAYMENT_COINCORNER_API_KEY;
$Api_Secret = MODULE_PAYMENT_COINCORNER_API_SECRET;


$url       = 'https://checkout.coincorner.com/api/CheckOrder'; //URL for Check Order request
$nonce     = (int)(microtime(true) * 1e6); //Authenticaton for request, creates nonce
$sig = hash_hmac('sha256', $nonce . $UserId . $Api_Key, $Api_Secret); //Authentication for request, creates signature
$headers = array();
$params = array();

//Adds Authenticaion vairables to params
$params['APIKey'] = $Api_Key;
$params['Nonce'] = $nonce;
$params['Signature'] = $sig;
$params['OrderId'] = $order_id;
   
if($API_Key_Request == $Api_Key) { 

  $curl = curl_init();

  $curl_options = array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL            => $url
  );
  
  
      $headers[] = 'Content-Type: application/x-www-form-urlencoded';
      array_merge($curl_options, array(CURLOPT_POST => 1));
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
  
  
  curl_setopt_array($curl, $curl_options);
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
  
  $response  = json_decode(curl_exec($curl), true);
  $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  
  if($http_status != 200) {
    http_response_code(400);
    return false;
  }
  else {

    //Set order status variable to orderstatus based on request
    switch ($response["OrderStatus"]) {
      case 0:
      $cc_order_status = MODULE_PAYMENT_COINCORNER_PENDING_PAYMENT_STATUS_ID;
      break;
      case 1:
      $cc_order_status = MODULE_PAYMENT_COINCORNER_PENDING_CONFIRMATION_STATUS_ID;
      break;
      case 2:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_PAID_STATUS_ID;
        break;
      case -1:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_CANCELED_STATUS_ID;
        break;
      case -2:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_EXPIRED_STATUS_ID;
        break;
      case -3:
      $cc_order_status = MODULE_PAYMENT_COINCORNER_EXPIRED_STATUS_ID;
        break;
      case -4:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_PENDING_REFUND_STATUS_ID;
        break;
        case -5:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_REFUNDED_STATUS_ID;
        break;
        case -99:
        $cc_order_status = MODULE_PAYMENT_COINCORNER_PENDING_INVOICE_ORDER_STATUS_ID;
        break;
      default:
        $cc_order_status = NULL;
      }

      //UPDATE order to status returned from callback request
        tep_db_query("update ". TABLE_ORDERS . " set orders_status = " . $cc_order_status . " where orders_id = " . intval($order_id));

  }

  
}
?>