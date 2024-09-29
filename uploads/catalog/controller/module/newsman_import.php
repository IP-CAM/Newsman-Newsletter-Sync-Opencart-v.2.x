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

            $apikey = (empty($_GET["nzmhash"])) ? "" : $_GET["nzmhash"]; 
            $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
            if (strpos($authorizationHeader, 'Bearer') !== false) {
                $apikey = trim(str_replace('Bearer', '', $authorizationHeader));
            }
            if(empty($apikey))
            {
                $apikey = empty($_POST['nzmhash']) ? '' : $_POST['nzmhash'];
            }            

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
        $apikey = (empty($_GET["nzmhash"])) ? "" : $_GET["nzmhash"];
        if(empty($apikey))
        {
            $apikey = empty($_POST['nzmhash']) ? '' : $_POST['nzmhash'];
        }        
        $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (strpos($authorizationHeader, 'Bearer') !== false) {
            $apikey = trim(str_replace('Bearer', '', $authorizationHeader));
        }
        $newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];
        if(empty($newsman))
        {
            $newsman = empty($_POST['newsman']) ? '' : $_POST['newsman'];
        }        
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

                case "coupons.json":

                    try {
                        $discountType = !isset($this->request->get['type']) ? -1 : (int)$this->request->get['type'];
                        $value = !isset($this->request->get['value']) ? -1 : (int)$this->request->get['value'];
                        $batch_size = !isset($this->request->get['batch_size']) ? 1 : (int)$this->request->get['batch_size'];
                        $prefix = !isset($this->request->get['prefix']) ? "" : $this->request->get['prefix'];
                        $expire_date = isset($this->request->get['expire_date']) ? $this->request->get['expire_date'] : null;
                        $min_amount = !isset($this->request->get['min_amount']) ? -1 : (float)$this->request->get['min_amount'];
                        $currency = isset($this->request->get['currency']) ? $this->request->get['currency'] : "";
                    
                        if ($discountType == -1) {
                            $this->response->setOutput(json_encode(array(
                                "status" => 0,
                                "msg" => "Missing type param"
                            )));
                            return;
                        }
                        if ($value == -1) {
                            $this->response->setOutput(json_encode(array(
                                "status" => 0,
                                "msg" => "Missing value param"
                            )));
                            return;
                        }
                    
                        $couponsList = array();
                    
                        for ($int = 0; $int < $batch_size; $int++) {
                            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                            $coupon_code = '';
                    
                            do {
                                $coupon_code = '';
                                for ($i = 0; $i < 8; $i++) {
                                    $coupon_code .= $characters[rand(0, strlen($characters) - 1)];
                                }
                                $full_coupon_code = $prefix . $coupon_code;
                                $existing_coupon = $this->db->query("SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '" . $this->db->escape($full_coupon_code) . "'");
                            } while ($existing_coupon->num_rows > 0);
                    
                            $coupon_data = array(
                                'name' => 'Generated Coupon ' . $full_coupon_code,
                                'code' => $full_coupon_code,
                                'discount' => $value,
                                'type' => ($discountType == 1) ? 'P' : 'F',
                                'total' => ($min_amount != -1) ? $min_amount : 0,
                                'logged' => 0,
                                'shipping' => 0,
                                'date_start' => date('Y-m-d'),
                                'date_end' => ($expire_date != null) ? date('Y-m-d', strtotime($expire_date)) : '9999-12-31',
                                'uses_total' => 1,
                                'uses_customer' => 1,
                                'status' => 1
                            );
                    
                            $this->db->query("INSERT INTO " . DB_PREFIX . "coupon SET " .
                                "name = '" . $this->db->escape($coupon_data['name']) . "', " .
                                "code = '" . $this->db->escape($coupon_data['code']) . "', " .
                                "discount = '" . (float)$coupon_data['discount'] . "', " .
                                "type = '" . $this->db->escape($coupon_data['type']) . "', " .
                                "total = '" . (float)$coupon_data['total'] . "', " .
                                "logged = '" . (int)$coupon_data['logged'] . "', " .
                                "shipping = '" . (int)$coupon_data['shipping'] . "', " .
                                "date_start = '" . $this->db->escape($coupon_data['date_start']) . "', " .
                                "date_end = '" . $this->db->escape($coupon_data['date_end']) . "', " .
                                "uses_total = '" . (int)$coupon_data['uses_total'] . "', " .
                                "uses_customer = '" . (int)$coupon_data['uses_customer'] . "', " .
                                "status = '" . (int)$coupon_data['status'] . "'");
                    
                            $couponsList[] = $full_coupon_code;
                        }
                    
                        $this->response->setOutput(json_encode(array(
                            "status" => 1,
                            "codes" => $couponsList
                        )));
                    } catch (Exception $exception) {
                        $this->response->setOutput(json_encode(array(
                            "status" => 0,
                            "msg" => $exception->getMessage()
                        )));
                    }                    

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
