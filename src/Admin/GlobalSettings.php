<?php

declare(strict_types=1);

namespace BTCPayServer\WC\Admin;

use BTCPayServer\Client\ApiKey;
use BTCPayServer\WC\Helper\GreenfieldApiAuthorization;
use BTCPayServer\WC\Helper\GreenfieldApiHelper;
use BTCPayServer\WC\Helper\GreenfieldApiWebhook;
use BTCPayServer\WC\Helper\Logger;
use BTCPayServer\WC\Helper\OrderStates;

/**
 * todo: add validation of host/url
 * todo: check connection on safe and show notice if not working
 */
class GlobalSettings extends \WC_Settings_Page {

	public function __construct()
	{
		$this->id = 'btcpay_settings';
		$this->label = __( 'BTCPay Settings', 'btcpay-greenfield-for-woocommerce' );
		// Register custom field type order_states with OrderStatesField class.
		add_action('woocommerce_admin_field_order_states', [(new OrderStates()), 'renderOrderStatesHtml']);

		if (is_admin()) {
			// Register and include JS.
			wp_register_script('btcpay_gf_global_settings', BTCPAYSERVER_PLUGIN_URL . 'assets/js/apiKeyRedirect.js', ['jquery'], BTCPAYSERVER_VERSION);
			wp_enqueue_script('btcpay_gf_global_settings');
			wp_localize_script( 'btcpay_gf_global_settings',
				'BTCPayGlobalSettings',
				[
					'url' => admin_url( 'admin-ajax.php' ),
					'apiNonce' => wp_create_nonce( 'btcpaygf-api-url-nonce' ),
				]);
		}
		parent::__construct();
	}

	public function output(): void
	{
		$settings = $this->get_settings_for_default_section();
		\WC_Admin_Settings::output_fields($settings);
	}

	public function get_settings_for_default_section(): array
	{
		return $this->getGlobalSettings();
	}

