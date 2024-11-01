<?php
/*
Plugin Name: Boxberry for WooCommerce
Description: The plugin allows you to automatically calculate the shipping cost and create Parsel for Boxberry
Version: 2.1
Author: Anton Drobyshev
Author URI: https://vk.com/antoshadrobyshev
Text Domain: boxberry
Domain Path: /lang
*/

error_reporting(~E_NOTICE && ~E_STRICT);
add_action( 'plugins_loaded', 'boxberry_load_textdomain' );
function boxberry_load_textdomain() {
    load_plugin_textdomain( 'boxberry', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' );
}
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    require __DIR__ . '/Boxberry/src/autoload.php';
    function boxberry_shipping_method_init() {
        class WC_Boxberry_Parent_Method extends WC_Shipping_Method {

            public function __construct( $instance_id = 0 ) {

                parent::__construct();
                $this->instance_id           = absint( $instance_id );
                $this->supports              = array(
                    'shipping-zones',
                    'instance-settings'
                );

                $params = array(
                    'title' => array(
                        'title' 		=> __( 'Title', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> $this->method_title,
                    ),
                    'key' => array(
                        'title' 		=> __( 'Boxberry API Key', 'boxberry' ),
                        'type' 			=> 'text',
                    ),
                    'api_url' => array(
                        'title' 		=> __( 'Boxberry API Url', 'boxberry' ),
                        'description' 	=> '',
                        'type' 			=> 'text',
                        'default'		=> 'https://api.boxberry.de/json.php',
                    ),
                    'wiidget_url' => array(
                        'title' 		=> __( 'Boxberry Widget Url', 'boxberry' ),
                        'description' 	=> '',
                        'type' 			=> 'text',
                        'default'		=> '//points.boxberry.de/js/boxberry.js',
                    ),
                    'default_weight' => array(
                        'title' 		=> __( 'Default Weight', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '',
                    ),
                    'min_weight' => array(
                        'title' 		=> __( 'Min Weight', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '5',
                    ),
                    'max_weight' => array(
                        'title' 		=> __( 'Max Weight', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '31000',
                    ),
                    'height' => array(
                        'title' 		=> __( 'Height', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '',
                    ),
                    'depth' => array(
                        'title' 		=> __( 'Depth', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '',
                    ),
                    'width' => array(
                        'title' 		=> __( 'Width', 'boxberry' ),
                        'type' 			=> 'text',
                        'default'		=> '',
                    ),
                    'surch' => array(
                        'title' 		=> __( 'surch', 'boxberry' ),
                        'type' 			=> 'select',
                        'options'		=> [
                            1=>'Нет',
                            0=>'Да',
                        ]
                    ),

                );

                if (is_array($this->instance_form_fields)) {
                    $this->instance_form_fields = array_merge($this->instance_form_fields, $params);
                } else {
                    $this->instance_form_fields  = $params;
                }

                $this->key            = $this->get_option( 'key' );
                $this->title   		  = $this->get_option( 'title' );
                $this->from      	  = $this->get_option( 'from' );
                $this->addcost 		  = $this->get_option( 'addcost' );
                $this->api_url 		  = $this->get_option( 'api_url' );
                $this->widget_url 		  = $this->get_option( 'widget_url' );
                $this->err = false;

                if ($_REQUEST['page'] =='wc-settings' && $_REQUEST['tab'] == 'shipping' && !empty($_REQUEST['instance_id']) && !empty($_POST)){

                    foreach ($_POST as $key =>$value){
                        if (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'api_url') !== false)){
                            if ($value=='') {
                                add_action( 'admin_notices',  $this->setNotice($key,'Поле Url для Api должно быть заполнено!'));

                            } else {$api_url = $value;}
                        } elseif (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'wiidget_url') !== false && $value=='')){
                           $this->setNotice($key,'Поле Url для виджета не заполнено!');

                        } elseif (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'key') !== false )){

                            if ($value=='') {
                                $this->setNotice($key,'Поле Api-token не заполнено');
                            } else {
                                $token = $value;
                            }
                        }  elseif (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'default_weight') !== false && $value=='')){
                           $this->setNotice($key,'Не указан вес по-умолчанию!');
                        }  elseif (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'min_weight') !== false && $value=='')){
                           $this->setNotice($key,'Не указан  минимальный вес!');
                        } elseif (strpos($key,'woocommerce_boxberry') !== false && (strpos($key,'max_weight') !== false && $value=='')){
                            $this->setNotice($key,'Не указан максимальный вес!');
                        }else {
                            unset ($_SESSION[$key]);
                            $this->err = false;
                        }
                    };

                    if (!$dataApi = $this->curl_contents($api_url.'?token='.$token.'&method=GetKeyIntegration')){
                       $this->setNotice('wrongApi','Неверный Url для Api');
                    }else {
                        $res = json_decode($dataApi, 2);
                        unset($_SESSION['wrongApi']);
                        if (isset($res[0]['err']) && !empty($res[0]['err'])) {
                            if (strpos('нет доступа',$res[0]['err']) !== false){
                                $this->setNotice('wrongToken','Неверный API token');
                            }elseif (strpos('учетная запись заблокирована',$res[0]['err']) !== false){
                                $this->setNotice('wrongToken','Учетная запись с данным API токеном заблокирована');
                            }elseif (strpos('временно недоступен',$res[0]['err']) !== false){
                                $this->setNotice('wrongToken','Сервис временно недоступен');
                            }
                            $this->setNotice('wrongToken','Неверный API token');
                        }else {
                            unset($_SESSION['wrongToken']);
                        }
                    }
                }

                if ($this->err === false){
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }

            }

            public function setNotice($key,$text,$type = 'error'){

                    if(!isset($_SESSION[$key])) {
                        echo '
                                    <div id="message" class="'.$type.'">
                                        <p>'.$text.'</p>
                                    </div>';
                        $_SESSION[$key] = true;
                        $this->err = true;
                    }
            }

            private function curl_contents($url){
                $timeout=3;
                $ch = curl_init();

                curl_setopt ($ch, CURLOPT_URL, $url);
                curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt ($ch, CURLOPT_LOW_SPEED_TIME, $timeout);
                curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
                $file_contents = curl_exec($ch);

                if ( curl_getinfo($ch,CURLINFO_HTTP_CODE) !== 200 ) {
                    return false;
                } else {
                    return $file_contents;
                }

            }

            final public function calculate_shipping( $package = array() ) {

                if (!is_cart() && !is_checkout() ){
                    return false;
                }

                global $woocommerce;

                $site_url    = get_option('siteurl');
                $site_url    = implode('/', array_slice(explode('/', preg_replace('/https?:\/\/|www./', '', $site_url)), 0, 1));
				$weight = 0;
                $weights = '0';
				$dimensions = true;

                $default_height = (int)$this->get_option( 'height' );
                $default_depth = (int)$this->get_option( 'depth' );
                $default_width = (int)$this->get_option( 'width' );

				$cart_products = $woocommerce->cart->get_cart();

				$current_unit = strtolower( get_option( 'woocommerce_weight_unit' ) );
				$weight_c = 1;
				switch ($current_unit){
					case 'kg':
						$weight_c = 1000;
					break;
				}
				$dimension_c = 1;
				$dimension_unit = strtolower(get_option('woocommerce_dimension_unit'));

				switch($dimension_unit){
                    case 'm':
                        $dimension_c = 100;
                        break;
                    case 'mm':
                        $dimension_c = 0.1;
                        break;
                }

                $countProduct = count($cart_products);
				$currentProduct  = null;

				foreach($cart_products as $cart_product)
				{
				    $product = new WC_Product($cart_product['product_id']);
					$itemWeight = $product->get_weight();
					$itemWeight = $itemWeight * $weight_c;

                    if ($countProduct == 1 && ($cart_product['quantity'] == 1))
                    {
                        $currentProduct = $product;
                    }

					$weight += (!empty($itemWeight) ? (float)$itemWeight
                            : (float)$this->get_option( 'default_weight' ) ) * $cart_product['quantity'];
                    $weights .= !empty($itemWeight) ? ','.((float)$itemWeight * $cart_product['quantity'])
                        : ','.((float)$this->get_option( 'default_weight' ) * $cart_product['quantity']);

                    $height = (float)$product->get_height()*$dimension_c;
                    $depth = (float)$product->get_length()*$dimension_c;
                    $width = (float)$product->get_width()*$dimension_c;
                    $sum_dimensions = $height + $depth + $width;

                    if($sum_dimensions > 250){return false; }

                    if ( ($default_height>0 && $height > $default_height)
                        || ($default_depth>0 && $depth>$default_depth)
                        || ($default_width && $width>$default_width) || $sum_dimensions > 250)
                    {
                        $dimensions = false;
                    }
				}

				if ( (float)$this->get_option( 'min_weight' ) <= $weight
                    && (float)$this->get_option( 'max_weight' )>=$weight && $dimensions)
				{
                    $height = $depth = $width = 0;

                    if (!is_null($currentProduct))
                    {
                        $height = $currentProduct->get_height()*$dimension_c;
                        $depth = $currentProduct->get_length()*$dimension_c;
                        $width = $currentProduct->get_width()*$dimension_c;
                    }

                    $totalval = $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total;

                    $surch = $this->get_option( 'surch')!='' ? (int)$this->get_option( 'surch') : 1;

                    try {
                        $client = new Boxberry\Client\Client();
                        $client->setApiUrl($this->api_url);
                        $client->setKey($this->key);

                        $deliveryCosts = $client->getDeliveryCosts();
                        $deliveryCosts->setWeight($weight);

                        if ($this->self_type )
                        {
                            $listCities = $client->getListCities();
                            $boxberry_cities = $client->execute($listCities);
                            if (is_null($boxberry_cities) || empty($boxberry_cities)){ return false; }

                            $city_code = null;

                            $package['destination']['city'] = str_replace('ё','е',$package['destination']['city']);
                            $fullAddress = false;
                            /** @var \Boxberry\Models\City $city */
                            foreach ($boxberry_cities as $city){
                               if (
                                   (mb_strtoupper($city->getName()) == trim(mb_strtoupper($package['destination']['city']))
                                   || mb_strtoupper($city->getName()) == trim(mb_strtoupper(mb_substr($package['destination']['city'], 0, 1))) . trim(mb_substr($package['destination']['city'], 1)))
                                   &&
                                   (!empty($package['destination']['state'])
                                       && stristr(mb_strtoupper($city->getRegion()), trim(mb_strtoupper($package['destination']['state']))) !== false ))
                               {
                                   $city_code = $city->getCode();
                                   $fullAddress = true;
                                   break;
                               }
                            }

                            if (!$fullAddress){
                                /** @var \Boxberry\Models\City $city */
                                foreach ($boxberry_cities as $city)
                                {
                                    if ($city->getName() == trim(mb_strtoupper($package['destination']['city']))
                                        || $city->getName() == trim(mb_strtoupper(mb_substr($package['destination']['city'], 0, 1))) . trim(mb_substr($package['destination']['city'], 1)))
                                    {
                                        $city_code = $city->getCode();
                                        break;
                                    }
                                }
                            }

                            if ($city_code === null) { return false; }

                            $blockCityModel = $client::getWidgetSettings();
                            $blockCityModel->setType(1);
                            $blockCity = $client->execute($blockCityModel);
                            $blockCityCodes = $blockCity->getResult()[1]['CityCode'];

                            if (in_array($city_code,$blockCityCodes)){ return false; }

                           $listPoints = $client->getListPoints();

                            if ($this->id == 'boxberry_self_after') {
                                $listPoints->setPrepaid(0);
                            }elseif ($this->id == 'boxberry_self'){
                                $listPoints->setPrepaid(1);
                            }

                            $listPoints->setCityCode($city_code);

                            $listPointsCollection = $client->execute($listPoints);

                            if (is_null($listPointsCollection)
                                || empty($listPointsCollection)
                                || !isset($listPointsCollection[0]))
                            {
                                    return false;
                            }
                            /** @var \Boxberry\Collections\Collection $listPointsCollection*/
                            $deliveryCosts->setTarget($listPointsCollection[0]->getCode());

                            unset($city_code, $listCities, $listPointsCollection);

                        } else {
                            $zipcheck = $client->getZipCheck();
                            $zipcheck->setZip($package['destination']['postcode']);

                            $responseObject = $client->execute($zipcheck);
                            if(is_null($responseObject) || empty($responseObject)){ return false; }

                            $expressDelivery = $responseObject->getExpressDelivery();
                            if (is_null($expressDelivery) || $expressDelivery == 0){ return false; }

                            $deliveryCosts->setZip($package['destination']['postcode']);
                        }

                        if ($this->payment_after)
                        {
                            $deliveryCosts->setPaysum($totalval);
                        } else {
                            $deliveryCosts->setPaysum(0);
                        }

                        $deliveryCosts->setsucrh($surch);
                        $deliveryCosts->setOrdersum($totalval);
                        $deliveryCosts->setHeight($height);
                        $deliveryCosts->setWidth($width);
                        $deliveryCosts->setDepth($depth);
                        $deliveryCosts->setCms('wordpress');
                        $deliveryCosts->setVersion('2.0');
                        $deliveryCosts->setUrl($site_url);

                        /** @var \Boxberry\Models\DeliveryCosts $responseObject */
                        $responseObject = $client->execute($deliveryCosts);
                        if (is_null($responseObject) || empty($responseObject)){ return false; }

                        $costReceived = $responseObject->getPrice();

                        $deliveryPeriod = $responseObject->getDeliveryPeriod();

                        $widgetSettingModel = $client::getWidgetSettings();
                        $widgetSettingModel->setType(3);
                        $widgetSetting = $client->execute($widgetSettingModel);

                        if (is_null($widgetSetting) || empty($widgetSetting)){ return false; }

                        if ( $widgetSetting->getHide_delivery_day() == '0')
                        {
                           $days = (int)$deliveryPeriod;
                           if (get_bloginfo('language')== 'ru-RU')
                           {
                             $deliveryDays = ' ('.(int)$days.' '.trim($client->setDayForPeriod($days,'рабочий день','рабочих дня','рабочих дней')).') ';
                           } else {
                             $deliveryDays = ' ('.(int)$days.' '.trim($client->setDayForPeriod($days,'day','days','days')).')';
                           }
                        } else { $deliveryDays = ''; }

                        $this->add_rate( array(
                            'id'    => $this->get_rate_id(),
                            'label' => $this->title.$deliveryDays,
                            'cost'  => (float)$this->addcost + (float)$costReceived,
                        ) );
                    } catch (\Exception $e) {
                        add_filter( "woocommerce_cart_no_shipping_available_html", function() use($e) {return $e->getMessage();} );
                    }
                }
                return false;
            }
        }

        class WC_Boxberry_Self_Method extends WC_Boxberry_Parent_Method {

            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_self';
                $this->method_title          = __( 'Boxberry Self', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = true;
                $this->payment_after = false;
                parent::__construct( $instance_id );
                $this->default_weight = $this->get_option( 'default_weight' );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_SelfAfter_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_self_after';
                $this->method_title          = __( 'Boxberry Self Payment After', 'boxberry' );
                $this->instance_form_fields = array(

                );
                $this->self_type = true;
                $this->payment_after = true;
                parent::__construct( $instance_id );
                $this->default_weight   		  = $this->get_option( 'default_weight' );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_Courier_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_courier';
                $this->method_title          = __( 'Boxberry Courier', 'boxberry' );
				 $this->instance_form_fields = array(

                );
                $this->self_type = false;
                $this->payment_after = false;
                parent::__construct( $instance_id );
                $this->key            = $this->get_option( 'key' );
            }
        }
        class WC_Boxberry_CourierAfter_Method extends WC_Boxberry_Parent_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                    = 'boxberry_courier_after';
                $this->method_title          = __( 'Boxberry Courier Payment After', 'boxberry' );
				 $this->instance_form_fields = array(

                );
                $this->self_type = false;
                $this->payment_after = true;
                parent::__construct( $instance_id );
                $this->key            = $this->get_option( 'key' );
            }
        }
    }

    add_action( 'woocommerce_shipping_init', 'boxberry_shipping_method_init' );

    function boxberry_shipping_method( $methods ) {
        $methods['boxberry_self'] = 'WC_Boxberry_Self_Method';
        $methods['boxberry_courier'] = 'WC_Boxberry_Courier_Method';
        $methods['boxberry_self_after'] = 'WC_Boxberry_SelfAfter_Method';
        $methods['boxberry_courier_after'] = 'WC_Boxberry_CourierAfter_Method';
        return $methods;
    }

    add_filter( 'woocommerce_shipping_methods', 'boxberry_shipping_method' );

    function boxberry_add_meta_tracking_code_box() {
        global $post;
		if (strpos($post->post_status,'wc')!==false){
			$order = new WC_Order( $post->ID );
            $arr = $order->get_shipping_methods();
			$shipping_method = array_shift($arr);
			$shipping_method_name = preg_replace('/\d+/', '',  $shipping_method['method_id']);
            $shipping_method_name = str_replace(':', '', $shipping_method_name );

			if (substr($shipping_method_name, 0, 8) != 'boxberry') {
				return;
			}
			$shipping = WC_Shipping::instance();

			$shipping->load_shipping_methods();
			$shipping_methods = $shipping->get_shipping_methods();
			$shipping_methods_object = $shipping_methods[$shipping_method_name];
			$shipping_methods_object->instance_id = str_replace($shipping_method_name.':', '', $shipping_method['method_id'] );
			add_meta_box( 'boxberry_meta_tracking_code', __( $shipping_methods_object->method_title, 'boxberry' ), 'boxberry_tracking_code', 'shop_order', 'side', 'default' );
		}
    }
    add_action( 'add_meta_boxes', 'boxberry_add_meta_tracking_code_box' );

    function boxberry_tracking_code() {
        global $post;

        $order = new WC_Order( $post->ID );

        $arr = $order->get_shipping_methods();
        $shipping_method = array_shift($arr);
        $shipping_method_name = preg_replace('/\d+/', '',  $shipping_method['method_id']);
        $shipping_method_name = str_replace(':', '', $shipping_method_name );

        $shipping = WC_Shipping::instance();

        $shipping->load_shipping_methods();
		$shipping_methods = $shipping->get_shipping_methods();
        $shipping_methods_object = $shipping_methods[$shipping_method_name];
        $shipping_methods_object->instance_id = str_replace($shipping_method_name.':', '', $shipping_method['method_id'] );

        if (is_null($shipping_methods_object->instance_id) || $shipping_methods_object->instance_id == $shipping_method_name){
            global $wpdb;
            $raw_methods_sql = "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s";
            $result = $wpdb->get_results( $wpdb->prepare( $raw_methods_sql, $shipping_method['method_id']) ) ;
            $shipping_methods_object->instance_id = $result[0]->instance_id;
        }

        $boxberry_tracking_number = get_post_meta($post->ID, 'boxberry_tracking_number', true);
        $boxberry_link = get_post_meta($post->ID, 'boxberry_link', true);
        $boxberry_error = get_post_meta($post->ID, 'boxberry_error', true);

        $boxberry_code = get_post_meta($post->ID, 'boxberry_code', true);
        $boxberry_address = get_post_meta($post->ID, 'boxberry_address', true);
		$key = $shipping_methods_object->get_option('key');
		$api_url = $shipping_methods_object->get_option('api_url');

		$client = new \Boxberry\Client\Client();
		$client->setApiUrl($api_url);
		$client->setKey($key);

		$widgetKeyMethod = new \Boxberry\Requests\GetKeyIntegrationRequest();
		$widgetKeyMethod->setToken($key);
		$widgetResponse = $client->execute($widgetKeyMethod);
		$widget_key = $widgetResponse->getWidgetKey();


        if (isset($boxberry_error) && strlen($boxberry_error)) {
            echo '<p>Возникла ошибка: ' . $boxberry_error . '</p>';

            echo '<p><input type="submit" class="add_note button" name="boxberry_create_parsel" value="Попробовать снова"></p>';

            if ($shipping_methods_object->self_type) {

                echo '<p>Код пункта выдачи: <a href="#" data-id="' . $post->ID . '" data-boxberry-open="true" data-boxberry-city="' . $order->shipping_city . '" data-boxberry-token="' . $widget_key . '">' . $boxberry_code .'</a></p>';
                echo '<p>Адрес пункта выдачи: ' . $boxberry_address .'</p>';

                 }
            } elseif(isset($boxberry_tracking_number) && strlen($boxberry_tracking_number)) {
            echo '<p><span style="display: inline-block;">Номер отправления:</span>';
            echo '<span style="margin-left: 10px">' . $boxberry_tracking_number . '</span>';
            echo '<p><a href="' . $boxberry_link .'" target="_blank">Ссылка на этикетку</a></p>';
        } else {
            if ($shipping_methods_object->self_type) {
                 if (!strlen($boxberry_code)) {
                    echo '<p><a href="#" data-id="' . $post->ID . '" data-boxberry-open="true" data-boxberry-city="' . $order->shipping_state. ' '  . $order->shipping_city . '" data-boxberry-token="' . $widget_key . '">Выберите ПВЗ</a></p>';
                    return;
                } else {
                    echo '<p>Код пункта выдачи: <a href="#" data-id="' . $post->ID . '" data-boxberry-open="true" data-boxberry-city="' . $order->shipping_city . '" data-boxberry-token="' . $widget_key . '">' . $boxberry_code .'</a></p>';
                    echo '<p>Адрес пункта выдачи: ' . $boxberry_address .'</p>';
                }
            }
            echo '<p>После нажатия кнопки заказ будет создан в системе Boxberry.</p>';
            echo '<p><input type="submit" class="add_note button" name="boxberry_create_parsel" value="Отправить заказ в систему"></p>';
        }

    }
    add_action('woocommerce_process_shop_order_meta', 'boxberry_save_tracking_code', 0, 2);

    function boxberry_save_tracking_code($post_id) {
		global $post;
		if ( !isset($_POST['boxberry_create_parsel']))
        {
            return;
        }

        $order = new WC_Order( $post_id );

        $shipping_method = array_shift($order->get_shipping_methods());
        $shipping_method_name = preg_replace('/\d+/', '',  $shipping_method['method_id']);
        $shipping_method_name = str_replace(':', '', $shipping_method_name );
        $shipping = WC_Shipping::instance();

        $shipping->load_shipping_methods();
		$shipping_methods = $shipping->get_shipping_methods();
        $shipping_methods_object = $shipping_methods[$shipping_method_name];
        $shipping_methods_object->instance_id = str_replace($shipping_method_name.':', '',$shipping_method['method_id'] );
        if (is_null($shipping_methods_object->instance_id) || $shipping_methods_object->instance_id == $shipping_method_name){
            global $wpdb;
            $raw_methods_sql = "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s";
            $result = $wpdb->get_results( $wpdb->prepare( $raw_methods_sql, $shipping_method['method_id']) ) ;
            $shipping_methods_object->instance_id = $result[0]->instance_id;
        }
        $client = new \Boxberry\Client\Client();
        $client->setApiUrl($shipping_methods_object->get_option('api_url'));
        $client->setKey($shipping_methods_object->get_option('key'));
        /** @var \Boxberry\Requests\ParselCreateRequest $parselCreate */
        $parselCreate = $client->getParselCreate();

        $parsel = new  \Boxberry\Models\Parsel();
        $parsel->setOrderId('wp_'.$order->get_order_number());

        //$total_price = $order->get_total() - $shipping_method['cost'];
        $total_price = $order->get_total();
        $parsel->setPrice($total_price - $shipping_method['cost']);
        $parsel->setDeliverySum($shipping_method['cost']);

		if (strpos($shipping_method_name , '_after') === false) {
            $parsel->setPaymentSum(0);
		} else {
            $parsel->setPaymentSum($total_price);
		}



		$customer_name = $order->get_formatted_shipping_full_name();
		$address = $order->shipping_state . ', ' . $order->shipping_city . ', ' . $order->shipping_address_1 . ', ' . $order->shipping_address_2;
		$customer_phone = $order->shipping_phone;
		$customer_email = $order->shipping_email;

		if (trim($customer_name) == ''){
            $customer_name = $order->get_formatted_billing_full_name();
        }
        if (trim(str_replace(',','',$address)) == ''){
		    $address = $order->billing_state . ', ' . $order->billing_city . ', ' . $order->billing_address_1 . ', ' . $order->billing_address_2;
        }
        if (trim($customer_phone) == '') {
		    $customer_phone = $order->billing_phone;
        }
        if (trim($customer_email) == '') {
            $customer_email = $order->billing_email;
        }

        $customer = new \Boxberry\Models\Customer();
        $customer->setFio($customer_name);
        $customer->setEmail($customer_email);
        $customer->setPhone($customer_phone);
        $customer->setAddress($address);
        $parsel->setCustomer($customer);

        $items = new \Boxberry\Collections\Items();
        $order_items =  $order->get_items();
        foreach ($order_items as $key => $orderItem)
        {
            $current_unit = strtolower( get_option( 'woocommerce_weight_unit' ) );
			$weight_c = 1;
           ;
			switch ($current_unit){
				case 'kg':
					$weight_c = 1000;
				break;
			}
			$product = new WC_Product($orderItem['product_id']);
            $itemWeight = $product->get_weight();
            $itemWeight = intval($itemWeight * $weight_c * $order_items[$key]["qty"]);

            if ($itemWeight === 0) {
                $itemWeight = $shipping_methods_object->get_option('default_weight') * $order_items[$key]["qty"];
            }

            $item = new \Boxberry\Models\Item();
			if (isset($product->sku) && !empty($product->sku)){
				$item->setId($product->sku);
			}else{
				$item->setId($orderItem['product_id']);
			}
            $item->setName($orderItem['name']);
           // $item->setPrice($product->get_price());
            $item->setPrice((float)$orderItem['total']/$orderItem['qty']);
            $item->setQuantity($orderItem['qty']);
            $item->setWeight($itemWeight);
            $items[] = $item;
			unset($product);
        }


        $parsel->setItems($items);
		$shop = array(
            'name' => '',
            'name1' => ''
        );

        if ($shipping_method_name == 'boxberry_self' ||$shipping_method_name == 'boxberry_self_after') {
            $parsel->setVid(1);
            $boxberry_code = get_post_meta($post->ID, 'boxberry_code', true);
			$boxberry_address = get_post_meta($post->ID, 'boxberry_address', true);
            if ( strlen($boxberry_code) === 0) {
                update_post_meta($post_id, 'boxberry_error', 'Для доставки до пункта ПВЗ нужно указать его код');
                return;
            }

            $shop['name'] = $boxberry_code;
            $shop['name1'] = $shipping_methods_object->get_option('from');
        } else {
            $postCode = $order->get_shipping_postcode();
            if (is_null($postCode) || trim((string)$postCode) == ''){
                $postCode = $order->get_billing_postcode();
            }
            $shippingCity = $order->get_shipping_city();
            if (is_null($shippingCity) || trim((string)$shippingCity) == ''){
                $shippingCity = $order->get_billing_city();
            }
            $shippingAddress = $order->get_shipping_address_1() . ', ' . $order->get_shipping_address_2();
            if (trim(str_replace(',','',$shippingAddress)) == ''){
                $shippingAddress = $order->get_billing_address_1() . ', ' . $order->get_billing_address_2();
            }

            $parsel->setVid(2);
            $courierDost = new \Boxberry\Models\CourierDelivery();
	        $courierDost->setIndex($postCode);
            $courierDost->setCity($shippingCity);
            $courierDost->setAddressp($shippingAddress);
            $parsel->setCourierDelivery($courierDost);
        }
        $parsel->setShop($shop);
        $parselCreate->setParsel($parsel);
		try {
            /** @var \Boxberry\Client\ParselCreateResponse $answer */
            $answer = $client->execute($parselCreate);
            if (strlen($answer->getTrack())) {
                $imIdsList = new \Boxberry\Collections\ImgIdsCollection(array(
                    $answer->getTrack()
                ));

                /*$parselSend = $client->getParselSend();
                $parselSend->setImgIdsList($imIdsList);

                $client->execute($parselSend);*/

                update_post_meta($post_id, 'boxberry_tracking_number', $answer->getTrack());
                update_post_meta($post_id, 'boxberry_link', $answer->getLabel());
                update_post_meta($post_id, 'boxberry_error', '');
            }
        } catch (Exception $e) {

            update_post_meta($post_id, 'boxberry_error', $e->getMessage());
        }
    }


    function action_woocommerce_checkout_order_review( $method ) {

        global $woocommerce;

        $shipping_method_name = preg_replace('/\d+/', '',  $method->id);
        $shipping_method_name = str_replace(':', '', $shipping_method_name );
        $shipping = WC_Shipping::instance();
        $shipping->load_shipping_methods();
        $shipping_methods = $shipping->get_shipping_methods();

        $shipping_methods_object = $shipping_methods[$shipping_method_name];

        if (substr($method->method_id, 0, 13) == 'boxberry_self' && is_checkout()){
            $shipping_methods_object->instance_id = str_replace($shipping_method_name.':', '', $method->id );

			$key = $shipping_methods_object->get_option('key');
			$api_url = $shipping_methods_object->get_option('api_url');


			$client = new \Boxberry\Client\Client();
			$client->setApiUrl($api_url);
			$client->setKey($key);
			$widgetKeyMethod = $client->getKeyIntegration();
			$widgetKeyMethod->setToken($key);
			try{
                $widgetResponse = $client->execute($widgetKeyMethod);
                if (is_null($widgetResponse) || empty($widgetResponse)){

                    return false;
                }
            }catch(Exception $ex){
			    return false;
            }

			$widget_key = $widgetResponse->getWidgetKey();

			$billing_city = WC()->customer->get_city();
			$shipping_city = WC()->customer->get_shipping_city();

			if(!empty($shipping_city)){
				$city = $shipping_city;
			}elseif(!empty($billing_city)){
				$city = $billing_city;
			}

            if(isset($_COOKIE['bxb_point_address']) && isset($_COOKIE['bxb_point_city']) && $_COOKIE['bxb_point_address'] != '' && $_COOKIE['bxb_point_city'] != ''){
			    $link_title = $_COOKIE['bxb_point_city'] . ' ('.$_COOKIE['bxb_point_address'].')';
			    $city = $_COOKIE['bxb_point_address'];
            } else {
			    $link_title = 'Выберите пункт выдачи';
            }

			$state = WC()->customer->get_shipping_state();

			$weight = 0;
            $weights = '0';
			$current_unit = strtolower( get_option( 'woocommerce_weight_unit' ) );
			$weight_c = 1;

			switch ($current_unit){
				case 'kg':
					$weight_c = 1000;
				break;
			}
            $dimension_c = 1;
            $dimension_unit = strtolower(get_option('woocommerce_dimension_unit'));

            switch($dimension_unit){
                case 'm':
                    $dimension_c = 100;
                    break;
                case 'mm':
                    $dimension_c = 0.1;
                    break;
            }
			$cart_products = $woocommerce->cart->get_cart();
            $countProduct = count($cart_products);

            $height = 0;
            $depth = 0;
            $width = 0;

            foreach($cart_products as $cart_product){
                $product = new WC_Product($cart_product['product_id']);
                $itemWeight = (float)$product->get_weight();
                $itemWeight = $itemWeight * $weight_c;

                if ($countProduct == 1 && ($cart_product['quantity'] == 1)) {
                    $height = (float)$product->get_height()*$dimension_c;
                    $depth = (float)$product->get_length()*$dimension_c;
                    $width = (float)$product->get_width()*$dimension_c;
                }

                $weight += (!empty($itemWeight) ? $itemWeight : (float)$shipping_methods_object->get_option( 'default_weight' ) ) * $cart_product['quantity'];
                $weights .= !empty($itemWeight) ? ','.($itemWeight * $cart_product['quantity']) : ','.((float)$shipping_methods_object->get_option( 'default_weight' ) * $cart_product['quantity']);
            }

            global $wpdb;

                $display = 'display:none;';
                $raw_methods_sql = "SELECT count(zone_id) as count_active FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = 1";
                $result = $wpdb->get_results( $wpdb->prepare( $raw_methods_sql,[])) ;

                if ($result[0]->count_active == '1'){
                    $display ='';
                }

            $totalval = $woocommerce->cart->cart_contents_total+$woocommerce->cart->tax_total;

                $surch = $shipping_methods_object->get_option( 'surch')!='' ? (int)$shipping_methods_object->get_option( 'surch') : 1;

            if ($shipping_methods_object->id == 'boxberry_self_after') {
                $payment = $totalval;
            }
            if ($shipping_methods_object->id == 'boxberry_self'){
                $payment = 0;
            }

            if (strpos('boxberry',$shipping_methods_object->id)>=0){
                $script = ' <script type="text/javascript">
                      var inputs = jQuery(\'.shipping_method\');
                      for(var ji = 0; ji < inputs.length; ji++){
                           if( inputs[ji].checked === true  && 
                           ( inputs[ji].value == \'boxberry_self:'.$shipping_methods_object->instance_id.'\' || inputs[ji].value == \'boxberry_self_after:'.$shipping_methods_object->instance_id.'\' 
                           ) || (( inputs[ji].value == \'boxberry_self:'.$shipping_methods_object->instance_id.'\' || inputs[ji].value == \'boxberry_self_after:'.$shipping_methods_object->instance_id.'\' 
                           ) && inputs[ji].type == \'hidden\') 
                           )  {
                             
                           if(document.getElementById(inputs[ji].value).style.display == \'none\'){                       
                              document.getElementById(inputs[ji].value).style.display = \'\';
                              document.getElementById(inputs[ji].value).style.color = \'inherit\';
                            }
                            
                          }   
                      }
                  </script>';
            } else {
                $script = '';
            }

            echo '                
                <p style="margin: 4px 0 8px 15px;"><a id="'.$shipping_method_name.':'.$shipping_methods_object->instance_id.'" href="#"
                   style="'.$display.'"
                   data-surch = '.$surch.'
                   data-boxberry-open="true"
                   data-method="' . $method->method_id . '"
                   data-boxberry-token="' . $widget_key . '"
                   data-boxberry-city="' . $state. ' ' . $city . '"
                   data-boxberry-weight="[' . $weights . ']"
                   data-paymentsum="' . $payment . '"
                   data-ordersum="' . $totalval . '"
                   data-height="' . $height . '"
                   data-width="' . $width . '"
                   data-depth="' . $depth . '"
                   data-api-url="'.$api_url.'"
                   
                >'.$link_title.'</a></p>
               
                '.$script;
        }
    }

    add_action( 'woocommerce_after_shipping_rate', 'action_woocommerce_checkout_order_review' );

    function boxberry_script_handle()
   {
       return;
   }

 function my_admin_enqueue($hook)
   {
       $widget_url = get_option('wiidget_url');
       if (strpos($widget_url,'http://') !== false){
           $protocol = 'http://';
           $widget_url = str_replace('http://','',$widget_url);
           wp_register_script( 'boxberry_points', 'http://'.$widget_url);
       }else if (strpos($widget_url,'https://') !== false){
           $protocol = 'https://';
           $widget_url = str_replace('https://','',$widget_url);
           wp_register_script( 'boxberry_points', 'https://'.$widget_url);
       }else {
           wp_register_script( 'boxberry_points', 'https://points.boxberry.de/js/boxberry.js');
       }
       wp_enqueue_script( 'boxberry_points' );

       wp_enqueue_script( 'boxberry_script_handle', plugin_dir_url( __FILE__ ) . ( 'js/boxberry_admin.js' ), array("jquery") );
   }

   function my_enqueue($hook)
   {
       if (is_cart() || is_checkout()) {
           $widget_url = get_option('wiidget_url');
           if (strpos($widget_url, 'http://') !== false) {
               $protocol = 'http://';
               $widget_url = str_replace('http://', '', $widget_url);
               wp_register_script('boxberry_points', 'http://' . $widget_url);
           } else {
               if (strpos($widget_url, 'https://') !== false) {
                   $protocol = 'https://';
                   $widget_url = str_replace('https://', '', $widget_url);
                   wp_register_script('boxberry_points', 'https://' . $widget_url);
               } else {
                   wp_register_script('boxberry_points', 'https://points.boxberry.de/js/boxberry.js');
               }
           }
           wp_enqueue_script('boxberry_points');

           wp_enqueue_script('boxberry_script_handle', plugin_dir_url(__FILE__) . ('js/boxberry.js'), array("jquery"));
       }
   }
   add_action( 'wp_enqueue_scripts', 'my_enqueue' );
   add_action( 'admin_enqueue_scripts', 'my_admin_enqueue' );

    function boxberry_put_choice_code ( $order_id ) 
	{
        $shipping_method = array_shift($_POST['shipping_method']);
        $shipping_method_name = preg_replace('/\d+/', '',  $shipping_method);
        $array = get_user_meta( get_current_user_id(), '_boxberry_array', true );
		
        if (isset($_COOKIE['bxb_code']) && isset($_COOKIE['bxb_address'])) {
			update_post_meta($order_id, 'boxberry_code', $_COOKIE['bxb_code']);
            update_post_meta($order_id, 'boxberry_address', $_COOKIE['bxb_address']);
        }
        update_user_meta( get_current_user_id(), '_boxberry_array', array());
    }
	
    add_action('woocommerce_new_order', 'boxberry_put_choice_code');
	add_action('wp_ajax_boxberry_update', 'boxberry_update_callback');
    add_action('wp_ajax_nopriv_boxberry_update', 'boxberry_update_callback');
    add_action('wp_ajax_boxberry_admin_update', 'boxberry_admin_update_callback');

    function boxberry_update_callback()
    {
        setcookie("bxb_code",$_POST['code'],0,'/');
        setcookie("bxb_address",$_POST['address'],0,'/');
	}
    function boxberry_admin_update_callback ()
    {
        update_post_meta($_POST['id'], 'boxberry_code', $_POST['code']);
        update_post_meta($_POST['id'], 'boxberry_address', $_POST['address']);
    }

    function js_variables()
	{
        $variables = array (
            'ajax_url' => admin_url('admin-ajax.php')
        );
        echo '<script type="text/javascript">';
        echo 'window.wp_data = ';
        echo json_encode($variables);
        echo ';</script>';
    }
    add_action('wp_head', 'js_variables');

    function admin_js_variables()
	{
        $variables = array (
            'ajax_url' => admin_url('admin-ajax.php')
        );
        echo '<script type="text/javascript">';
        echo 'window.wp_data = ';
        echo json_encode($variables);
        echo ';</script>';
    }
    add_action('admin_head', 'js_variables');
}