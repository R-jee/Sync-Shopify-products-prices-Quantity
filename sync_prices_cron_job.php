<?php
chdir('/public_html/miniapp_shopify/'); // your url app link
echo "hello";
require "./inc/database.php";
require "./inc/functions.php";

$Total__products__updated = 0;
$GraphQL__rate_limit_ = 50;
$GOTDB___products_result = [];
$GetDB_Shopify_SHOP = [];
$access_token = "";
$host_shop = "";
$ARRAY___ToUpdate_In_Shopify_ARRAY = array();

$GetDB_Shopify_App_DATA = $db->query("SELECT * FROM `auto_miniapp_shopify` WHERE `shop_url` = 'autospartoutlet.myshopify.com' ");
if (mysqli_num_rows($GetDB_Shopify_App_DATA) > 0) {
    $GetDB_Shopify_SHOP = mysqli_fetch_all($GetDB_Shopify_App_DATA);
}

if (sizeof($GetDB_Shopify_SHOP) > 0) {
    foreach ($GetDB_Shopify_SHOP as $shop__key => $shop__value) {

        $host_shop = Get_host_shop($shop__value[1]);
        $access_token = ($shop__value[2]);

        $GetDB_products = $db->query("SELECT * FROM `inventory_prices` WHERE `location_inventory_id_check` = 0 ");

        if (mysqli_num_rows($GetDB_products) > 0) {
            $GOTDB___products_result = mysqli_fetch_all($GetDB_products);
        }
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        if (sizeof($GOTDB___products_result) > 0) {
            foreach ($GOTDB___products_result as $db_key => $db_prod) {
                $temp_db_product_sku = $db_prod[2];
                $temp_db_product_quantity = $db_prod[8];
                $temp_db_product_netPrice = ($db_prod[3] + $db_prod[4] + $db_prod[5] + $db_prod[6] + $db_prod[7]);

                $temp_shopify_product_sku = "";
                $temp_shopify_product_quantity = "";
                $temp_shopify_product_netPrice = "";
                
                if ($db_key == 5000) {
                    // echo "Key :: ". $db_key;
                    // break;
                    // die();
                }

                if ($temp_db_product_sku != "") {

                    $GRAPHqL = shopify_graphQL_call($access_token, $host_shop, "2022-04", Query_get__product_with_sku($temp_db_product_sku));
                    $GRAPHqL_data = json_decode($GRAPHqL['response'], JSON_PRETTY_PRINT);

                    if (isset($GRAPHqL_data['extensions'])) {
                        $currentlyAvailable_ThrottledCost = $GRAPHqL_data['extensions']['cost']['throttleStatus']['currentlyAvailable'];
                        // print_r( $currentlyAvailable_ThrottledCost );
                        if ((int)$currentlyAvailable_ThrottledCost <= $GraphQL__rate_limit_) {
                            echo "currentlyAvailable_ThrottledCost  :: " . $currentlyAvailable_ThrottledCost . PHP_EOL;
                            sleep(1);
                        }
                    }
                    if ( isset($GRAPHqL_data['data']) ) :
                        if (isset($GRAPHqL_data['data']['products']['edges']) && $GRAPHqL_data['data']['products']['edges'] != array()) {
                            // echo "Array Key exists...";
                            $temp_Parent_prod_id = 0;
                            $temp_Parent_varient_product_id = 0;
                            $GOT_GRAPHQL__OUT_ARRAY = $GRAPHqL_data['data']['products']['edges'];
                            foreach ($GOT_GRAPHQL__OUT_ARRAY as $key => $GOT_GRAPHQL__OUT) :
                                $temp_Parent_prod_id = 0;
                                $temp_Parent_varient_product_id = 0;
                                $inventory_item_ID = 0;
                                $inventory_quantity = 0;
                                $location_ID = 0;
                                // print_r($GOT_GRAPHQL__OUT);
                                // die();
                                if (array_key_exists("node", $GOT_GRAPHQL__OUT)) {
                                    $temp_Parent_prod_id = graphQL__id__spliting($GOT_GRAPHQL__OUT['node']['id']);
                                    $temp_Parent_varient_product_id = graphQL__id__spliting($GOT_GRAPHQL__OUT['node']['variants']['edges'][0]['node']['id']);
                                    $inventory_item_ID = graphQL__id__spliting($GOT_GRAPHQL__OUT['node']['variants']['edges'][0]['node']['inventoryItem']['id']);
                                    $inventory_quantity = ($GOT_GRAPHQL__OUT['node']['variants']['edges'][0]['node']['inventoryQuantity']); // inventoryQuantity
                                    $location_ID = graphQL__id__spliting($GOT_GRAPHQL__OUT['node']['variants']['edges'][0]['node']['fulfillmentService']['location']['id']);
                                }

                                $l = ((int)$temp_db_product_quantity - (int)$inventory_quantity);
                                $check_if_sku_exist_in_db = $db->query("SELECT * FROM `shopify_product_details` WHERE `sku` = '$temp_db_product_sku' ");
                                // echo "SELECT * FROM `shopify_product_details` WHERE `sku` = '$temp_db_product_sku' " . PHP_EOL ;
                                if (mysqli_num_rows($check_if_sku_exist_in_db) > 0) {
                                    $db->query("UPDATE `shopify_product_details` SET `v_id` = '$temp_Parent_varient_product_id', `p_id` = '$temp_Parent_prod_id', `sku` = '$temp_db_product_sku', `price` = '$temp_db_product_netPrice', `quantity` = '$temp_db_product_quantity', `inventory_item_id` = '$inventory_item_ID', `location_id` = '$location_ID', `inventory_quantity` = '$inventory_quantity', `available_adjustment` = '$l' WHERE `sku` = '$temp_db_product_sku' ");
                                    $db->query("UPDATE `inventory_prices` SET `location_inventory_id_check`= 1 WHERE `sku` = '$temp_db_product_sku' ");
                                    // print_r($res);
                                } else if (mysqli_num_rows($check_if_sku_exist_in_db) == 0) {
                                    $db->query("INSERT INTO `shopify_product_details` (`id`, `v_id`, `p_id`, `sku`, `price`, `quantity`, `inventory_item_id`, `location_id`, `inventory_quantity`, `available_adjustment`) VALUES (NULL, '$temp_Parent_varient_product_id', '$temp_Parent_prod_id', '$temp_db_product_sku', $temp_db_product_netPrice, '$temp_db_product_quantity', '$inventory_item_ID', '$location_ID', '$inventory_quantity', '$l')");
                                    $db->query("UPDATE `inventory_prices` SET `location_inventory_id_check`= 1 WHERE `sku` = '$temp_db_product_sku' ");
                                }
                                // array_push(
                                //     $ARRAY___ToUpdate_In_Shopify_ARRAY,
                                //     array(
                                //         "v_id" =>  $temp_Parent_varient_product_id,
                                //         "p_id" => $temp_Parent_prod_id,
                                //         "sku" => $temp_db_product_sku,
                                //         "price" => $temp_db_product_netPrice,
                                //         "quantity" => $temp_db_product_quantity,
                                //         "inventory_item_id" => $inventory_item_ID,
                                //         "location_id" => $location_ID,
                                //         "inventory_quantity" => $inventory_quantity,
                                //         "available_adjustment" => ((int)$temp_db_product_quantity - (int)$inventory_quantity)
                                //     )
                                // );
                            endforeach;
                        } // end if
                    endif;
                    /*
                        if (array_key_exists("errors",$GRAPHqL_data)){
                            
                            // Query_throttleStatus_reSet();
                            print_r($GRAPHqL_data);
                            
                            $GRAPHqL = shopify_graphQL_call($access_token, $host_shop, "2022-04", Query_throttleStatus_reSet() );
                            $GRAPHqL_data = json_decode($GRAPHqL['response'], JSON_PRETTY_PRINT);
                            print_r($GRAPHqL_data);
                            die();
                            
                        }
                    */
                }
            }

            // print_r( $ARRAY___ToUpdate_In_Shopify_ARRAY );

        }
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////


        // $ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL = array();
        // $ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL = $ARRAY___ToUpdate_In_Shopify_ARRAY;
        // // // print_r($ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL);
        // if (isset($ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL)) :
        //     $Total__products__updated = 0;
        //     if (sizeof($ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL) > 0) {
        //         foreach ($ARRAY___ToUpdate_In_Shopify_ARRAY_FINAL as $key => $value) :
        //             $Total__products__updated = $Total__products__updated + 1;
        //             // echo "v_ID  ---> ". $value['v_id'];
        //             // echo "price  ---> ". $value['price'];
        //             // echo "available_adjustment  ---> ". $value['available_adjustment'];
        //             // echo "inventory_item_id  ---> ". $value['inventory_item_id'];
        //             // echo "location_id  ---> ". $value['location_id'];
        //             // echo PHP_EOL;

        //             // ----------------------------------------------    Adjusting the price & counts of products  START      //
        //             Adject_Price__Function($access_token, $host_shop, "2022-04", $value['v_id'], $value['price']);
        //             if (isset($value['location_id']) && isset($value['inventory_item_id']) && isset($value['available_adjustment'])) :
        //                 if ($value['location_id'] != ""  &&  $value['inventory_item_id'] != "" && $value['available_adjustment'] != 0) {
        //                     Adject_Quantity__Function($access_token, $host_shop, "2022-04", $value['location_id'], $value['inventory_item_id'], $value['available_adjustment']);
        //                 }
        //             endif;
        //         // ----------------------------------------------    Adjusting the price & counts of products   END       //
        //         endforeach;
        //     }
        // endif;
        // print_r($ARRAY___ToUpdate_In_Shopify_ARRAY);
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        ///////////////////////////////////////////////////////////////////////////////////////////////////


    } // end foreach loop
} // end if 


echo "Done Updating Products...";
