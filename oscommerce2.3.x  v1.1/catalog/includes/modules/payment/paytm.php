<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/
	
	require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../encdec_paytm.php');
	
  class paytm {
    var $code, $title, $description, $enabled;

// class constructor
    function paytm() {
      $this->code = 'paytm'; 
      $this->title = MODULE_PAYMENT_PAYTM_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_PAYTM_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_PAYTM_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_PAYTM_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID;
      }

      if (is_object($order)){
				$this->update_status();
			}
			
			$this->form_action_url = 'https://secure.paytm.in/oltp-web/processTransaction';
			
    }

// class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYTM_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYTM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title);
    }

    function pre_confirmation_check() {
			global $cart; 
			$mod = MODULE_PAYMENT_PAYTM_MODE;
		  if($mod == "Test"){
		  	$this->form_action_url = "https://pguat.paytm.com/oltp-web/processTransaction";
		  }else{
		  	$this->form_action_url ="https://secure.paytm.in/oltp-web/processTransaction";
		  }

			if ( empty($cart->cartID))
      {
        $cart->cartID = $cart->generate_cart_id();
      }

      if (!tep_session_is_registered('cartID'))
      {
          tep_session_register('cartID');
      }
      
    }

    // function confirmation() {
    //   return false;
    // }
     function confirmation() {

      global $cartID, $cart_DirecPay_ID, $customer_id, $languages_id, $order, $order_total_modules;



      if (tep_session_is_registered('cartID')) {

        $insert_order = false;



        if (tep_session_is_registered('cart_DirecPay_ID')) {

          $order_id = substr($cart_DirecPay_ID, strpos($cart_DirecPay_ID, '-')+1);



          $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");

          $curr = tep_db_fetch_array($curr_check);



          if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_DirecPay_ID, 0, strlen($cartID))) ) {

            $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');



            if (tep_db_num_rows($check_query) < 1) {

              tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');

              tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');

              tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');

              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');

              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');

              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

            }



            $insert_order = true;

          }

        } else {

          $insert_order = true;

        }



        if ($insert_order == true) {

          $order_totals = array();

          if (is_array($order_total_modules->modules)) {

            reset($order_total_modules->modules);

            while (list(, $value) = each($order_total_modules->modules)) {

              $class = substr($value, 0, strrpos($value, '.'));

              if ($GLOBALS[$class]->enabled) {

                for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {

                  if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {

                    $order_totals[] = array('code' => $GLOBALS[$class]->code,

                                            'title' => $GLOBALS[$class]->output[$i]['title'],

                                            'text' => $GLOBALS[$class]->output[$i]['text'],

                                            'value' => $GLOBALS[$class]->output[$i]['value'],

                                            'sort_order' => $GLOBALS[$class]->sort_order);

                  }

                }

              }

            }

          }



          $sql_data_array = array('customers_id' => $customer_id,

                                  'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],

                                  'customers_company' => $order->customer['company'],

                                  'customers_street_address' => $order->customer['street_address'],

                                  'customers_suburb' => $order->customer['suburb'],

                                  'customers_city' => $order->customer['city'],

                                  'customers_postcode' => $order->customer['postcode'],

                                  'customers_state' => $order->customer['state'],

                                  'customers_country' => $order->customer['country']['title'],

                                  'customers_telephone' => $order->customer['telephone'],

                                  'customers_email_address' => $order->customer['email_address'],

                                  'customers_address_format_id' => $order->customer['format_id'],

                                  'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],

                                  'delivery_company' => $order->delivery['company'],

                                  'delivery_street_address' => $order->delivery['street_address'],

                                  'delivery_suburb' => $order->delivery['suburb'],

                                  'delivery_city' => $order->delivery['city'],

                                  'delivery_postcode' => $order->delivery['postcode'],

                                  'delivery_state' => $order->delivery['state'],

                                  'delivery_country' => $order->delivery['country']['title'],

                                  'delivery_address_format_id' => $order->delivery['format_id'],

                                  'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],

                                  'billing_company' => $order->billing['company'],

                                  'billing_street_address' => $order->billing['street_address'],

                                  'billing_suburb' => $order->billing['suburb'],

                                  'billing_city' => $order->billing['city'],

                                  'billing_postcode' => $order->billing['postcode'],

                                  'billing_state' => $order->billing['state'],

                                  'billing_country' => $order->billing['country']['title'],

                                  'billing_address_format_id' => $order->billing['format_id'],

                                  'payment_method' => $order->info['payment_method'],

                                  'cc_type' => $order->info['cc_type'],

                                  'cc_owner' => $order->info['cc_owner'],

                                  'cc_number' => $order->info['cc_number'],

                                  'cc_expires' => $order->info['cc_expires'],

                                  'date_purchased' => 'now()',

                                  'orders_status' => $order->info['order_status'],

                                  'currency' => $order->info['currency'],

                                  'currency_value' => $order->info['currency_value']);



          tep_db_perform(TABLE_ORDERS, $sql_data_array);



          $insert_id = tep_db_insert_id();



          for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {

            $sql_data_array = array('orders_id' => $insert_id,

                                    'title' => $order_totals[$i]['title'],

                                    'text' => $order_totals[$i]['text'],

                                    'value' => $order_totals[$i]['value'],

                                    'class' => $order_totals[$i]['code'],

                                    'sort_order' => $order_totals[$i]['sort_order']);



            tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);

          }



          for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {

            $sql_data_array = array('orders_id' => $insert_id,

                                    'products_id' => tep_get_prid($order->products[$i]['id']),

                                    'products_model' => $order->products[$i]['model'],

                                    'products_name' => $order->products[$i]['name'],

                                    'products_price' => $order->products[$i]['price'],

                                    'final_price' => $order->products[$i]['final_price'],

                                    'products_tax' => $order->products[$i]['tax'],

                                    'products_quantity' => $order->products[$i]['qty']);



            tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);



            $order_products_id = tep_db_insert_id();



            $attributes_exist = '0';

            if (isset($order->products[$i]['attributes'])) {

              $attributes_exist = '1';

              for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {

                if (DOWNLOAD_ENABLED == 'true') {

                  $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename

                                       from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa

                                       left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad

                                       on pa.products_attributes_id=pad.products_attributes_id

                                       where pa.products_id = '" . $order->products[$i]['id'] . "'

                                       and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'

                                       and pa.options_id = popt.products_options_id

                                       and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'

                                       and pa.options_values_id = poval.products_options_values_id

                                       and popt.language_id = '" . $languages_id . "'

                                       and poval.language_id = '" . $languages_id . "'";

                  $attributes = tep_db_query($attributes_query);

                } else {

                  $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");

                }

                $attributes_values = tep_db_fetch_array($attributes);



                $sql_data_array = array('orders_id' => $insert_id,

                                        'orders_products_id' => $order_products_id,

                                        'products_options' => $attributes_values['products_options_name'],

                                        'products_options_values' => $attributes_values['products_options_values_name'],

                                        'options_values_price' => $attributes_values['options_values_price'],

                                        'price_prefix' => $attributes_values['price_prefix']);



                tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);



                if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {

                  $sql_data_array = array('orders_id' => $insert_id,

                                          'orders_products_id' => $order_products_id,

                                          'orders_products_filename' => $attributes_values['products_attributes_filename'],

                                          'download_maxdays' => $attributes_values['products_attributes_maxdays'],

                                          'download_count' => $attributes_values['products_attributes_maxcount']);



                  tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);

                }

              }

            }

          }



          $cart_DirecPay_ID = $cartID . '-' . $insert_id;

          tep_session_register('cart_DirecPay_ID');

        }

      }



      return false;

    }

    function process_button() {
    global $order, $customer_id,$cart,$cart_DirecPay_ID;


			$merchant_mid = MODULE_PAYMENT_PAYTM_MERCHANT_ID;
		  $merchant_key =html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
		  $website = MODULE_PAYMENT_PAYTM_WEBSITE;
		  $industry_type_id = MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID;
      $callback_enabled= MODULE_PAYMENT_PAYTM_CALLBACK;
			
			$amount = $order->info['total']; 
			//$orderId = $cart->cartID;	
			      $order_id = substr($cart_DirecPay_ID, strpos($cart_DirecPay_ID, '-')+1);
    $_SESSION['sorderid']=$order_id;
			$post_variables = array(		
				"MID" => $merchant_mid,
				"ORDER_ID" => $order_id,
				"CUST_ID" => ! empty($customer_id)?$customer_id:$order->customer['email_address'],
				"WEBSITE" => $website,
				"INDUSTRY_TYPE_ID" => $industry_type_id,
				"EMAIL" => $order->customer['email_address'],
				"MOBILE_NO" => $order->customer['telephone'],
				"CHANNEL_ID" => "WEB",
				"TXN_AMOUNT" => $amount
			);
			
			if(stripos($callback_enabled,"yes") !== false){
				$post_variables['CALLBACK_URL'] = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
			}
			
			
		
			$checksum = getChecksumFromArray($post_variables,$merchant_key);
			$post_variables['CHECKSUMHASH']=$checksum;
      
			$process_button_string = '';
			
			foreach($post_variables as $key=>$value){
				$process_button_string .= tep_draw_hidden_field($key, $value);
			}
			
			return $process_button_string;
    }

    function before_process() {
			global $cart;
			$contents =$cart->contents;
			$cart->remove_all();
			$cart->contents = $contents;
			
			$merchant_key =html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
			$paramList = $_POST;
			$paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : ""; 
      $isValidChecksum = verifychecksum_e($paramList, $merchant_key, $paytmChecksum); 
			$resp_code = isset($_POST["RESPCODE"]) ? $_POST["RESPCODE"] : ""; 
			
			if($isValidChecksum){
				if( $resp_code != "01"){	
					tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Your payment was not processed. Please try again...!"), 'SSL', true, false));
				}
			}else{				
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Security error...!"), 'SSL', true, false));
			}		
			
    }

    function after_process() {
		// Create an array having all required parameters for status query.
		$requestParamList = array("MID" => MODULE_PAYMENT_PAYTM_MERCHANT_ID , "ORDERID" => $_POST['ORDERID']);
		
		$StatusCheckSum = getChecksumFromArray($requestParamList, $merchant_key);
							
		$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
		
		$mod = MODULE_PAYMENT_PAYTM_MODE;
		
		if($mod == "Test"){
			$check_status_url = 'https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus';
		}else{
			$check_status_url = 'https://secure.paytm.in/oltp/HANDLER_INTERNAL/getTxnStatus';
		}
		
		$responseParamList = callNewAPI($check_status_url, $requestParamList);
		if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$_POST['TXNAMOUNT'])
		{
			global $insert_id;
			$status_comment=array();
			if(isset($_POST)){
				if(isset($_POST['ORDERID'])){
					$status_comment[]="Order Id: " . $_POST['ORDERID'];
				}
				
				if(isset($_POST['TXNID'])){
					$status_comment[]="Paytm TXNID: " . $_POST['TXNID'];
				}
				
			}
			
			$sql_data_array = array('orders_id' => $insert_id,
                              'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => implode("\n", $status_comment));

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}
		else{
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Security error...!"), 'SSL', true, false));
		}
			
    }

    function output_error() {
      return false;
    }
		
		function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYTM_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }
		
		function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PAYTM Module', 'MODULE_PAYMENT_PAYTM_STATUS', 'True', 'Do you want to accept PAYTM payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('MerchantID', 'MODULE_PAYMENT_PAYTM_MERCHANT_ID', 'Paytm MerchantId', 'The Merchant Id given by Paytm', '6', '2', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Key', 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY', 'Paytm Merchant Key', 'Merchant key.Please note that get this key ,login to your Paytm merchant account', '6', '2', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Website', 'MODULE_PAYMENT_PAYTM_WEBSITE', 'Merchant Website', 'The Website given by Paytm', '6', '2', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Industry Type', 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', 'Industry type', 'The merchant industry type', '6', '2', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_PAYTM_MODE', 'Test', 'Mode of transactions : Test(Sandbox) or Live ', '6', '0', 'tep_cfg_select_option(array(\'Test\',\'Live\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('PAYTM Payment Zone', 'MODULE_PAYMENT_PAYTM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PAYTM Sort order of  display.', 'MODULE_PAYMENT_PAYTM_SORT_ORDER', '0', 'Sort order of PAYTM display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('PAYTM Set Order Status', 'MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
	    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Callback', 'MODULE_PAYMENT_PAYTM_CALLBACK', 'No', 'Would you like to enable Callback URL?', '6', '0', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
    }

    function remove() {
      $keys = '';
      $keys_array = $this->keys();
      for ($i=0; $i<sizeof($keys_array); $i++) {
        $keys .= "'" . $keys_array[$i] . "',";
      }
      $keys = substr($keys, 0, -1);
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in (" . $keys . ")");
    }
    function keys() {
      return array('MODULE_PAYMENT_PAYTM_STATUS', 'MODULE_PAYMENT_PAYTM_MERCHANT_ID','MODULE_PAYMENT_PAYTM_MERCHANT_KEY','MODULE_PAYMENT_PAYTM_MODE','MODULE_PAYMENT_PAYTM_ZONE','MODULE_PAYMENT_PAYTM_SORT_ORDER', 'MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID', 'MODULE_PAYMENT_PAYTM_CALLBACK','MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID', 'MODULE_PAYMENT_PAYTM_WEBSITE');
    }
  }
?>
