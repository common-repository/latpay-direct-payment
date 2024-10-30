<?php
/*
 * Plugin Name: Latpay Direct Payment
 * Plugin URI: https://wordpress.org/plugins/latpay-direct-payment/
 * Description: Plugin to integrate with Latpay Direct Payment which based on embedded form model.
 * Author: Latpay Team
 * Author URI: https://www.latpay.com/
 * Version: 1.0.3
 * Tested up to: 6.5
 * WC requires at least: 3.0.0
 * WC tested up to: 8.9
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */





/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
defined( 'ABSPATH' ) or die( 'No file found' );
header('Access-Control-Allow-Origin: *');
add_action('plugins_loaded', 'woocommerce_checkout_gateway_latpay_init', 0);
add_action('before_woocommerce_init', function(){

  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );

  }

});
function woocommerce_checkout_gateway_latpay_init() {
  if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
  class WC_HPS_Checkout_Gateway_Latpay extends WC_Payment_Gateway {

    public function __construct(){
      $this->id = 'latpay_checkout';
      $this->method_title = 'Latpay Direct Payment';
      $this->method_description   = 'Make payment using credit/debit card';
      $this->has_fields = true;
            // Method with all the options fields
      $this -> init_form_fields();
            // Load the settings.
      $this->saved_cards = 'yes' === $this->get_option( 'saved_cards' );
      $this -> init_settings();
      $this -> title = $this -> settings['title'];
      $this -> Merchant_User_Id = $this -> settings['Merchant_User_Id'];
      $this -> publickey = $this -> settings['publickey'];
      $this -> datakey = $this -> settings['datakey'];     
      $this -> description = $this -> settings['description'];
      $this ->icon = apply_filters( 'woocommerce_simplepay_icon', plugins_url( '/images/cards.png' , __FILE__ ) );
      $this->supports = array( 
       'products', 
       'tokenization',
       'subscriptions',
     ); 



      add_action( 'wp_loaded', 'register_all_scripts' );

      if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
          add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
        } else {
          add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
        }

        wp_enqueue_script( 'latpay_own', 'https://lateralpayments.com/checkout/Scripts/Latpay2.js' , array('jquery'),  false );



   }//Constructor Ends
   function init_form_fields(){

    $this -> form_fields = array(


      'title' => array(
        'title' => __('Title:', 'latpay_checkout'),
        'type'=> 'text',
        'description' => __(' ', 'latpay_checkout'),
        'default' => __('LatPay', 'latpay_checkout')),
      
      'description' => array(
        'title' => __('Description:', 'latpay'),
        'type' => 'textarea',
        'description' => __('Additional text which will be displayed in checkout for Latpay (for e.g. Pay by Card)', 'latpay_checkout'),
        'default' => __('Pay by Card (debit / credit)', 'latpay')),

      'enabled' => array(
        'title' => __('Enabled', 'latpay_checkout'),
        'type' => 'checkbox',
        'label' => __('Enable Latpay Direct Payment.', 'latpay_checkout'),
        'default' => 'no'),

      'Merchant_User_Id' => array(
        'title' => __('Merchant ID', 'latpay_checkout'),
        'type' => 'text',
        'description' => __('As provided by Latpay')),

      'publickey' => array(
        'title' => __('Public Key', 'latpay_checkout'),
        'type' => 'text',
        'description' => __('As provided by Latpay')),

      'datakey' => array(
        'title' => __('Data Key', 'latpay_checkout'),
        'type' => 'text',
        'description' => __('As provided by Latpay')),

      'debug' => array(
        'title' => __('Debug Log', 'latpay_checkout'),
        'type' => 'checkbox',
        'description' => __('This is ony for debugging purpose'),
        'default' => 'no'),

      'oh-hold-email' => array(
        'title' => __('Send email', 'latpay_checkout'),
        'type' => 'checkbox',
        'description' => __('This sends mail to admin if order is in ON-HOLD state.'),
        'default' => 'no'),

    );
  }

  public function admin_options(){
    echo '<h3>'.__('Latpay Direct Payment Gateway', 'HPSNEW').'</h3>';
    echo '<p>'.__('HPS is most popular payment gateway for online shopping').'</p>';
    echo '<table class="form-table">';
    $this -> generate_settings_html();
    echo '</table>';
  }

  public function payment_fields() {
    ob_start(); 
    echo $this -> fieldset_section();
    echo  $this -> generate_latpay_form();
    ob_end_flush();
  }

  public function fieldset_section(){
    if(is_user_logged_in()){
      $this->tokenization_script();
      $this->saved_payment_methods();
    }
    ?>
    <style>
    div.loader_img{
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      position: fixed;
      z-index: 9;
      background: #ffffffc4;
      top: 10px;
      visibility: hidden;
    }
  </style>
  <div class="latpay-cardcvc-fieldset" style="background:transparent;">
    <div id="payment_mode"></div>
    <div class="form-row form-row-wide">
      <label for="latpay-card-element">Card Code (CVC)<span class="required">*</span></label>
      <div class="latpay-card-group">
        <div id="latpay-cardcvc-element" class="wc-latpay-elements-field">
          <input type="password" maxlength="3" name="cardtoken_cvc" id="customer_cardtoken_cc_cvc" placeholder="cvc" value="" style="width: 100%;padding: 10px;">
        </div>
      </div>
      <div id="token-error" role="alert"></div>
    </div>
  </div>
  <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">

   <?php do_action( 'woocommerce_credit_card_form_start', $this->id );
   global $woocommerce;   
   $cart = $woocommerce->cart;
   $merchantid = $this->settings['Merchant_User_Id'];
   $publickey = $this->settings['publickey'];
   $cart_total =   WC()->cart->total;
   $currency = get_woocommerce_currency();
   $reference = sha1($cart_total);
   $description = sha1($cart_total);
   echo ' <div class="latpay-container" id="latpay-pay-data" 
   data-merchantid = "' . esc_attr($merchantid) . '"
   data-publickey = "' . esc_attr($publickey) . '"
   data-currency = "' . esc_attr($currency) . '"
   data-amount = "' . esc_attr($cart_total) . '"   
   >';
   ?>

   <div id="latpay-element"></div>
   <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); 
   echo '</div>';
   ?>
 </fieldset>
 <?php
 if(is_user_logged_in()){
  $this->save_payment_method_checkbox();   
}
}

