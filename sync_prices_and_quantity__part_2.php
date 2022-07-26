<?php
chdir('/public_html/miniapp_shopify/');
echo "Updating Price and Quantity";
require "./inc/database.php";
require "./inc/functions.php";


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function shopify_call($token, $shop, $api_endpoint, $query = array(), $method = 'GET', $request_headers = array())
{

	// Build URL
	//	$url = "https://" . $shop . ".myshopify.com" . $api_endpoint;
	$url = "https://" . $shop . ".myshopify.com" . $api_endpoint;
	if (!is_null($query) && in_array($method, array('GET', 	'DELETE'))) $url = $url . "?" . http_build_query($query);

	// Configure cURL
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 3);
	// curl_setopt($curl, CURLOPT_SSLVERSION, 3);
	curl_setopt($curl, CURLOPT_USERAGENT, 'My New Shopify App v.1');
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

	// Setup headers
	$request_headers[] = "";
	if (!is_null($token)) $request_headers[] = "X-Shopify-Access-Token: " . $token;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

	if ($method != 'GET' && in_array($method, array('POST', 'PUT'))) {
		if (is_array($query)) $query = http_build_query($query);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
	}

	// Send request to Shopify and capture any errors
	$response = curl_exec($curl);
	$error_number = curl_errno($curl);
	$error_message = curl_error($curl);

	// Close cURL to be nice
	curl_close($curl);

	// Return an error is cURL has a problem
	if ($error_number) {
		return $error_message;
	} else {

		// No error, return Shopify's response by parsing out the body and the headers
		$response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

		// Convert headers into an array
		$headers = array();
		$header_data = explode("\n", $response[0]);
		$headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
		array_shift($header_data); // Remove status, we've already set it above
		foreach ($header_data as $part) {
			$h = explode(":", $part);
			$headers[trim($h[0])] = trim($h[1]);
		}

		// Return headers and Shopify's response
		return array('headers' => $headers, 'response' => $response[1]);
	}
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function Adject_Quantity__Function($access_token, $host_shop, $varsion, $location_ID, $inventory_item_iD, $available_Adjustment)
{
	$modify_data = array(
		"location_id" => $location_ID,
		"inventory_item_id" => $inventory_item_iD,
		"available_adjustment" => $available_Adjustment
	);

	$modified_product_inventory_quantity = shopify_call($access_token, $host_shop, "/admin/api/" . $varsion . "/inventory_levels/adjust.json", $modify_data, 'POST');
	$modified_product_inventory_quantity_response = $modified_product_inventory_quantity['response'];
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function Adject_Price__Function($access_token, $host_shop, $varsion, $variant_ID, $adjustment_Price)
{

	$modify_data = array(
		"variant" => array(
			"id" => $variant_ID,
			"price" => $adjustment_Price
		)
	);

	$modified_product = shopify_call($access_token, $host_shop, "/admin/api/" . $varsion . "/variants/" . $variant_ID . ".json", $modify_data, 'PUT');
	$modified_product_response = $modified_product['response'];
	// print_r($modified_product_response);
	// if($modified_product_response){
	//     return true;
	// }
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$starttime = microtime(true); // Top of page
$Total__products__updated = 0;
$GOTDB___products_result = null;
$access_token = "";
$host_shop = "";

$GetDB_Shopify_App_DATA = $db->query("SELECT * FROM `auto_miniapp_shopify` WHERE `shop_url` = 'autospartoutlet.myshopify.com' LIMIT 1 ");

if (mysqli_num_rows($GetDB_Shopify_App_DATA) > 0) {
    $GetDB_Shopify_SHOP = mysqli_fetch_all($GetDB_Shopify_App_DATA);
}

if (sizeof($GetDB_Shopify_SHOP) > 0) {
    foreach ($GetDB_Shopify_SHOP as $shop__key => &$shop__value) {
        $host_shop = Get_host_shop($shop__value[1]);
        $access_token = ($shop__value[2]);
    }
}
$GetDB_products = $db->query("SELECT `inventory_prices`.id ,`inventory_prices`.sku , ( `inventory_prices`.cost + `inventory_prices`.fee + `inventory_prices`.`commission` + `inventory_prices`.`shipping` + `inventory_prices`.`profit`) as `net_price` ,`inventory_prices`.qty ,`shopify_product_details`.`v_id` , `shopify_product_details`.`p_id` , `shopify_product_details`.`inventory_item_id`,`shopify_product_details`.`location_id`,`shopify_product_details`.`inventory_quantity`, `shopify_product_details`.`available_adjustment` FROM `inventory_prices` LEFT JOIN `shopify_product_details` ON `inventory_prices`.`sku` = `shopify_product_details`.`sku` WHERE `inventory_prices`.`location_inventory_id_check` = 1 ");

if (mysqli_num_rows($GetDB_products) > 0) {
    $GOTDB___products_result = mysqli_fetch_all($GetDB_products);
}

// print_r($GOTDB___products_result);
// die();

$Total__products__updated = 0;
if (sizeof($GOTDB___products_result) > 0) {
    foreach ($GOTDB___products_result as $key => $value) :
        $Total__products__updated = $Total__products__updated + 1;
        // echo "v_ID  ---> ". $value[1];
        // echo "price  ---> ". $value[2];

        // echo "available_adjustment  ---> ". $value['available_adjustment'];
        // echo "inventory_item_id  ---> ". $value['inventory_item_id'];
        // echo "location_id  ---> ". $value['location_id'];
        // echo PHP_EOL;

        // ----------------------------------------------    Adjusting the price & counts of products  START      //
        Adject_Price__Function($access_token, $host_shop, "2022-04", $value[4], $value[2]);
        if (isset($value[7]) && isset($value[6]) && isset($value[9])) :
            if ($value[7] != ""  &&  $value[6] != "") {
                Adject_Quantity__Function($access_token, $host_shop, "2022-04", $value[7], $value[6], $value[9]);
                $check_if_sku_exist_in_db = $db->query("SELECT * FROM `shopify_product_details` WHERE `sku` = '$value[1]' ");
                // echo "SELECT * FROM `shopify_product_details` WHERE `sku` = '$temp_db_product_sku' " . PHP_EOL ;
                if (mysqli_num_rows($check_if_sku_exist_in_db) > 0) {
                    $res = $db->query("UPDATE `shopify_product_details` SET `inventory_quantity` = '". ( (int)$value[8] + (int)$value[9] ) ."', `available_adjustment` = 0 WHERE `sku` = '$value[1]' ");
                    // print_r($res);
                }
            }
        endif;
    // ----------------------------------------------    Adjusting the price & counts of products   END       //
    endforeach;
}
$endtime = microtime(true); // Bottom of page
printf("Page loaded in %f seconds", $endtime - $starttime );
echo PHP_EOL;
echo "Done Updating ". $Total__products__updated ." Products...";
