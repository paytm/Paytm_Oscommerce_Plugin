<?php
/*
	$Id$

	osCommerce, Open Source E-Commerce Solutions
	http://www.oscommerce.com

	Copyright (c) 2010 osCommerce

	Released under the GNU General Public License
*/

require('includes/application_top.php');

/*
* Promo Code functions here
*/

apply_coupon($HTTP_POST_VARS);

function apply_coupon($post_params = array()) {

	// promo code feature must be enabled
	if(!defined('MODULE_PAYMENT_PAYTM_PROMO_CODE_STATUS') || MODULE_PAYMENT_PAYTM_PROMO_CODE_STATUS != "True"){
		return;
	}
	
	if(isset($post_params["promo_code"]) && trim($post_params["promo_code"]) != "") {

		$json = array();

		$check_query = MODULE_PAYMENT_PAYTM_PROMO_CODE_VALIDATION;


		// if promo code local validation enabled
		if(defined('MODULE_PAYMENT_PAYTM_PROMO_CODE_VALIDATION') && MODULE_PAYMENT_PAYTM_PROMO_CODE_VALIDATION == "True"){

			$promo_codes = explode(",", MODULE_PAYMENT_PAYTM_PROMO_CODES);

			$promo_code_found = false;

			foreach($promo_codes as $key=>$val){
				// entered promo code should matched
				if(trim($val) == trim($post_params["promo_code"])) {
					$promo_code_found = true;
					break;
				}
			}

		} else {
			$promo_code_found = true;
		}

		if($promo_code_found){
			$json = array("success" => true, "message" => "Applied Successfully");
			
			$reqParams = $post_params;

			if(isset($reqParams["promo_code"])){
				// PROMO_CAMP_ID is key for Promo Code at Paytm's end
				$reqParams["PROMO_CAMP_ID"] = $reqParams["promo_code"];
			
				// unset promo code sent in request 
				unset($reqParams["promo_code"]);

				// unset CHECKSUMHASH
				unset($reqParams["CHECKSUMHASH"]);
			}

			// create a new checksum with Param Code included and send it to browser
			require_once(DIR_WS_MODULES."payment/paytm.php");
			$json['CHECKSUMHASH'] = getChecksumFromArray($reqParams, MODULE_PAYMENT_PAYTM_MERCHANT_KEY);
		} else {
			$json = array("success" => false, "message" => "Incorrect Promo Code");
		}

		echo json_encode($json); exit;
	}
}
/*
* Promo Code functions here
*/


/*
* Code to test Curl
*/
if(isset($_GET['paytm_action']) && $_GET['paytm_action'] == "curltest"){
	curltest();
}

function curltest(){

	// phpinfo();exit;
	$debug = array();

	if(!function_exists("curl_init")){
		$debug[0]["info"][] = "cURL extension is either not available or disabled. Check phpinfo for more info.";

	// if curl is enable then see if outgoing URLs are blocked or not
	} else {

		// if any specific URL passed to test for
		if(isset($_GET["url"]) && $_GET["url"] != ""){
			$testing_urls = array($_GET["url"]);   
		
		} else {

			$testing_urls = array(
											tep_href_link(FILENAME_DEFAULT), // this site homepage URL
											"www.google.co.in",
											MODULE_PAYMENT_PAYTM_TRANSACTION_STATUS_URL
										);
		}

		// loop over all URLs, maintain debug log for each response received
		foreach($testing_urls as $key=>$url){

			$debug[$key]["info"][] = "Connecting to <b>" . $url . "</b> using cURL";

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$res = curl_exec($ch);

			if (!curl_errno($ch)) {
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$debug[$key]["info"][] = "cURL executed succcessfully.";
				$debug[$key]["info"][] = "HTTP Response Code: <b>". $http_code . "</b>";

				// $debug[$key]["content"] = $res;

			} else {
				$debug[$key]["info"][] = "Connection Failed !!";
				$debug[$key]["info"][] = "Error Code: <b>" . curl_errno($ch) . "</b>";
				$debug[$key]["info"][] = "Error: <b>" . curl_error($ch) . "</b>";
				break;
			}

			curl_close($ch);
		}
	}

	$content = "";
	foreach($debug as $k=>$v){
		$content .= "<ul>";
		foreach($v["info"] as $info){
			$content .= "<li>".$info."</li>";
		}
		$content .= "</ul>";

		// echo "<div style='display:none;'>" . $v["content"] . "</div>";
		$content .= "<hr/>";
	}

	echo $content;
}
/*
* Code to test Curl
*/