public function generate_latpay_form(){

  global $woocommerce;   
  $cart = $woocommerce->cart;
  $cart_total =   WC()->cart->cart_contents_total;         
  $reference = "Test";

  $inputfiled = '<script type="text/javascript"> 
  var divelements = document.getElementById("latpay-pay-data")
  var merchantid = divelements.getAttribute("data-merchantid");
  var publickey = divelements.getAttribute("data-publickey");
  var amount = divelements.getAttribute("data-amount");
  var currency = divelements.getAttribute("data-currency");

  LatpayCheckout.open({
    "merchantuserid":merchantid,
    "publickey": publickey,
    "status" : function (status) {
      if(status == "failed"){

      }
    }
    });


    (function($) {
      var payMethod;
      payMethod = $(`input[name="payment_method"]:checked`). val();

      $(`input[name="payment_method"]`).change(function(){
        payMethod = $(`input[name="payment_method"]:checked`). val();
        });
        function create_UUID(){
          var dt = new Date().getTime();
          var uuid = "xxxxxxxxxxxx4xxxyxxxxxxxxxxxxxxx".replace(/[xy]/g, function(c) {
            var r = (dt + Math.random()*16)%16 | 0;
            dt = Math.floor(dt/16);
            return (c=="x" ? r :(r&0x3|0x8)).toString(16);
            });
            return uuid;
          }
          var billing_obj = {};
          $("#place_order").on("click", function (e) {
            $("#TransToken").remove();
            $("#amount").remove();
            $("#description").remove();
            $("#reference").remove();
            $("#currency").remove();
            $("#status").remove();
            var divelementsbypamnet = document.getElementById("payment_mode")
            var paytype = divelementsbypamnet.getAttribute("data-payment-mode");
            var reference = create_UUID();
            var description = reference;

            if (payMethod == "latpay_checkout") {
              if(paytype != "token")
              {

                var checkoutFormValid = false;


                $("p.validate-required input, p.validate-required select").each(function() {
                 if($(this).attr("name") ==  "account_password"){
                  if ($("#createaccount").length){     
                    if($("#createaccount").prop("checked") == true){
                     if ($("#account_password").length && $("#account_password").val() == ""){

                       return false;
                     }
                   }
                 }
               }
               else if($(this).val() != "" ){

                var bill_key = $(this).attr("name");
                var bill_val = $(this).val();
                Object.defineProperty(billing_obj, bill_key, {
                  value: bill_val,
                  writable: false
                  })

                  checkoutFormValid = true;
                }
                else{
                  checkoutFormValid = false;
                  return false;
                }
                });

                if(checkoutFormValid){

                  LatpayCheckout.processpayment({
                    "amount": amount,
                    "currency": currency,
                    "reference": reference,
                    "description": description,
                    "firstname":  billing_obj.billing_first_name,
                    "lastname":  billing_obj.billing_last_name,
                    "email":  billing_obj.billing_email,
                    "status" : function (status) {
                      if(status == "success"){                      
                        return true;       
                      }
                      else{
                        e.preventDefault();
                      }

                    }
                    });
                  }    
                }
                else{
                  var token_cvc = $("#customer_cardtoken_cc_cvc").val();
                  var err = false;

                  if (token_cvc == "") {
                    err = true;
                    $("#customer_cardtoken_cc_cvc").css({
                      "border": "1px solid red",
                      "border-radius": "0px"
                      });
                      $("#token-error").text("* mandatory");
                      $("#customer_cardtoken_cc_cvc").keypress(function () {
                        $("#customer_cardtoken_cc_cvc").css({
                          "border": "1px solid #333",
                          "border-radius": "0px"
                          });
                          $("#token-error").text("");
                          });
                        }
                        if (token_cvc != "") {
                          if (token_cvc.length < 3) {
                            err = true;
                            $("#customer_cardtoken_cc_cvc").css({
                              "border": "1px solid red",
                              "border-radius": "0px"
                              });
                              $("#token-error").text("* check value");
                              $("#customer_cardtoken_cc_cvc").keypress(function () {
                                $("#customer_cardtoken_cc_cvc").css({
                                  "border": "1px solid #333",
                                  "border-radius": "0px"
                                  });
                                  $("#token-error").text("");
                                  });
                                }

                              }

                              if(err == true){
                                e.preventDefault();
                              }
                            }
                          }
                          }); 


                          if($(".woocommerce-SavedPaymentMethods-tokenInput").attr("name") == "wc-latpay_checkout-payment-token"){

                            if($("input[name=wc-latpay_checkout-payment-token]:checked").val() !== "new"){           
                             $("#payment_mode").attr("data-payment-mode", "token");
                             $(".latpay-cardcvc-fieldset").css("display", "block");               
                           }
                           else{
                            $("#payment_mode").attr("data-payment-mode", "card");
                            $(".latpay-cardcvc-fieldset").css("display", "none");              
                          }
                        }
                        else{
                          $("#payment_mode").attr("data-payment-mode", "card");
                          $(".latpay-cardcvc-fieldset").css("display", "none");            
                        }

                        $(".woocommerce-SavedPaymentMethods-tokenInput").change(function() {
                          if($(".woocommerce-SavedPaymentMethods-tokenInput").attr("name") == "wc-latpay_checkout-payment-token"){

                            if($("input[name=wc-latpay_checkout-payment-token]:checked").val() !== "new"){           
                             $("#payment_mode").attr("data-payment-mode", "token");
                             $(".latpay-cardcvc-fieldset").css("display", "block");               
                             $("#latpay-element").css("display", "none");               
                           }
                           else{
                            $("#payment_mode").attr("data-payment-mode", "card");
                            $(".latpay-cardcvc-fieldset").css("display", "none");
                            $("#latpay-element").css("display", "block");              
                          }
                        }
                        else{
                          $("#payment_mode").attr("data-payment-mode", "card");
                          $(".latpay-cardcvc-fieldset").css("display", "none");            
                        }
                        });

                        if($("ul.woocommerce-SavedPaymentMethods").attr("data-count")<1){
                          $("#wc-latpay_checkout-payment-token-new").prop("checked", true);
                          $(".woocommerce-SavedPaymentMethods").css("display", "none");
                        }

                        $("#customer_cardtoken_cc_cvc").on("keypress change blur", function (e) {
                          if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
                            return false;
                          }
                          });

                          })(jQuery);

                          </script>

                          ';
                          return $inputfiled;
                        }

                        public function save_payment_method_checkbox() {
                          printf(
                            '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                            <input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
                            <label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
                            </p>',
                            esc_attr( $this->id ),
                            esc_html( apply_filters( 'wc_latpay_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-gateway-latpay' ) ) )
                          );
                        }

                        public function is_using_saved_payment_method() {
                          $payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : 'latpay_checkout';

                          return ( isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
                        }

    // Send the Payment token to Latpay

// function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {

//   $result = $this->process_subscription_payment( $order, $amount_to_charge );

//   if ( is_wp_error( $result ) ) {
//     WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
//   } else {
//     WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
//   }
// }

        public function custom_logs($message) { 
          if(is_array($message)) { 
            $message = json_encode($message); 
          } 
          $file = fopen("wp-content/latpay_log.log","a"); 
          echo fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message); 
          fclose($file); 
        }


        public function process_payment($order_id)
        {
          $order = new WC_Order( $order_id );
          $first_name = $order->get_billing_first_name();
          $last_name = $order->get_billing_last_name();;
          $orderid = $order->get_id();
          global $woocommerce;   
          $cart = $woocommerce->cart;
          $merchantid  = $this -> settings['Merchant_User_Id'];
          $publickey   = $this -> settings['publickey'];
          $datakey     = $this -> settings['datakey'];
          $debug       = $this -> settings['debug'];
          $onHold       = $this -> settings['oh-hold-email'];
          $cart_total =   WC()->cart->total;
          $currency = get_woocommerce_currency();  

          $jsonData;
          $transtokenval;
          $cardtoken_cvc;
          $url;
          $card_type;

          $payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : 'latpay_checkout';
          if(isset($_POST['transtokenval']))
          {
            $card_type = "New Card";
            $url = 'https://lateralpayments.com/checkout/authorise/CapturebyToken';
            $transtokenval   = sanitize_text_field($_POST['transtokenval']);
            $status          = sanitize_text_field($_POST['status']);
            $reference       = sanitize_text_field($_POST['reference']);
            $description     = sanitize_text_field($_POST['reference']);
            $jsonData = array(
              'amount'         => $cart_total,
              'currency'       => $currency,
              'status'         => $status,            
              'transtoken'     => $transtokenval,
              'datakey'        => $datakey,
              'merchantuserid' => $merchantid,
              'reference'      => $reference,
              'description'    => $description
            );
          }
          else{    
            $card_type = "Saved Card";
            $url = 'https://lateralpayments.com/checkout/authorise/Capture';
    //Retrive saved token in json
            if ( $this->is_using_saved_payment_method() ) {
    // Use an existing token, and then process the payment.
              $wc_token_id = wc_clean( $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
              $wc_token    = WC_Payment_Tokens::get( $wc_token_id );
              $token_decode     = json_decode(stripslashes($wc_token),true);
              $cardToken_pay   = $token_decode['token'];
            }
            $reference = md5(mt_Rand().date(" m d y h:i:s"));
            $description = $reference;
            $cardtoken_cvc   = sanitize_text_field($_POST['cardtoken_cvc']);

            $jsonData = array(
              'amount'         => $cart_total,
              'currency'       => $currency,
              'merchantid'     => $merchantid,
              'datakey'        => $datakey,
              'reference'      => $reference,
              'description'    => $description,
              'Cardtoken'      => $cardToken_pay,
              'cvc'            => $cardtoken_cvc,
              "fristname"      => $first_name,
              "lastname"       => $last_name

            );
          }


          $user_status = is_user_logged_in();      


// This checks to see if customer opted to save the payment method to file.
          $maybe_saved_card = isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );

          $data_json = json_encode($jsonData);  

        //WP REMOTE POST
          $context = array(
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => $data_json,
            'timeout'     => 120,
            'method'      => 'POST',
            'data_format' => 'body',
          );
          $capture_starts = date("h:i:s");
          $response = wp_remote_post( $url, $context);
          $jdecode = json_decode(wp_remote_retrieve_body($response), true);
          $capture_ends = date("h:i:s");

          $res = json_encode($jdecode);

          $cardToken   = $jdecode['Capture']['cardtoken'];
          $cardlast4   = $jdecode['Capture']['cardlast4'];
          $cardtype    = $jdecode['Capture']['cardtype'];
          $cardexpiry  = $jdecode['Capture']['cardexpiry'];
          $month       = substr($cardexpiry,0,2);
          $year        = substr($cardexpiry,-4);
          $status_code = $jdecode['Capture']['status']['StatusCode'];
          $errorcode   = $jdecode['Capture']['status']['errorcode'];
          $statusdesc  = $jdecode['Capture']['status']['errordesc'];

          if($debug == "yes"){
            $this ->custom_logs("\n THIS IS THE START OF DEBUG LOG ");
            $this ->custom_logs("CARD TYPE = ".$card_type );
            $this ->custom_logs("CAPTURE URL = ".$url );
            $this ->custom_logs("START TIME ".$capture_starts);
            $this ->custom_logs("CAPTURE REQUEST = ".$data_json );    
            $this ->custom_logs("RESPONSE = ".$res );
            $this ->custom_logs("END TIME ".$capture_ends);
          }


          if( $status_code == "0" ){

            // Set order status
           $order->update_status( 'processing',$statusdesc);

            // Reduce stock levels
           wc_reduce_stock_levels( $orderid );

            // Remove cart
           WC()->cart->empty_cart();

            //Save card token
           if ($user_status == 1 && $maybe_saved_card == 1) {


             $token = new WC_Payment_Token_CC();
             $token->set_token( $cardToken ); // Token comes from payment processor
             $token->set_gateway_id( $this->id );
             $token->set_card_type( $cardtype );
             $token->set_last4( $cardlast4 );
             $token->set_expiry_month( $month );
             $token->set_expiry_year( $year );
             $token->set_user_id( get_current_user_id() );
            // Save the new token to the database
             $token->save();          
            // Set this token as the users new default token
             WC_Payment_Tokens::set_users_default( get_current_user_id(), $token->get_id() );
           }      
           $order->add_order_note('STATUS = '. $status_code."<br> DESCRIPTION ".$statusdesc.'<br> REFERENCE = '.$reference.' <br> START TIME = '.$capture_starts.' <br>END TIME = '.$capture_ends);

           return array(
            'result'    => 'success',
            'redirect'  => $order->get_checkout_order_received_url( true )
          );


         }else if($status_code == "") {
           $order->update_status( 'on-hold' );
           $order->add_order_note( 'Timeout occured contact latpay.<br>REFERENCE = '.$reference.' <br> START TIME = '.$capture_starts.' <br>END TIME = '.$capture_ends);
           wc_add_notice( 'Timeout occured - Please try again', 'error' );
           if ($onHold == "yes") {                      

            $subject = 'Order #'.$orderid.' oh-hold';
            $content = "<b>You’ve received new order from $first_name $last_name Order ID( $orderid ), which is currently on-hold state, kindly contact latpay team for more details.<br></b>";

                      // load the mailer
            $mailer = WC()->mailer();
            $mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $subject, $content ), '', '' );
          }
          return;
        }else {
         $order->update_status( 'failed');
         $order->add_order_note('STATUS = '. $status_code."<br> DESCRIPTION ".$statusdesc.'<br> REFERENCE = '.$reference.' <br> START TIME = '.$capture_starts.' <br>END TIME = '.$capture_ends);
         wc_add_notice( $statusdesc . ' Please try again.', 'error' );
         return;
       }

     }

}//END CLASS


// To Add gateway in woocommerce payment gateway list
      function woocommerce_add_checkout_gateway_latpay_gateway($methods) {
        $methods[] = 'WC_HPS_Checkout_Gateway_Latpay';
        return $methods;
      }
      add_filter('woocommerce_payment_gateways', 'woocommerce_add_checkout_gateway_latpay_gateway' );
    }
