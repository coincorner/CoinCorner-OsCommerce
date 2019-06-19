<?php

class coincorner
{

  public $code;
  public $title;
  public $description;
  public $enabled;

  function coincorner()
  {
    $this->code             = 'coincorner';
    $this->title            = "Bitcoin via CoinCorner";
    $this->description      = 'Checkout with CoinCorner';
    $this->app_id           = MODULE_PAYMENT_COINCORNER_APP_ID;
    $this->api_key          = MODULE_PAYMENT_COINCORNER_API_KEY;
    $this->api_secret       = MODULE_PAYMENT_COINCORNER_API_SECRET;
    $this->receive_currency = MODULE_PAYMENT_COINCORNER_RECEIVE_CURRENCY;
    $this->sort_order       = MODULE_PAYMENT_COINCORNER_SORT_ORDER;
    $this->enabled          = ((MODULE_PAYMENT_COINCORNER_STATUS == 'True') ? true : false);
  }

  function javascript_validation()
  {
    return false;
  }

  function selection()
  {
    return array('id' => $this->code, 'module' => $this->title);
  }

  function pre_confirmation_check()
  {
    return false;
  }

  function confirmation()
  {
    return false;
  }

  function process_button()
  {
    return false;
  }

  function before_process()
  {
    return false;
  }

  function after_process()
  {
    global $insert_id, $order;

    $info = $order->info;

    $configuration = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key='STORE_NAME' limit 1");
    $configuration = tep_db_fetch_array($configuration);
    $products = tep_db_query("select oc.products_id, oc.products_quantity, pd.products_name from " . TABLE_ORDERS_PRODUCTS . " as oc left join " . TABLE_PRODUCTS_DESCRIPTION . " as pd on pd.products_id=oc.products_id  where orders_id=" . intval($insert_id));

    $description = array();
    foreach ($products as $product) {
      $description[] = $product['products_quantity'] . ' × ' . $product['products_name'];
    }

    //Callback link
    $callback = tep_href_link('coincorner_callback.php', $parameters='', $connection='NONSSL', $add_session_id=true, $search_engine_safe=true, $static=true );

    //Creates params array which will be passed into create order request
    $params = array(
      'OrderId'         => $insert_id,
      'InvoiceAmount'            => $order->info['total'],
      'SettleCurrency'         => MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY,
      'InvoiceCurrency'         => MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY,
      'SuccessRedirectURL' => tep_href_link(FILENAME_CHECKOUT_SUCCESS),
      'FailRedirectURL' => tep_href_link(FILENAME_CHECKOUT_PAYMENT),
      'NotificationURL' => $callback,
      'ItemDescription' => implode( ", ", $description)
   
    );
    
    $UserId = MODULE_PAYMENT_COINCORNER_APP_ID;
    $Api_Key = MODULE_PAYMENT_COINCORNER_API_KEY;
    $Api_Secret = MODULE_PAYMENT_COINCORNER_API_SECRET;
    
    
     $url       = 'https://checkout.coincorner.com/api/CreateOrder'; //URL for create order request
     $nonce     = (int)(microtime(true) * 1e6); //Authenticaton for request, creates nonce
     $sig = hash_hmac('sha256', $nonce . $UserId . $Api_Key, $Api_Secret); //Authentication for request, creates signature
     $headers   = array();

     //Adds authentication variables to params array for request
     $params['APIKey'] = $Api_Key; 
     $params['Nonce'] = $nonce;
     $params['Signature'] = $sig;
     $curl = curl_init();

 
     $curl_options = array(
         CURLOPT_RETURNTRANSFER => 1,
         CURLOPT_URL  => $url
     );
      
     $headers[] = 'Content-Type: application/x-www-form-urlencoded';
     array_merge($curl_options, array(CURLOPT_POST => 1));
     curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
   
     curl_setopt_array($curl, $curl_options);
     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
     curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

     $response = json_decode(curl_exec($curl), TRUE);
     $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
     $invoice = explode("/Checkout/", $response);
     if (count($invoice) < 2) {
      $message = "CoinCorner returned an error. Error: {$response}";
      tep_db_query("update ". TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_COINCORNER_CANCELED_STATUS_ID . " where orders_id = " . intval($order_id));
      tep_redirect(FILENAME_CHECKOUT_PAYMENT);
  } else {
            // Redirect to payment gateway for payment
            $_SESSION['cart']->reset(true);
            tep_redirect($response);
          }



    return false;
  }

