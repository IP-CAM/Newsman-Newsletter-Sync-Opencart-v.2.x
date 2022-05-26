<?php

/**
 * Newsman Newsletter Sync
 *
 * @author corodeanu lucian for Newsman
 */
class ControllerModuleNewsmanImport extends Controller
{
    /**
     * Run import
     */
    public function index()
    {
        $this->load->model('module/newsman_import');
        $this->load->model('setting/setting');

        $settings = (array)$this->model_setting_setting->getSetting('newsman_import');
        $_apikey = $settings["api_key"];

        $cron = (empty($_GET["cron"]) ? "" : $_GET["cron"]);
        if (!empty($_GET["cron"])) {

            $apikey = (empty($_GET["apikey"])) ? "" : $_GET["apikey"]; 

            if ($apikey != $_apikey) {
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode("403"));
                return;
            }

            if ($this->model_module_newsman_import->import_to_newsman())
            {
                $this->db->query("UPDATE " . DB_PREFIX . "setting SET value='" . date("Y-m-d H:i:s") . "' WHERE `code` = 'newsman_import' AND `key` = 'last_data_time'");
                echo "Sync success";
            }
            else{
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode("403 - sync unsuccessful, an error occurred or 1 hour didn't pass since last cron sync"));
                return;
            }
        }
        elseif(!empty($_GET["newsman"]) && $_GET["newsman"] == "getCart.json")
        {
            $this->getCart();
        } 
        else {
            $this->newsmanFetchData($_apikey);
        }
    }

    public function getCart(){
        $prod = array();
        $cart = $this->cart->getProducts();
        
        foreach ( $cart as $cart_item_key => $cart_item ) {

            $prod[] = array(
                "id" => $cart_item['product_id'],
                "name" => $cart_item["name"],
                "price" => $cart_item["price"],
                "quantity" => $cart_item['quantity']
            );
                                    
         }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($prod, JSON_PRETTY_PRINT));        
        return;
    }

    public function newsmanFetchData($_apikey)
    {
        $apikey = (empty($_GET["apikey"])) ? "" : $_GET["apikey"];
        $newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];
        $productId = (empty($_GET["product_id"])) ? "" : $_GET["product_id"];
        $orderId = (empty($_GET["order_id"])) ? "" : $_GET["order_id"];
        $start = (!empty($_GET["start"]) && $_GET["start"] >= 0) ? $_GET["start"] : 1;
        $limit = (empty($_GET["limit"])) ? 1000 : $_GET["limit"];

        if (!empty($newsman) && !empty($apikey)) {     
            $currApiKey = $_apikey;

            if ($apikey != $currApiKey) {
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode("403"));
                return;
            }

            switch ($_GET["newsman"]) {
                case "orders.json":

                    $ordersObj = array();

                    $this->load->model('catalog/product');
                    $this->load->model('account/order');
                    
                    $orders = $this->model_account_order->getOrders(array("start" => $start, "limit" => $limit));

                    if(!empty($orderId))
                    {
                        $orders = $this->model_account_order->getOrder($orderId);                        
                        $orders = array(
                            $orders
                        );
                    }        

                    foreach ($orders as $item) {

                        $products = $this->model_account_order->getOrderProducts($item["order_id"]);
                        $productsJson = array();

                        foreach ($products as $prodOrder) {

                            $prod = $this->model_catalog_product->getProduct($prodOrder["product_id"]);

                            $image = "";

                            if(!empty($prod["image"]))
                            {
                                $image = explode(".", $prod["image"]);
                                $image = $image[1];  
                                $image = str_replace("." . $image, "-500x500" . '.' . $image, $prod["image"]);    
                                $image = 'https://' . $_SERVER['SERVER_NAME'] . '/image/cache/' . $image;                                
                            }

                            $productsJson[] = array(
                                "id" => $prodOrder['product_id'],
                                "name" => $prodOrder['name'],
                                "quantity" => $prodOrder['quantity'],
                                "price" => $prodOrder['price'],
                                "price_old" => (empty($prodOrder["special"]) ? "" : $prodOrder["special"]),
                                "image_url" => $image,
                                "url" => 'https://' . $_SERVER['SERVER_NAME'] . '/index.php?route=product/product&product_id=' . $prodOrder["product_id"]
                            );
                        }

                        $ordersObj[] = array(
                            "order_no" => $item["order_id"],
                            "date" => "",
                            "status" => "",
                            "lastname" => $item["lastname"],
                            "firstname" => $item["firstname"],
                            "email" => "",
                            "phone" => "",
                            "state" => "",
                            "city" => "",
                            "address" => "",
                            "discount" => "",
                            "discount_code" => "",
                            "shipping" => "",
                            "fees" => 0,
                            "rebates" => 0,
                            "total" => $item["total"],
                            "products" => $productsJson
                        );
                    }

                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($ordersObj, JSON_PRETTY_PRINT));
                    return;

                    break;

                case "products.json":

                    $this->load->model('catalog/product');
                    $products = $this->model_catalog_product->getProducts(array("start" => $start, "limit" => $limit));

                    $productsJson = array();

                    if(!empty($productId))
                    {
                        $products = $this->model_catalog_product->getProduct($productId);
                        $products = array(
                            $products
                        );
                    }

                    foreach ($products as $prod) {

                        $image = "";

                        //price old special becomes price
                        $price = (!empty($prod["special"])) ? $prod["special"] : $prod["price"];
                        //price becomes price old
                        $priceOld = (!empty($prod["special"])) ? $prod["price"] : "";

                        if(!empty($prod["image"]))
                        {
                            $image = explode(".", $prod["image"]);
                            $image = $image[1];  
                            $image = str_replace("." . $image, "-500x500" . '.' . $image, $prod["image"]);    
                            $image = 'https://' . $_SERVER['SERVER_NAME'] . '/image/cache/' . $image;                                
                        }

                        $productsJson[] = array(             
                            "id" => $prod["product_id"],
                            "name" => $prod["name"],
                            "stock_quantity" => $prod["quantity"],
                            "price" => $price,
                            "price_old" => $priceOld,
                            "image_url" => $image,
                            "url" => 'https://' . $_SERVER['SERVER_NAME'] . '/index.php?route=product/product&product_id=' . $prod["product_id"]
                        );
                    }

                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($productsJson, JSON_PRETTY_PRINT));
                    return;

                    break;

                case "customers.json":

                    $wp_cust = $this->getCustomers(array("start" => $start, "limit" => $limit));

                    $custs = array();

                    foreach ($wp_cust as $users) {

                        $custs[] = array(
                            "email" => $users["email"],
                            "firstname" => $users["firstname"],
                            "lastname" => $users["lastname"]
                        );
                    }

                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($custs, JSON_PRETTY_PRINT));
                    return;

                    break;

                case "subscribers.json":

                    $wp_subscribers = $this->getCustomers(array("start" => $start, "limit" => $limit, "filter_newsletter" => 1));
                    $subs = array();

                    foreach ($wp_subscribers as $users) {
                        $subs[] = array(
                            "email" => $users["email"],
                            "firstname" => $users["firstname"],
                            "lastname" =>  $users["lastname"]
                        );
                    }

                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($subs, JSON_PRETTY_PRINT));
                    return;

                    break;
                case "version.json":
                    $version = array(
                    "version" => "Opencart 2.x"
                    );

                    $this->response->addHeader('Content-Type: application/json');
                            $this->response->setOutput(json_encode($version, JSON_PRETTY_PRINT));
                    return;
            
                    break;

            }
        } else {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode("403"));
        }
    }

    public function getCustomers($data = array())
    {
        $sql = "SELECT * from " . DB_PREFIX . "customer";

        if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
            $sql .= " WHERE newsletter = '" . (int)$data['filter_newsletter'] . "'";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }
}
