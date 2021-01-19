<?php
/*
$Id$

osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com

Copyright (c) 2003 osCommerce

Released under the GNU General Public License
 */

require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../PaytmChecksum.php');
require(dirname(__FILE__) . DIRECTORY_SEPARATOR . '/paytmlib/PaytmHelper.php');

class paytm {

	public $code, $title, $description, $enabled;

	public function paytm() {

		$this->code = 'paytm';
		$this->title = MODULE_PAYMENT_PAYTM_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_PAYTM_TEXT_DESCRIPTION;

		$last_updated = "";
		$path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "/paytm_version.txt";
		if (file_exists($path)) {
			$handle = fopen($path, "r");
			if ($handle !== false) {
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<p>Last Updated: ' . date("d F Y", strtotime($date)) . '</p>';
			}
		}

		$this->description = '<hr/><div class="text-center">' . $last_updated . '<p>OSCommerce Version: ' . PROJECT_VERSION . '</p></div><hr/>';

		$this->sort_order = MODULE_PAYMENT_PAYTM_SORT_ORDER;
		$this->enabled = ((MODULE_PAYMENT_PAYTM_STATUS == 'True') ? true : false);

		if ((int) MODULE_PAYMENT_PAYTM_ORDER_PENDING_STATUS_ID > 0) {
			$this->order_status = MODULE_PAYMENT_PAYTM_ORDER_PENDING_STATUS_ID;
		}

		if (is_object($order)) {
			$this->update_status();
		}

		//$this->form_action_url = MODULE_PAYMENT_PAYTM_TRANSACTION_URL;
		$mod = MODULE_PAYMENT_PAYTM_ENVT;    
			if($mod == "Test"){
				$this->paytmurl = PaytmConstants::STAGING_HOST;
				$this->init_txn_url = PaytmConstants::TRANSACTION_INIT_URL_STAGING;
				//$this->init_txn_status = "https://securegw-stage.paytm.in/"; PRODUCTION_HOST   STAGING_HOST
			}else{
				$this->paytmurl = PaytmConstants::PRODUCTION_HOST;
				$this->init_txn_url = PaytmConstants::TRANSACTION_INIT_URL_PRODUCTION;
		}
	}