  function get_error()
  {
    return false;
  }

  function check()
  {
    if (!isset($this->_check)) {
      $check_query  = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_COINCORNER_STATUS'");
      $this->_check = tep_db_num_rows($check_query);
    }

    return $this->_check;
  }

  //All settings for plug in are configured here
  function install()
  {

    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable CoinCorner Module', 'MODULE_PAYMENT_COINCORNER_STATUS', 'False', 'Enable the CoinCorner bitcoin plugin?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CoinCorner APP ID', 'MODULE_PAYMENT_COINCORNER_APP_ID', '0', 'Your CoinCorner APP ID', '6', '0', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CoinCorner API Key', 'MODULE_PAYMENT_COINCORNER_API_KEY', '0', 'Your CoinCorner API Key', '6', '0', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('CoinCorner APP Secret', 'MODULE_PAYMENT_COINCORNER_API_SECRET', '0', 'Your CoinCorner API Secret', '6', '0', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Invoice Currency', 'MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY', 'GBP', 'The currency you want to invoice your customer in', '6', '0', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Settle Currency', 'MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY', 'GBP', 'The currency you want the order to be settled in (Only GBP Currency supported)', '6', '0', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_COINCORNER_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '8', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Pending Payment Status', 'MODULE_PAYMENT_COINCORNER_PENDING_PAYMENT_STATUS_ID', 0, 'Status for your order when awaiting payment', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Pending Confirmation Status', 'MODULE_PAYMENT_COINCORNER_PENDING_CONFIRMATION_STATUS_ID', 1, 'Status for your order when your order is pending confirmation', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Cancelled Status', 'MODULE_PAYMENT_COINCORNER_CANCELED_STATUS_ID', -1, 'Status for your order when your order is cancelled', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Paid Order Status', 'MODULE_PAYMENT_COINCORNER_PAID_STATUS_ID', '2', 'Status in your store when CoinCorner order status is paid', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Expired Order Status', 'MODULE_PAYMENT_COINCORNER_EXPIRED_STATUS_ID', '-2', 'Status in your store when CoinCorner order status is expired', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Pending Refund Status', 'MODULE_PAYMENT_COINCORNER_PENDING_REFUND_STATUS_ID', '-4', 'Status in your store when CoinCorner order status is pending a refund', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refunded Status', 'MODULE_PAYMENT_COINCORNER_REFUNDED_STATUS_ID', '-5', 'Status in your store when CoinCorner order status is refunded', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
  }

  function remove ()
  {
    tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_COINCORNER\_%'");
  }

  //All Configuration keys must be included in keys array
  function keys()
  {
    return array(
      'MODULE_PAYMENT_COINCORNER_STATUS',
      'MODULE_PAYMENT_COINCORNER_APP_ID',
      'MODULE_PAYMENT_COINCORNER_API_KEY',
      'MODULE_PAYMENT_COINCORNER_API_SECRET',
      'MODULE_PAYMENT_COINCORNER_INVOICE_CURRENCY',
      'MODULE_PAYMENT_COINCORNER_SETTLE_CURRENCY',
      'MODULE_PAYMENT_COINCORNER_SORT_ORDER',
      'MODULE_PAYMENT_COINCORNER_PENDING_PAYMENT_STATUS_ID',
      'MODULE_PAYMENT_COINCORNER_PENDING_CONFIRMATION_STATUS_ID',
      'MODULE_PAYMENT_COINCORNER_CANCELED_STATUS_ID',
      'MODULE_PAYMENT_COINCORNER_PAID_STATUS_ID',
      'MODULE_PAYMENT_COINCORNER_EXPIRED_STATUS_ID',
      'MODULE_PAYMENT_COINCORNER_REFUNDED_STATUS_ID',
    );
  }


  
}
function coincorner_censorize($value) {
  return "(hidden for security reasons)";
}