	public function getGlobalSettings(): array
	{
		Logger::debug('Entering Global Settings form.');
		return [
			'title'                 => [
				'title' => esc_html_x(
					'BTCPay Server Payments Settings',
					'global_settings',
					'btcpay-greenfield-for-woocommerce'
				),
				'type'        => 'title',
				'desc' => sprintf( _x( 'This plugin version is %s and your PHP version is %s. If you need assistance, please come on our chat <a href="https://chat.btcpayserver.org" target="_blank">https://chat.btcpayserver.org</a>. Thank you for using BTCPay!', 'global_settings', 'btcpay-greenfield-for-woocommerce' ), BTCPAYSERVER_VERSION, PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ),
				'id' => 'btcpay_gf'
			],
			'url'                      => [
				'title'       => esc_html_x(
					'BTCPay Server URL',
					'global_settings',
					'btcpay-greenfield-for-woocommerce'
				),
				'type'        => 'text',
				'desc' => esc_html_x( 'Url to your BTCPay Server instance.', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'placeholder' => esc_attr_x( 'e.g. https://btcpayserver.example.com', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'desc_tip'    => true,
				'id' => 'btcpay_gf_url'
			],
			'api_key'                  => [
				'title'       => esc_html_x( 'BTCPay API Key', 'global_settings','btcpay-greenfield-for-woocommerce' ),
				'type'        => 'text',
				'desc' => _x( 'Your BTCPay API Key. If you do not have any yet <a href="#" class="btcpay-api-key-link" target="_blank">click here to generate API keys.</a>', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'default'     => '',
				'id' => 'btcpay_gf_api_key'
			],
			'store_id'                  => [
				'title'       => esc_html_x( 'Store ID', 'global_settings','btcpay-greenfield-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip' => _x( 'Your BTCPay Store ID. You can find it on the store settings page on your BTCPay Server.', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'default'     => '',
				'id' => 'btcpay_gf_store_id'
			],
			'default_description'                     => [
				'title'       => esc_html_x( 'Default Customer Message', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'textarea',
				'desc' => esc_html_x( 'Message to explain how the customer will be paying for the purchase. Can be overwritten on a per gateway basis.', 'btcpay-greenfield-for-woocommerce' ),
				'default'     => esc_html_x('You will be redirected to BTCPay to complete your purchase.', 'global_settings', 'btcpay-greenfield-for-woocommerce'),
				'desc_tip'    => true,
				'id' => 'btcpay_gf_default_description'
			],
			'transaction_speed'               => [
				'title'       => esc_html_x( 'Invoice pass to "confirmed" state after', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'select',
				'desc' => esc_html_x('An invoice becomes confirmed after the payment has...', 'global_settings', 'btcpay-greenfield-for-woocommerce'),
				'options'     => [
					'default'    => 'Keep store level configuration',
					'high'       => '0 confirmation on-chain',
					'medium'     => '1 confirmation on-chain',
					'low-medium' => '2 confirmations on-chain',
					'low'        => '6 confirmations on-chain',
				],
				'default'     => 'default',
				'desc_tip'    => true,
				'id' => 'btcpay_gf_transaction_speed'
			],
			'order_states'                    => [
				'type' => 'order_states',
				'id' => 'btcpay_gf_order_states'
			],
			'separate_gateways'                           => [
				'title'       => __( 'Separate Payment Gateways', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc' => _x( 'Make all supported and enabled payment methods available as their own payment gateway. This opens new possibilities like discounts for specific payment methods. See our <a href="todo-input-link-here" target="_blank">full guide here</a>', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'id' => 'btcpay_gf_separate_gateways'
			],
			'customer_data'                           => [
				'title'       => __( 'Send customer data to BTCPayServer', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc' =>  _x( 'If you want customer email, address, etc. sent to BTCPay Server enable this option. By default for privacy and GDPR reasons this is disabled.', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'id' => 'btcpay_gf_send_customer_data'
			],
			'debug'                           => [
				'title'       => __( 'Debug Log', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc'        => sprintf( _x( 'Enable logging <a href="%s" class="button">View Logs</a>', 'global_settings', 'btcpay-greenfield-for-woocommerce' ), Logger::getLogFileUrl()),
				'id' => 'btcpay_gf_debug'
			],
			// todo: not sure if callback and redirect url should be overridable; can be done via woocommerce hooks if
			// needed but no common use case for 99%
			/*
			'notification_url'                => [
				'title'       => esc_html_x( 'Notification URL', 'global_settings', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'url',
				'desc' => __( 'BTCPay will send IPNs for orders to this URL with the BTCPay invoice data', 'btcpay-greenfield-for-woocommerce' ),
				'default'     => '',
				'placeholder' => WC()->api_request_url( 'BTCPayServer_WC_Gateway_Default' ),
				'desc_tip'    => true,
				'id' => 'btcpay_gf_notification_url'
			],
			'redirect_url'                    => [
				'title'       => __( 'Redirect URL', 'btcpay-greenfield-for-woocommerce' ),
				'type'        => 'url',
				'desc' => __( 'After paying the BTCPay invoice, users will be redirected back to this URL', 'btcpay-greenfield-for-woocommerce' ),
				'default'     => '',
				'placeholder' => '', $this->get_return_url(),
				'desc_tip'    => true,
				'id' => 'btcpay_gf_redirect_url'
			],
			*/
			'sectionend' => [
				'type' => 'sectionend',
				'id' => 'btcpay_gf',
			],
		];
	}

	/**
	 * On saving the settings form make sure to check if the API key works and register a webhook if needed.
	 */
	public function save() {
		// If we have url, storeID and apiKey we want to check if the api key works and register a webhook.
		if ( $this->hasNeededApiCredentials() ) {
			// Check if api key works for this store.
			$apiUrl  = esc_url_raw( $_POST['btcpay_gf_url'] );
			$apiKey  = sanitize_text_field( $_POST['btcpay_gf_api_key'] );
			$storeId = sanitize_text_field( $_POST['btcpay_gf_store_id'] );

			if ( GreenfieldApiHelper::apiCredentialsExist($apiUrl, $apiKey, $storeId) ) {
				// Check if the provided API key has the right scope and permissions.
				try {
					$apiClient  = new ApiKey( $apiUrl, $apiKey );
					$apiKeyData = $apiClient->getCurrent();
					$apiAuth    = new GreenfieldApiAuthorization( $apiKeyData->getData() );
					$hasError   = false;

					if ( ! $apiAuth->hasSingleStore() ) {
						\WC_Admin_Settings::add_error( __( 'The provided API key scope is valid for multiple stores, please make sure to create one for a single store.', 'btcpay-greenfield-for-woocommerce' ) );
						$hasError = true;
					}

					if ( ! $apiAuth->hasRequiredPermissions() ) {
						\WC_Admin_Settings::add_error( sprintf(
							__( 'The provided API key does not match the required permissions. Please make sure the following permissions are are given: %s', 'btcpay-greenfield-for-woocommerce' ),
							implode( ', ', GreenfieldApiAuthorization::REQUIRED_PERMISSIONS )
						) );
					}

					// Check if a webhook for our callback url exists.
					if ( false === $hasError ) {
						// Check if we already have a webhook registered for that store.
						if ( GreenfieldApiWebhook::webhookExists( $apiUrl, $apiKey, $storeId ) ) {
							\WC_Admin_Settings::add_message( __( 'Reusing existing webhook.', 'btcpay-greenfield-for-woocommerce' ) );
						} else {
							// Register a new webhook.
							if ( GreenfieldApiWebhook::registerWebhook( $apiUrl, $apiKey, $storeId ) ) {
								\WC_Admin_Settings::add_message( __( 'Successfully registered a new webhook on BTCPay Server.', 'btcpay-greenfield-for-woocommerce' ) );
							} else {
								\WC_Admin_Settings::add_error( __( 'Could not register a new webhook on the store.', 'btcpay-greenfield-for-woocommerce' ) );
							}
						}
					}
				} catch ( \Throwable $e ) {
					\WC_Admin_Settings::add_error( sprintf(
						__( 'Error fetching data for this API key from server. Please check if the key is valid. Error: %s', 'btcpay-greenfield-for-woocommerce' ),
						$e->getMessage()
					) );
				}

			}
		}

		parent::save();

		// Purge separate payment methods cache if enabled.
		GreenfieldApiHelper::clearSupportedPaymentMethodsCache();
	}

	private function hasNeededApiCredentials(): bool {
		if (
			!empty($_POST['btcpay_gf_url']) &&
			!empty($_POST['btcpay_gf_api_key']) &&
			!empty($_POST['btcpay_gf_store_id'])
		) {
			return true;
		}
		return false;
	}
}
