<?php
/*
Plugin Name: AffiliateWP - Store Credit
Plugin URI: http://affiliatewp.com
Description: Pay AffiliateWP referrals as store credit. Currently supports WooCommerce, with support for EDD planned.
Author: ramiabraham
Contributors: ramiabraham, mordauk, sumobi
Version: 0.1
Author URI: http://affiliatewp.com
Text Domain: affwp_wc_credit
Domain Path: /lang
 */

add_action( 'plugins_loaded', array ( AffiliateWP_Store_Credit::get_instance(), 'plugin_setup' ) );

class AffiliateWP_Store_Credit {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;


	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';


	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';


	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @since   0.1.0
	 * @return  object of this class
	 */
	public static function get_instance() {

		NULL === self::$instance and self::$instance = new self;

		return self::$instance;

	}


	/**
	 * Used for regular plugin work.
	 *
	 * @wp-hook plugins_loaded
	 * @since   0.1.0
	 * @return  void
	 */
	public function plugin_setup() {

		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );
		$this->load_language( 'affwp_wc_credit' );

		add_action( 'affwp_set_referral_status', array( $this, 'process_payout' ), 10, 3 );

		add_action( 'woocommerce_before_checkout_form', array( $this, 'action_add_checkout_notice' ) );

		add_action( 'init', array( $this, 'checkout_actions' ) );

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'validate_coupon_usage' ), 10, 2 );

	}


	/**
	 * Constructor. Intentionally left empty and public.
	 *
	 * @see plugin_setup()
	 * @since 0.1.0
	 */
	public function __construct() {
	}


	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @since   0.1.0
	 * @return  void
	 */
	public function load_language( $domain ) {

		load_plugin_textdomain( $domain, false, $this->plugin_path . '/languages' );

	}



	/**
	 * process_payout function.
	 *
	 * @access public
	 * @param mixed $referral_id
	 * @param mixed $new_status
	 * @param mixed $old_status
	 * @return void
	 */
	public function process_payout( $referral_id, $new_status, $old_status ) {

		// wp_die( var_dump( array( $referral_id, $new_status, $old_status ) ) );

		if ( 'paid' === $new_status ) {

			$this->add_payment( $referral_id );

		} else if ( ( 'paid' === $old_status ) && ( 'unpaid' === $new_status ) ) {

			$this->remove_payment( $referral_id );
		}

	}



	/**
	 * add_payment function.
	 *
	 * @access protected
	 * @param mixed $referral_id
	 * @return void
	 */
	protected function add_payment( $referral_id ) {

		// If the referral id isn't valid
		if ( ! is_numeric( $referral_id ) ) {
			return;
		}

		// Get the referral object
		$referral = affwp_get_referral( $referral_id );

		// Get the user id
		$user_id = affwp_get_affiliate_user_id( $referral->affiliate_id );

		// Get the user's current woocommerce credit balance
		$current_balance = get_user_meta( $user_id, 'affwp_wc_credit_balance', true );

		$new_balance = floatval( $current_balance + $referral->amount );

		return update_user_meta( $user_id, 'affwp_wc_credit_balance', $new_balance );

	}


	// @todo docblock
	protected function remove_payment( $referral_id ) {

		// If the referral id isn't valid
		if ( ! is_numeric( $referral_id ) ) {
			return;
		}

		// Get the referral object
		$referral = affwp_get_referral( $referral_id );

		// Get the user id
		$user_id = affwp_get_affiliate_user_id( $referral->affiliate_id );

		// Get the user's current woocommerce credit balance
		$current_balance = get_user_meta( $user_id, 'affwp_wc_credit_balance', true );

		$new_balance = floatval( $current_balance - $referral->amount );

		return update_user_meta( $user_id, 'affwp_wc_credit_balance', $new_balance );

	}



	/**
	 * action_add_checkout_notice function.
	 *
	 * @access public
	 * @return void
	 */
	public function action_add_checkout_notice() {

		$balance = (float) get_user_meta( get_current_user_id(), 'affwp_wc_credit_balance', true );

		$cart_coupons = WC()->cart->get_applied_coupons();

		$coupon_applied = $this->check_for_coupon( $cart_coupons );

		// If the user has a credit balance and haven't already generated and applied a coupon code
		if ( $balance && ! $coupon_applied ) {
			wc_print_notice( 'You have an account balance of <strong>'. wc_price( $balance ) . '</strong>. Would you like to use it now? <a href="' . add_query_arg( 'affwp_wc_apply_credit', 'true', WC()->cart->get_checkout_url() ) . '" class="button">Apply</a>', 'notice' );
		}

	}



	/**
	 * checkout_actions function.
	 *
	 * @access public
	 * @return void
	 */
	public function checkout_actions() {

		if ( isset( $_GET['affwp_wc_apply_credit'] ) && $_GET['affwp_wc_apply_credit'] ) {

			$user_id = get_current_user_id();

			// Get the credit balance and cart total
			$credit_balance = (float) get_user_meta( $user_id, 'affwp_wc_credit_balance', true );
			$cart_total     = (float) $this->calculate_cart_subtotal();

			// Determine the max possible coupon value
			$coupon_total = $this->calculate_coupon_amount( $credit_balance, $cart_total );

			// Bail if the coupon value was 0
			if ( $coupon_total <= 0 ) {
				return;
			}

			// Attempt to generate a coupon code
			$coupon_code = $this->generate_coupon( $user_id, $coupon_total );

			// If a coupon code was successfully generated, apply it
			if ( $coupon_code ) {

				WC()->cart->add_discount( $coupon_code );

			}

		}
	}



	/**
	 * calculate_cart_subtotal function.
	 *
	 * @access protected
	 * @return void
	 */
	protected function calculate_cart_subtotal() {

		$cart_subtotal = ( 'excl' == WC()->cart->tax_display_cart ) ? WC()->cart->subtotal_ex_tax : WC()->cart->subtotal;

		return $cart_subtotal;

	}



	/**
	 * calculate_coupon_amount function.
	 *
	 * @access protected
	 * @param mixed $credit_balance
	 * @param mixed $cart_total
	 * @return void
	 */
	protected function calculate_coupon_amount( $credit_balance, $cart_total ) {

		// If either of these are empty, return 0
		if ( ! $credit_balance || ! $cart_total ) {
			return 0;
		}

		if ( $credit_balance > $cart_total ) {

			$coupon_amount = $cart_total;

		} else {

			$coupon_amount = $credit_balance;

		}

		return $coupon_amount;

	}



	/**
	 * generate_coupon function.
	 *
	 * @access protected
	 * @param int $user_id (default: 0)
	 * @param int $amount (default: 0)
	 * @return void
	 */
	protected function generate_coupon( $user_id = 0, $amount = 0 ){

		$amount = floatval( $amount );
		if ( $amount <= 0 ) {
			return false;
		}

		$user_id = ( $user_id ) ? $user_id : get_current_user_id();

		$date = current_time( 'Ymd' );

		$coupon_code = 'AFFILIATE-CREDIT-' . $date . '-' . $user_id;

		$expires = date( 'Y-m-d', strtotime( '+2 days', current_time( 'timestamp' ) ) );

		$coupon = array(
			'post_title' => $coupon_code,
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => 1,
			'post_type'		=> 'shop_coupon'
		);

		$new_coupon_id = wp_insert_post( $coupon );

		if ( $new_coupon_id ) {

			update_post_meta( $new_coupon_id, 'discount_type', 'fixed_cart' );
			update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
			update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
			update_post_meta( $new_coupon_id, 'usage_limit', '1' );
			update_post_meta( $new_coupon_id, 'expiry_date', $expires );
			update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
			update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

			return $coupon_code;

		}

		return false;

	}



	/**
	 * validate_coupon_usage function.
	 *
	 * @access public
	 * @param mixed $order_id
	 * @param mixed $data
	 * @return void
	 */
	public function validate_coupon_usage( $order_id, $data ) {

		// Get the order object
		$order = new WC_Order( $order_id );

		// Get the user ID associated with the order
		$user_id = $order->get_user_id();

		// Grab an array of coupons used
		$coupons = $order->get_used_coupons();

		// If the order has coupons
		if ( $coupon_code = $this->check_for_coupon( $coupons ) ) {

			// Process the coupon usage and remove the amount from the user's credit balance
			$this->process_used_coupon( $user_id, $coupon_code );

		}

	}



	/**
	 * check_for_coupon function.
	 *
	 * @access protected
	 * @param array $coupons (default: array())
	 * @return void
	 */
	protected function check_for_coupon( $coupons = array() ) {

		if ( ! empty( $coupons ) ) {

			foreach ( $coupons as $coupon_code ) {

				// Return coupon code if an affiliate credit coupon is found
				if ( false !== stripos( $coupon_code, 'AFFILIATE-CREDIT-' ) ) {

					return $coupon_code;

				}

			}

		}

		return false;

	}



	/**
	 * process_used_coupon function.
	 *
	 * @access protected
	 * @param int $user_id (default: 0)
	 * @param string $coupon_code (default: '')
	 * @return void
	 */
	protected function process_used_coupon( $user_id = 0, $coupon_code = '' ) {

		if ( ! $user_id || ! $coupon_code ) {
			return;
		}

		$coupon = new WC_Coupon( $coupon_code );

		$coupon_amount = $coupon->amount;

		if ( ! $coupon_amount ) {
			return;
		}

		// Get the user's current woocommerce credit balance
		$current_balance = get_user_meta( $user_id, 'affwp_wc_credit_balance', true );

		$new_balance = floatval( $current_balance - $coupon_amount );

		return update_user_meta( $user_id, 'affwp_wc_credit_balance', $new_balance );

	}

}