	public function update_status() {
		global $order;

		if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAYTM_ZONE > 0)) {
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

	public function javascript_validation() {
		return false;
	}

	public function selection() {
		return array(
					'id'		=> $this->code,
					'module'	=> $this->title
				);
	}

	public function pre_confirmation_check() {
		global $cart, $order;
		
	//	$this->form_action_url = MODULE_PAYMENT_PAYTM_TRANSACTION_URL;

		if (empty($cart->cartID)) {
			$cart->cartID = $cart->generate_cart_id();
		}

		if (!tep_session_is_registered('cartID')) {
			tep_session_register('cartID');
		}
		$order->info['payment_method'] = '<img src="ext/modules/payment/paytm/images/logo.png" border="0" alt="Paytm Logo" style="padding: 3px; width:auto; height: 100%;" />';
	}


	public function confirmation() {

		global $cartID, $cart_DirecPay_ID, $customer_id, $languages_id, $order, $order_total_modules;

		if (tep_session_is_registered('cartID')) {

			$insert_order = false;

			if (tep_session_is_registered('cart_DirecPay_ID')) {

				$order_id = substr($cart_DirecPay_ID, strpos($cart_DirecPay_ID, '-') + 1);

				$curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int) $order_id . "'");

				$curr = tep_db_fetch_array($curr_check);

				if (($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_DirecPay_ID, 0, strlen($cartID)))) {

					$check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int) $order_id . '" limit 1');

					if (tep_db_num_rows($check_query) < 1) {

						tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int) $order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int) $order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int) $order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int) $order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int) $order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int) $order_id . '"');

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

							for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) {

								if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {

									$order_totals[] = array(
														'code'	=> $GLOBALS[$class]->code,
														'title'	=> $GLOBALS[$class]->output[$i]['title'],
														'text'	=> $GLOBALS[$class]->output[$i]['text'],
														'value'	=> $GLOBALS[$class]->output[$i]['value'],
														'sort_order'=> $GLOBALS[$class]->sort_order
														);
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

				for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {

					$sql_data_array = array(
											'orders_id' => $insert_id,
											'title'		=> $order_totals[$i]['title'],
											'text'		=> $order_totals[$i]['text'],
											'value'		=> $order_totals[$i]['value'],
											'class'		=> $order_totals[$i]['code'],
											'sort_order'=> $order_totals[$i]['sort_order']
										);

					tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);

				}

				for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {

					$sql_data_array = array(
											'orders_id'			=> $insert_id,
											'products_id'		=> tep_get_prid($order->products[$i]['id']),
											'products_model'	=> $order->products[$i]['model'],
											'products_name'		=> $order->products[$i]['name'],
											'products_price'	=> $order->products[$i]['price'],
											'final_price'		=> $order->products[$i]['final_price'],
											'products_tax'		=> $order->products[$i]['tax'],
											'products_quantity'	=> $order->products[$i]['qty']
										);

					tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

					$order_products_id = tep_db_insert_id();

					$attributes_exist = '0';

					if (isset($order->products[$i]['attributes'])) {

						$attributes_exist = '1';

						for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {

							if (DOWNLOAD_ENABLED == 'true') {

								$attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
									from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
									left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
									on pa.products_attributes_id=pad.products_attributes_id
									where pa.products_id = '" . $order->products[$i]['id'] . "
									and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
									and pa.options_id = popt.products_options_id
									and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
									and pa.options_values_id = oval.products_options_values_id
									and popt.language_id = '" . $languages_id . "'
									and poval.language_id = '" . $languages_id . "'";

								$attributes = tep_db_query($attributes_query);

							} else {

								$attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");

							}

							$attributes_values = tep_db_fetch_array($attributes);

							$sql_data_array = array(
								'orders_id'				=> $insert_id,
								'orders_products_id'	=> $order_products_id,
								'products_options'		=> $attributes_values['products_options_name'],
								'products_options_values'=> $attributes_values['products_options_values_name'],
								'options_values_price'	=> $attributes_values['options_values_price'],
								'price_prefix' 			=> $attributes_values['price_prefix']
							);

							tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

							if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {

								$sql_data_array = array(
									'orders_id'				=> $insert_id,
									'orders_products_id'	=> $order_products_id,
									'orders_products_filename'=> $attributes_values['products_attributes_filename'],
									'download_maxdays'		=> $attributes_values['products_attributes_maxdays'],
									'download_count'		=> $attributes_values['products_attributes_maxcount']
								);

								tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
							}
						}
					}
				}

				$cart_DirecPay_ID = $cartID . '-' . $insert_id;

				tep_session_register('cart_DirecPay_ID');
			}
		}

	}

	public function process_button() {
		global $order, $customer_id, $cart, $cart_DirecPay_ID;

		$merchant_key = html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);

		$cust_id = !empty($customer_id) ? $customer_id : $order->customer['email_address'];
		
		$customCallBackUrl = MODULE_PAYMENT_PAYTM_CUSTOM_CALLBACKURL;

		$amount = number_format($order->info['total'],2);
		//$orderId = $cart->cartID;
		$order_id = substr($cart_DirecPay_ID, strpos($cart_DirecPay_ID, '-') + 1);
		$_SESSION['sorderid'] = $order_id;

		// $order_id = "TEST_".date("Ymdh").'_'.$order_id; // just for testing

		$post_variables = array(
								"MID" 				=> MODULE_PAYMENT_PAYTM_MERCHANT_ID,
								"ORDER_ID"			=> PaytmHelper::getPaytmOrderId($order_id),
								"CUST_ID"			=> $cust_id,
								"WEBSITE"			=> MODULE_PAYMENT_PAYTM_WEBSITE,
								"INDUSTRY_TYPE_ID"  => MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID,
								"EMAIL"				=> $order->customer['email_address'],
								"MOBILE_NO"			=> $order->customer['telephone'],
								"CHANNEL_ID"		=> PaytmConstants::CHANNEL_ID,
								"TXN_AMOUNT"		=> $amount,
							);

		$post_variables['CALLBACK_URL'] = tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');

		if (trim($customCallBackUrl) != '') {
			if (filter_var($customCallBackUrl, FILTER_VALIDATE_URL)) {
				$post_variables['CALLBACK_URL'] = $customCallBackUrl;
			}
		}
		$process_button_string = '';
		$paytmParams["body"] = array(
            "requestType" => "Payment",
            "mid" => $post_variables["MID"],
            "websiteName" => $post_variables["WEBSITE"],
            "orderId" => $post_variables["ORDER_ID"],
            "callbackUrl" => $post_variables["CALLBACK_URL"],
            "txnAmount" => array(
                "value" => $post_variables["TXN_AMOUNT"],
                "currency" => "INR",
            ),
            "userInfo" => array(
                "custId" => $post_variables["CUST_ID"],
                "mobile" => $post_variables["MOBILE_NO"],
                "email" =>  $post_variables["EMAIL"],
            ),
        );
        $generateSignature = PaytmChecksum::generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $merchant_key);
        $paytmParams["head"] = array(
            "signature" => $generateSignature
        );
        $apiURL = $this->init_txn_url.$post_variables["MID"] . "&orderId=" . $post_variables["ORDER_ID"];

        $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $response_array = PaytmHelper::executecUrl($apiURL, $post_data_string);

        if(!empty($response_array['body']['txnToken'])){
        $txnToken = $response_array['body']['txnToken'];
        $paytm_msg = PaytmConstants::TNX_TOKEN_GENERATED;
        }else{
         $txnToken = '';
         $paytm_msg = PaytmConstants::RESPONSE_ERROR;

        }

       //print_r($response_array);  
        $post_variables['TXN_TOKEN'] = $txnToken;
        $post_variables['PAYTM_MSG'] = $paytm_msg;

        $spinner = '<div id="paytm-pg-spinner" class="paytm-pg-loader"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div><div class="bounce4"></div><div class="bounce5"></div></div>';
        $loader = '$("html").find("#bodyWrapper").after("<div></div>");';

			$process_button_string = $spinner.'<script type="application/javascript" crossorigin="anonymous" src="'.$this->paytmurl.'/merchantpgpui/checkoutjs/merchants/'.$post_variables['MID'].'.js"></script>
				
			        <style type="text/css">
            #paytm-pg-spinner {margin: 0% auto 0;width: 70px;text-align: center;z-index: 999999;position: relative;display:block }

            #paytm-pg-spinner > div {width: 10px;height: 10px;background-color: #012b71;border-radius: 100%;display: inline-block;-webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;animation: sk-bouncedelay 1.4s infinite ease-in-out both;}

            #paytm-pg-spinner .bounce1 {-webkit-animation-delay: -0.64s;animation-delay: -0.64s;}

            #paytm-pg-spinner .bounce2 {-webkit-animation-delay: -0.48s;animation-delay: -0.48s;}
            #paytm-pg-spinner .bounce3 {-webkit-animation-delay: -0.32s;animation-delay: -0.32s;}

            #paytm-pg-spinner .bounce4 {-webkit-animation-delay: -0.16s;animation-delay: -0.16s;}
            #paytm-pg-spinner .bounce4, #paytm-pg-spinner .bounce5{background-color: #48baf5;} 
            @-webkit-keyframes sk-bouncedelay {0%, 80%, 100% { -webkit-transform: scale(0) }40% { -webkit-transform: scale(1.0) }}

            @keyframes sk-bouncedelay { 0%, 80%, 100% { -webkit-transform: scale(0);transform: scale(0); } 40% { 
                                            -webkit-transform: scale(1.0); transform: scale(1.0);}}
            .paytm-overlay{width: 100%;position: fixed;top: 0px;opacity: .4;height: 100%;background: #000;display: block;z-index: 99999999;}

        </style>	

		
		<script type="text/javascript">
							 
		$(document).ready(function(){
 
          '.$loader.'
            $("#bodyWrapper").next("div").addClass("paytm-overlay paytm-pg-loader");
            $(".paytm-overlay").css("display","block");
            $("#paytm-pg-spinner").css("display","block !important");


              function openJsCheckout(){
                 var config = {
                        "root": "",
                        "flow": "DEFAULT",
                        "data": {
                            "orderId": "'.$post_variables['ORDER_ID'].'",
                            "token": "'.$post_variables['TXN_TOKEN'].'",
                            "tokenType": "TXN_TOKEN",
                            "amount": "'.$post_variables['TXN_AMOUNT'].'"
                        },
                        "merchant": {
                            "redirect": true
                        },
                        "handler": {
                            
                            "notifyMerchant": function (eventName, data) {
                                
                                if(eventName == "SESSION_EXPIRED"){
                                location.reload(); 
                               }
                            }
                        }
                    };
                    if (window.Paytm && window.Paytm.CheckoutJS) {
                        // initialze configuration using init method 
                        window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
                            // after successfully updating configuration, invoke checkoutjs
                            window.Paytm.CheckoutJS.invoke();

                        jQuery(".paytm-overlay").css("display","none");
                        //jQuery("#paytm-pg-spinner").css("display","none");

                        }).catch(function onError(error) {
                            //console.log("error => ", error);
                        });
                    }

              }
              var txnToken = "'.$post_variables['TXN_TOKEN'].'";
              if(txnToken){

              setTimeout(function(){openJsCheckout()},3000);

              }else{
              	jQuery("#tdb5").after("<div>'.$post_variables['PAYTM_MSG'].'</div>");
              	jQuery("#tdb5").next("div").css("color","red");
              	jQuery(".paytm-overlay").css("display","none");
                jQuery("#paytm-pg-spinner").css("display","none");

              }

             
            });
			</script>
						';

		return $process_button_string;
	}


	public function before_process() {

		global $cart;

		$isValidChecksum = false;
		$txnstatus = false;

		$merchant_key = html_entity_decode(MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
		$paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : "";

		//$isValidChecksum = verifychecksum_e($_POST, $merchant_key, $paytmChecksum);
		$isValidChecksum = PaytmChecksum::verifySignature($_POST, $merchant_key, $paytmChecksum);
		if(isset($_POST['STATUS']) && $_POST['STATUS'] == "TXN_SUCCESS") {
			$txnstatus = true;

			
		}

		

		$paytm_order_id = isset($_POST['ORDERID'])? $_POST['ORDERID'] : "";
		$order_id = PaytmHelper::getOrderId($paytm_order_id);

		// $order_id = str_replace("TEST_".date("Ymdh")."_", "", $order_id); // just for testing

	 	$order_query = tep_db_query("SELECT orders_id FROM " . TABLE_ORDERS . " WHERE orders_id = '" . (int)$order_id . "'");

		if ( tep_db_num_rows($order_query) === 1 ) {



			if($isValidChecksum && $txnstatus) {




		     $requestParamList = array("MID" => MODULE_PAYMENT_PAYTM_MERCHANT_ID , "ORDERID" => $paytm_order_id);

		     $paytmParamsStatus = array();
                /* body parameters */
             $paytmParamsStatus["body"] = array(
                    /* Find your MID in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys */
                    "mid" => $requestParamList['MID'],
                    /* Enter your order id which needs to be check status for */
                    "orderId" => $requestParamList['ORDERID'],
                );
              $checksumStatus = PaytmChecksum::generateSignature(json_encode($paytmParamsStatus["body"], JSON_UNESCAPED_SLASHES), MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
                /* head parameters */
               $paytmParamsStatus["head"] = array(
                    /* put generated checksum value here */
               "signature" => $checksumStatus
               );
                /* prepare JSON string for request */
                $post_data_status = json_encode($paytmParamsStatus, JSON_UNESCAPED_SLASHES);
                $apiURL = $this->paytmurl.PaytmConstants::ORDER_STATUS_URL; 
                //$responseStatusArray = PaytmHelper::executecUrl($apiURL, $post_data_string);
                $ch = curl_init($apiURL);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_status);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                $responseJson = curl_exec($ch);
                $responseStatusArray = json_decode($responseJson, true);
				if ($responseStatusArray['body']['resultInfo']['resultStatus']=='TXN_SUCCESS' && $responseStatusArray['body']['txnAmount']==$_POST['TXNAMOUNT']) {

					$status_comment = array();

					if (isset($_POST)) {
						if (isset($_POST['ORDERID'])) {
							$status_comment[] = "Order Id: " . $_POST['ORDERID'];
						}

						if (isset($_POST['TXNID'])) {
							$status_comment[] = "Paytm TXNID: " . $_POST['TXNID'];
						}
					}


					$sql_data_array = array(
											'orders_id' => $order_id,
											'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID,
											'date_added' => 'now()',
											'customer_notified' => '0',
											'comments' => implode("\n", $status_comment)
										);

					// add in order history
					tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

					// update order status
					tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = ".MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID." WHERE orders_id = '" . $order_id . "'");

					// reset the cart
					$cart->reset(true);

					// redirect to success page
					tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));

				} else {

					$sql_data_array = array(
											'orders_id' => $order_id,
											'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID,
											'date_added' => 'now()',
											'customer_notified' => '0',
											'comments' => implode("\n", $status_comment)
										);

					// add in order history
					tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

					// update order status
					tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = ".MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID." WHERE orders_id = '" . $order_id . "'");

					tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode($_POST["RESPMSG"]), 'SSL', true, false));
				}
	 
			} else if($isValidChecksum && !$txnstatus){
				
				$sql_data_array = array(
											'orders_id' => $order_id,
											'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID,
											'date_added' => 'now()',
											'customer_notified' => '0',
											'comments' => implode("\n", $status_comment)
										);

					// add in order history
					tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

					// update order status
					tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = ".MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID." WHERE orders_id = '" . $order_id . "'");

					tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode($_POST["RESPMSG"]), 'SSL', true, false));

			} else {

				$sql_data_array = array(
											'orders_id' => $order_id,
											'orders_status_id' => MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID,
											'date_added' => 'now()',
											'customer_notified' => '0',
											'comments' => implode("\n", $status_comment)
										);

				// add in order history
				tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

				// update order status
				tep_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = ".MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID." WHERE orders_id = '" . $order_id . "'");

				tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("It seems some issue in server to server communication. Kindly connect with administrator."), 'SSL', true, false));

			}
		}  else {
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'error_message=' . urlencode("Security error...!"), 'SSL', true, false));
		}
	}


	public function after_process() {
		return false;
	}


	public function output_error() {
		return false;
	}


	public function check() {
		if (!isset($this->_check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYTM_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}


	private function getParams(){

		// order status for pending payment orders, this will be set as default untill paytm send response back
		if (!defined('MODULE_PAYMENT_PAYTM_ORDER_PENDING_STATUS_ID')) {
		$check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Paytm [Payment Pending]' limit 1");

			if (tep_db_num_rows($check_query) < 1) {
				$status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
				$status = tep_db_fetch_array($status_query);

				$pending_status_id = $status['status_id']+1;

				$languages = tep_get_languages();

				foreach ($languages as $lang) {
				tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $pending_status_id . "', '" . $lang['id'] . "', 'Paytm [Payment Pending]')");
				}

				$flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
				if (tep_db_num_rows($flags_query) == 1) {
					tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $pending_status_id . "'");
				}
			} else {
				$check = tep_db_fetch_array($check_query);
				$pending_status_id = $check['orders_status_id'];
			}

		} else {
			$pending_status_id = MODULE_PAYMENT_PAYTM_ORDER_PENDING_STATUS_ID;
		}

		// order status for pending payment orders, this will be set as default untill paytm send response back
		if (!defined('MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID')) {
		$check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Paytm [Payment Failed]' limit 1");

			if (tep_db_num_rows($check_query) < 1) {
				$status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
				$status = tep_db_fetch_array($status_query);

				$failed_status_id = $status['status_id']+1;

				$languages = tep_get_languages();

				foreach ($languages as $lang) {
					tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $failed_status_id . "', '" . $lang['id'] . "', 'Paytm [Payment Failed]')");
				}

				$flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
				if (tep_db_num_rows($flags_query) == 1) {
					tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $failed_status_id . "'");
				}
			} else {
				$check = tep_db_fetch_array($check_query);
				$failed_status_id = $check['orders_status_id'];
			}

		} else {
			$failed_status_id = MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID;
		}


		// order status on success
		if (!defined('MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID')) {
		$check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Paytm [Success]' limit 1");

			if (tep_db_num_rows($check_query) < 1) {
				$status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
				$status = tep_db_fetch_array($status_query);

				$tx_status_id = $status['status_id']+1;

				$languages = tep_get_languages();

				foreach ($languages as $lang) {
				tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $tx_status_id . "', '" . $lang['id'] . "', 'Paytm [Success]')");
				}

				$flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
				if (tep_db_num_rows($flags_query) == 1) {
					tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $tx_status_id . "'");
				}
			} else {
				$check = tep_db_fetch_array($check_query);
				$tx_status_id = $check['orders_status_id'];
			}

		} else {
			$tx_status_id = MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID;
		}


		$params = array();
		$sort_order = 0;

		// Module status
		$params[] = array(
						'configuration_title'		=> 'Enable Paytm Module',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_STATUS',
						'configuration_value'		=> 'True',
						'configuration_description'=> 'Do you want to accept Paytm payments?',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_select_option(array(\'True\', \'False\'), ',
						'date_added'					=> 'now()'
					);


		$params[] = array(
						'configuration_title'		=> 'Paytm Environment',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_ENVT',
						'configuration_value'		=> 'Test',
						'configuration_description' => 'Choose your paytm environment Test/Live',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_select_option(array(\'Test\', \'Live\'), ',
						'date_added'					=> 'now()'
					);

		// Merchant Id
		$params[] = array(
						'configuration_title'		=> 'Merchant ID',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_MERCHANT_ID',
						'configuration_value'		=> '',
						'configuration_description'=> 'Merchant ID Provided by Paytm',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);


		// Merchant Key
		$params[] = array(
						'configuration_title'		=> 'Merchant Key',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_MERCHANT_KEY',
						'configuration_value'		=> '',
						'configuration_description'=> 'Merchant Secret Key Provided by Paytm',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);

		// Website
		$params[] = array(
						'configuration_title'		=> 'Website',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_WEBSITE',
						'configuration_value'		=> '',
						'configuration_description'=> 'Website Name Provided by Paytm',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);

		// Industry Type
		$params[] = array(
						'configuration_title'		=> 'Industry Type',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_INDUSTRY_TYPE_ID',
						'configuration_value'		=> '',
						'configuration_description'=> 'Industry Type Provided by Paytm',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);

		// Custom Callback URL
		$params[] = array(
						'configuration_title'		=> 'Custom Callback URL',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_CUSTOM_CALLBACKURL',
						'configuration_value'		=> '',
						'configuration_description'=> 'Leave it blank for Default',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);

		// Paytm Payment Zone
		$params[] = array(
						'configuration_title'		=> 'Paytm Payment Zone',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_ZONE',
						'configuration_value'		=> '',
						'configuration_description'=> 'If a zone is selected, only enable this payment method for that zone.',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_pull_down_zone_classes(',
						'use_function'					=> 'tep_get_zone_class_title',
						'date_added'					=> 'now()'
					);

		// Pending Order Status
		$params[] = array(
						'configuration_title'		=> 'Paytm Set Order Status for Pending Payments',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_ORDER_PENDING_STATUS_ID',
						'configuration_value'		=> $pending_status_id,
						'configuration_description'=> 'Set the status of orders made with this payment module to this value.',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_pull_down_order_statuses(',
						'use_function'					=> 'tep_get_order_status_name',
						'date_added'					=> 'now()'
					);

		// Failed Order Status
		$params[] = array(
						'configuration_title'		=> 'Paytm Set Order Status for Failed Payments',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_ORDER_FAILED_STATUS_ID',
						'configuration_value'		=> $failed_status_id,
						'configuration_description'=> 'Set the status of orders made with this payment module to this value.',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_pull_down_order_statuses(',
						'use_function'					=> 'tep_get_order_status_name',
						'date_added'					=> 'now()'
					);

		// Success Order Status
		$params[] = array(
						'configuration_title'		=> 'Paytm Set Order Status for Success Payments',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_ORDER_STATUS_ID',
						'configuration_value'		=> $tx_status_id,
						'configuration_description'=> 'Set the status of orders made with this payment module to this value.',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'set_function'					=> 'tep_cfg_pull_down_order_statuses(',
						'use_function'					=> 'tep_get_order_status_name',
						'date_added'					=> 'now()'
					);

		// Sort Order
		$params[] = array(
						'configuration_title'		=> 'Sort order of display',
						'configuration_key'			=> 'MODULE_PAYMENT_PAYTM_SORT_ORDER',
						'configuration_value'		=> '0',
						'configuration_description'=> 'Sort order of Paytm display. Lowest is displayed first.',
						'configuration_group_id'	=> '6',
						'sort_order'					=> ++$sort_order,
						'date_added'					=> 'now()'
					);




		return $params;
	}


	public function keys(){
		$keys = array();
		foreach($this->getParams() as $k=>$v){
			$keys[] = $v["configuration_key"];
		}
		return $keys;
	}

	public function install() {

		$params = $this->getParams();
		foreach($params as $k=>$v){
			tep_db_perform(TABLE_CONFIGURATION, $v);
		}
	}

	public function remove() {
		tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('". implode("','", $this->keys()) ."')");
	}
}