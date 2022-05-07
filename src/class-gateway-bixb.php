<?php
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'bixb_add_gateway_class' );
function bixb_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Bixb_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'bixb_init_gateway_class' );

function bixb_init_gateway_class() {

	class WC_Bixb_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
			$this->id = 'bixb'; // payment gateway plugin ID
			$this->icon = plugin_dir_url( __DIR__ ) . '/dist/images/bixb-logo.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'Bixbcoin Gateway';
			$this->method_description = 'Bixbcoin payment gateway <br> IPN Link <code>'.str_replace('http://', 'https://', home_url( '/' ) . 'wp-json/bixb/ipn').'</code>'; // will be displayed on the options page
			$this->bixb_base_api_url = 'https://bixbpay.com'; // Bixb api base url

			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);

			// Method with all the options fields
			$this->init_form_fields();
			// Load the settings.
			$this->init_settings();

			$this->title 					= sanitize_title( $this->get_option( 'title' ) );
			$this->description 				= sanitize_textarea_field( $this->get_option( 'description' ) );
			$this->enabled 					= sanitize_text_field( $this->get_option( 'enabled' ) );
			$this->api_key 					= sanitize_text_field( $this->get_option( 'api_key' ) );
			$this->transaction_note_prefix 	= sanitize_text_field( $this->get_option( 'transaction_note_prefix' ) );
			$this->label_adresses 			= sanitize_text_field( $this->get_option( 'label_adresses' ) );
			$this->ipn_enabled 				= sanitize_text_field( $this->get_option( 'ipn_enabled' ) );
			$this->ipn_secret 				= sanitize_text_field( $this->get_option( 'ipn_secret' ) );
			$this->confirmation_count		= sanitize_text_field( $this->get_option( 'confirmation_count' ) );

			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ), 10, 1 );
			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			
			// You can also register a webhook here
			if ( $this->ipn_enabled === 'yes')
				add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook' ) );

			
 		}

		/**
 		 * Plugin options
 		 */
 		public function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title'       => esc_html( 'Enable/Disable' ),
					'label'       => esc_html( 'Enable Bixbcoin Gateway' ),
					'type'        => esc_html( 'checkbox' ),
					'description' => esc_html( '' ),
					'default'     => esc_html( 'no' )
				),
				'title' => array(
					'title'       => esc_html( 'Title' ),
					'type'        => esc_html( 'text' ),
					'description' => esc_html( 'This controls the title which the user sees during checkout.' ),
					'default'     => esc_html( 'Bixbcoin Gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => esc_html( 'Description' ),
					'type'        => esc_html( 'textarea' ),
					'description' => esc_html( 'This controls the description which the user sees during checkout.' ),
					'default'     => esc_html( 'Crypto is here! Pay with Bixbcoin crypto.' ),
					'desc_tip'    => true,
				),
				'api_key' => array(
					'title'       => esc_html( 'API Key' ),
					'type'        => esc_html( 'text' ),
					'description' => esc_html( 'Bixbcoin API key' ),
					'desc_tip'    => true,
				),
				'ipn_enabled' => array(
					'title'       => esc_html( 'Handle IPN Callback' ),
					'label'       => esc_html( 'Update order status automatically when transaction created' ),
					'type'        => esc_html( 'checkbox' ),
					'default'     => esc_html( 'yes' ),
				),
				'ipn_secret' => array(
					'title'       => esc_html( 'IPN secret' ),
					'type'        => esc_html( 'text' ),
					'description' => esc_html( 'To automatically update status of transaction you need set an IPN secret.' ),
					'desc_tip'    => true,
				),
				'confirmation_count' => array(
					'title'       => esc_html( 'Confirmation Count' ),
					'type'        => esc_html( 'number' ),
					'description' => esc_html( 'Confirm payment as payed after x confirmations.' ),
					'default'     => esc_html( '1' ),
					'desc_tip'    => true,
				),
				'label_adresses' => array(
					'title'       => esc_html( 'Label adresses' ),
					'label'       => esc_html( 'Label new addresses with order id' ),
					'type'        => esc_html( 'checkbox' ),
					'description' => esc_html( 'Label generated address with created order id' ),
					'default'     => esc_html( 'yes' ),
					'desc_tip'    => true,
				),
				'transaction_note_prefix' => array(
					'title'       => esc_html( 'Transaction address prefix' ),
					'type'        => esc_html( 'textarea' ),
					'description' => esc_html( 'Anything you wanna add before transaction address' ),
					'default'     => esc_html( 'Transaction address: ' ),
					'desc_tip'    => true,
				),
			);
	 	}

		/**
		 * You will need it if you want your custom credit card form
		 */
		public function payment_fields() {
			// That day
		}

		/*
		 * some checks
		 */
	 	public function payment_scripts() {

			if ( ! is_cart() && ! is_checkout() && ! isset( sanitize_text_field( $_GET['pay_for_order'] ) ) ) {
				return;
			}

			// if our payment gateway is disabled
			if ( 'no' === $this->enabled ) {
				return;
			}

			if ( empty( $this->api_key ) ) {
				return;
			}

			// do not work with card detailes without SSL unless your website 
			if ( ! is_ssl() ) {
				// return;
			}

	
	 	}

		/*
 		 * Fields validation
		 */
		public function validate_fields() {
			// Maybe some day
		}

		/*
		 * We're processing the payments here
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;
		
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );
		
			// Set adress label to order id if user want
			$label = ($this->label_adresses === 'yes') ? $order_id : '';

			/*
			* Array with parameters for API interaction
			*/
			$args = array(
		
				'method' => 'POST',
				'headers' => array(
					'Authorization' => "Bearer {$this->api_key}"
				),
				'body' => array(
					'label' => esc_html( $label )
				)
		
			);
		
			$response = wp_remote_post( $this->bixb_base_api_url . '/api/v1/address', $args );
		
		
			if( !is_wp_error( $response ) ) {
		
				$body = json_decode( $response['body'], true );
				
				if (!isset($body['address'])) {
					$order->add_order_note('Fail in generating address. Error: ' . esc_html( $body['message'] ), false);
					wc_add_notice("Payment is not available at the moment.", 'error');
					return;
				}
		
				$order_total_bixb = self::usd_to_bixb($order->get_total());
				// Adding transaction address to order notes
				$order->add_order_note( esc_html( $this->transaction_note_prefix ) . esc_html( $body['address'] ), true, false );
				$order->add_order_note( "Bixb value assigned: " . esc_html( $order_total_bixb ), true, false );
				$order->update_meta_data('_bixb_address', esc_html( $body['address'] ));
				$order->update_meta_data('_bixb_amount', esc_html( $order_total_bixb ));

				// Save order
				$order->save();

				// Empty cart
				$woocommerce->cart->empty_cart();
		
				// Redirect to the thank you page
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
		
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}
			
			wc_add_notice(  'Connection error.', 'error' );
			return;
	 	}

		/*
		 * In case you need a webhook, like PayPal IPN, Bixb etc
		 */
		public function webhook() {

			if (empty($_POST)) {
				return [
					'status' => 'error',
					'message' => 'No data received'
				];
			}

			$me = new self();
			$ipnSecret = $me->ipn_secret;
			$confirmation_count = $me->confirmation_count;
			
			$address = sanitize_text_field( $_POST['address'] ); // Receiving address
			$amount = sanitize_text_field( $_POST['amount'] ); // Received amount
			$type = sanitize_text_field( $_POST['type'] );	// Transaction type {SEND, RECEIVE}
			$confirmations = sanitize_text_field( $_POST['confirmations'] ); // Number of confirmations the transaction has
			$validation_hash = hash("sha256","{$ipnSecret}-{$address}-{$amount}-{$type}-{$confirmations}");

			if ($type === 'SEND') {
				return [
					'status' => 'error',
					'message' => 'Sending transactions are not supported'
				];
			}

			// check and see is this a valid IPN call or not
			if ($validation_hash == sanitize_text_field( $_POST['hash'] )){
				// Valid IPN call
				$order_id = $me->get_order_id_by_address($address);
				$order = wc_get_order($order_id);

				// It's recommended to only confirm transaction with above 20 confirmations
				if ((int)$confirmations < (int)$confirmation_count) {
					$order->add_order_note('IPN Call maded by below '.esc_html( $confirmation_count ).' confirmation.', true, false);
					return [
						'status' => 'success',
						'message' => 'Transaction is not confirmed yet'
					];
				}

				// Checks if received amount is enough or not
				// @todo replace this amount with address amount cause of multi transactions
				if ($amount < $me->get_bixb_total($order_id)) {
					$order->add_order_note('Transaction received but it\'s not enought.', true, false);
					return [
						'status' => 'success',
						'message' => 'Transaction amount is not enough.'
					];
				}
				
				// Everything is fine, complete payment and reduce order stock
				$order->payment_complete();
				$order->reduce_order_stock();
				$order->add_order_note('IPN call confirmed.', true, false);
				return [
					'status' => 'success',
					'message' => 'Transaction is confirmed at '.esc_html( $confirmation_count ).' confirmation.'
				];
			}else {
				// Invalid IPN call
				return [
					'status' => 'error',
					'message' => 'Invalid IPN call'
				];
			}

	 	}

		/**
		 * Output for the order received page.
		 * 
		 * @since 1.0
		 * @param	int	order id
		 */
		public function thankyou_page($order_id) {
			
			if (!self::order_has_address($order_id))
				return;
			
			$address = WC_Bixb_Gateway::get_order_address($order_id);
			$amount = WC_Bixb_Gateway::get_bixb_total( $order_id );
			?>
				<h4 style="text-align: center;"><?php esc_html_e( $this->transaction_note_prefix ); ?> <code><?php esc_html_e( $address ); ?></code><br>
			Amount of bixb You should send: <?php esc_html_e( $amount ); ?> <br>
			<?php _e( WC_Bixb_Gateway::get_qr_image($address, $amount) ); ?></h4>
			<?php
		}
		
		/**
		 * returns order id by order bixb address
		 * 
		 * @since 1.0
		 * @param	string 	address
		 * @return	int		order id
		 */
		public function get_order_id_by_address($address) {
			global $wpdb;
			$address = sanitize_text_field( $address );
			
			$result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bixb_address' AND meta_value = %s",
					$address
				)
			)l

			if (count($result) > 0)
				return $result[0]->post_id;
			
			return false;
		}

		/**
		 * returns order total in bixb
		 * 
		 * @since 1.0
		 * @param	int		order id
		 * @return	float	bixb price
		 */
		public function get_bixb_total($order_id) {
			return (float)wc_get_order($order_id)->get_meta('_bixb_amount');
		}

		/**
		 * checks is order has bixb address. Basicly it's checks that order method was bixb or not
		 * 
		 * @since 1.0
		 * @param	int	order id
		 * @return	bool
		 */
		public static function order_has_address($order_id) {

			$order = wc_get_order($order_id);
			$address = $order->get_meta('_bixb_address');
			return !empty($address);

		}

		/**
		 * get order address by order id
		 * 
		 * @since 1.0
		 * @param	int		order id
		 * @return	string	address
		 */
		public static function get_order_address($order_id) {
			return wc_get_order($order_id)->get_meta('_bixb_address');
		}

		/**
		 * Converts USD to bixb
		 * 
		 * @since 1.0
		 * @param	float|int	usd price
		 * @param	int			to bixb decimal value you want
		 * @return	float		bixb price
		 */
		public static function usd_to_bixb($usd, $decimal = 6) {
			$response = wp_remote_get( 'https://persia.exchange/home/price/usdt/bxb' );
 
			if ( is_array( $response ) && ! is_wp_error( $response ) ) {
				$bixbrate    = $response['body']; // use the content
			}

			
			return number_format((float)$usd / $bixbrate, $decimal, '.', '');
		}

		public function get_qr_image($address, $amount) {
			$data = 'bixbcoin:' . $address . '?amount=' . $amount;
			$url = 'https://chart.googleapis.com/chart?cht=qr&chl='.$data.'&chs=200x200&choe=UTF-8&chld=L|2';

			return '<img src="'.$url.'" alt="Qr code" />';
		}
 	}
}