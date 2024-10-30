<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LD_Salesforce_Settings
 * @package  LD_Salesforce
 */
class LD_Salesforce_Settings {
	public function __construct() {
		$this->options = get_option( 'learndash_salesforce_settings', [] );

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_init', [ $this, 'api_status' ], 1 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'sub_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'learndash_admin_tabs', [ $this, 'admin_tabs' ], 10, 1 );
		add_filter( 'learndash_admin_tabs_on_page', [ $this, 'admin_tabs_on_page' ], 10, 3 );
	}

	public function admin_notices() {
		$data              = [];
		$is_submit         = filter_input( INPUT_GET, 'settings-updated' );
		$option_page       = filter_input( INPUT_GET, 'page' );
		$connection_status = get_transient( 'learndash_salesforce_connection_status' );

		if ( 'learndash-salesforce-settings' !== $option_page ) {
			return;
		}

		$data['type'] = 'success';

		if ( 'connected' === $connection_status && ld_salesforce()->is_connected() ) {
			$data['msg'] = __( 'Connection established.', 'crm-salesforce-learndash-integration' );
		} elseif ( 'disconnected' === $connection_status ) {
			$data['msg'] = __( 'Disconnected.', 'crm-salesforce-learndash-integration' );
		} elseif ( true == $is_submit ) {
			$data['msg'] = __( 'Settings Updated.', 'crm-salesforce-learndash-integration' );
		}

		delete_transient( 'learndash_salesforce_connection_status' );

		ld_salesforce()->render( 'admin/settings/notices.twig', $data );
	}

	public function api_status() {
		$is_submit   = filter_input( INPUT_POST, 'action' );
		$option_page = filter_input( INPUT_POST, 'option_page' );
		$disconnect  = filter_input( INPUT_POST, 'disconnect' );

		if ( 'update' != $is_submit || 'learndash_salesforce_settings_group' !== $option_page ) {
			return;
		}

		if ( $disconnect ) {
			ld_salesforce()->disconnect();
		} else {
			ld_salesforce()->api->auth();
		}
	}

	public function register_settings() {
		register_setting( 'learndash_salesforce_settings_group', 'learndash_salesforce_settings', [ $this, 'sanitize_settings' ] );

		$options = [
			'client_id'     => '',
			'client_secret' => '',
			'login_uri'     => '',
		];

		add_option( 'learndash_salesforce_settings', $options );

		add_settings_section( 'learndash_salesforce_settings', __return_null(), __return_empty_array(), 'learndash-salesforce-settings' );
	}

	/**
	 * Sanitize setting inputs
	 * @param  array $inputs Non-sanitized inputs
	 * @return array         Sanitized inputs
	 */
	public function sanitize_settings( $inputs ) {

		foreach ( $inputs as $key => $input ) {
			$inputs[ $key ] = sanitize_text_field( $input );
		}

		return $inputs;
	}

	/**
	 * Add plugin's menu
	 */
	public function sub_menu() {
		add_submenu_page(
			'edit.php?post_type=sfwd-courses',
			__( 'Salesforce', 'crm-salesforce-learndash-integration' ),
			__( 'Salesforce', 'crm-salesforce-learndash-integration' ),
			'manage_options',
			'admin.php?page=learndash-salesforce-settings',
			[ $this, 'page' ]
		);

		add_submenu_page(
			'learndash-lms-non-existant',
			__( 'Salesforce', 'crm-salesforce-learndash-integration' ),
			__( 'Salesforce', 'crm-salesforce-learndash-integration' ),
			'manage_options',
			'learndash-salesforce-settings',
			[ $this, 'page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_scripts() {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( ! is_admin() || ( 'learndash-salesforce-settings' !== $page ) ) {
			return;
		}

		// we need to load the LD plugin style.css, sfwd_module.css and sfwd_module.js because we want to replicate the styling on the admin tab.
		wp_enqueue_style( 'learndash_style', LEARNDASH_LMS_PLUGIN_URL . 'assets/css/style.css' );
		wp_enqueue_style( 'sfwd-module-style', LEARNDASH_LMS_PLUGIN_URL . 'assets/css/sfwd_module.css' );
		wp_enqueue_script( 'sfwd-module-script', LEARNDASH_LMS_PLUGIN_URL . 'assets/js/sfwd_module.js', [ 'jquery' ], LEARNDASH_VERSION, true );

		$data = [];
		$data = [ 'json' => json_encode( $data ) ];
		wp_localize_script( 'sfwd-module-script', 'sfwd_data', $data );

		// Load our admin JS
		// wp_enqueue_script( 'learndash-salesforce-admin', LDHS_ASSETS_URL . 'js/learndash-salesforce-admin.js', [ 'jquery' ], ld_salesforce()::VERSION, true );
	}

	public function get_salesforce_settings() {
		$is_connected = ld_salesforce()->is_connected();

		$client_keys_desc = sprintf(
			__( 'Check out %1$s to retrieve your credentials. Your OAuth scopes must match these %2$s.', 'crm-salesforce-learndash-integration' ),
			sprintf( '<a href="https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_defining_remote_access_applications.htm" target="_blank">%s</a>', __( 'this guide', 'crm-salesforce-learndash-integration' ) ),
			sprintf( '<a href="https://i.imgur.com/YbbJDtX.png" target="_blank">%s</a>', __( 'rules', 'crm-salesforce-learndash-integration' ) )
		);

		$settings = [
			[
				'connection'    => [
					'name' => __( 'Connection', 'crm-salesforce-learndash-integration' ),
					'type' => 'heading',
				],
				'client_id'     => [
					'name'     => __( 'Client ID', 'crm-salesforce-learndash-integration' ),
					'desc'     => $client_keys_desc,
					'type'     => 'text',
					'readonly' => $is_connected,
					'value'    => isset( $this->options['client_id'] ) ? $this->options['client_id'] : '',
				],
				'client_secret' => [
					'name'     => __( 'Client Secret', 'crm-salesforce-learndash-integration' ),
					'desc'     => $client_keys_desc,
					'type'     => 'password',
					'readonly' => $is_connected,
					'value'    => isset( $this->options['client_secret'] ) ? $this->options['client_secret'] : '',
				],
				'login_uri'     => [
					'name'        => __( 'Login URI', 'crm-salesforce-learndash-integration' ),
					'desc'        => sprintf(
						__( 'If using custom domain, add your Salesforce login URL. Defaults to %s', 'crm-salesforce-learndash-integration' ),
						'<strong>https://login.salesforce.com</strong>'
					),
					'type'        => 'text',
					'placeholder' => 'https://login.salesforce.com',
					'readonly'    => $is_connected,
					'value'       => isset( $this->options['login_uri'] ) ? $this->options['login_uri'] : '',
				],
				'callback_uri'  => [
					'name'     => __( 'Callback URI', 'crm-salesforce-learndash-integration' ),
					'desc'     => __( 'Add this Callback URI to your App API settings', 'crm-salesforce-learndash-integration' ),
					'type'     => 'text',
					'readonly' => true,
					'value'    => admin_url( 'admin.php?page=learndash-salesforce-settings&sf=1' ),
				],
			],
		];

		return apply_filters( 'learndash_salesforce_settings', $settings );
	}

	/**
	 * Setting page data
	 */
	public function page() {
		$data = [];

		$data['optionss'] = $this->get_salesforce_settings();
		array_walk(
			$data['optionss'],
			function( &$val, $key ) use ( & $data ) {
				array_walk(
					$val,
					function( &$val2, $key2 ) use ( & $data, $key ) {
						$data['optionss'][ $key ][ $key2 ]['key'] = $key2;
					}
				);
			}
		);

		$data['q_img']     = esc_attr( LEARNDASH_LMS_PLUGIN_URL . 'assets/images/question.png' );
		$data['connected'] = ld_salesforce()->is_connected();

		try {
			ld_salesforce()->render( 'admin/settings/page.twig', $data );
		} catch ( Exception $e ) {
			echo $e->getMessage();
		}
	}

	/**
	 * Add admin tabs for settings page
	 * @param  array $tabs Original tabs
	 * @return array       New modified tabs
	 */
	public function admin_tabs( $tabs ) {
		$tabs['salesforce'] = [
			'link'      => 'admin.php?page=learndash-salesforce-settings',
			'name'      => __( 'Salesforce Settings', 'crm-salesforce-learndash-integration' ),
			'id'        => 'admin_page_learndash-salesforce-settings',
			'menu_link' => 'edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses',
		];

		return $tabs;
	}

	/**
	 * Display active tab on settings page
	 * @param  array $admin_tabs_on_page Original active tabs
	 * @param  array $admin_tabs         Available admin tabs
	 * @param  int   $current_page_id    ID of current page
	 * @return array                     Currenct active tabs
	 */
	public function admin_tabs_on_page( $admin_tabs_on_page, $admin_tabs, $current_page_id ) {
		foreach ( $admin_tabs as $key => $value ) {
			if ( $value['id'] == $current_page_id && 'edit.php?post_type=sfwd-courses&page=sfwd-lms_sfwd_lms.php_post_type_sfwd-courses' === $value['menu_link'] ) {

				$admin_tabs_on_page[ $current_page_id ][] = 'salesforce';
				return $admin_tabs_on_page;
			}
		}

		return $admin_tabs_on_page;
	}

	/**
	 * Get all pages
	 * @return array
	 */
	public function get_pages() {
		$pages = get_pages();
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$pages_options[ $page->ID ] = $page->post_title;
			}
		}

		return $pages_options;
	}
}
