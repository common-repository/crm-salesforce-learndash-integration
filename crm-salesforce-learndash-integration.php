<?php
/**
 * Plugin Name: CRM Salesforce LearnDash Integration
 * Description: Integrates your course enrollments with Salesforce CRM
 * Version: 1.0.1
 * Author: qfnetwork, rahilwazir
 * Author URI: https://www.qfnetwork.org
 * Text Domain: crm-salesforce-learndash-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LD_Salesforce
 */
class LD_Salesforce {
	const VERSION = '1.0.1';

	/**
	 * @var LD_Salesforce
	 */
	private static $instance = null;

	/**
	 * @var LD_Salesforce_API
	 */
	public $api = null;

	/**
	 * @var LD_Salesforce_Integration
	 */
	public $salesforce = null;

	/**
	 * @var LD_Salesforce_Settings
	 */
	public $settings = null;

	/**
	 * @return this
	 */
	public static function instance() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof LD_Salesforce ) ) {
			self::$instance = new self;

			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Setup Constants
	 */
	private function setup_constants() {
		define( 'LD_SALESFORCE_DIR', plugin_dir_path( __FILE__ ) );
		define( 'LD_SALESFORCE_FILE', __FILE__ );
		define( 'LD_SALESFORCE_INCLUDES_DIR', trailingslashit( LD_SALESFORCE_DIR . 'includes' ) );
		define( 'LD_SALESFORCE_VIEWS_DIR', LD_SALESFORCE_DIR . 'views' );
		define( 'LD_SALESFORCE_BASE_DIR', plugin_basename( __FILE__ ) );

		/**
		 * Plugin URLS
		 */
		define( 'LD_SALESFORCE_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
		define( 'LD_SALESFORCE_ASSETS_URL', trailingslashit( LD_SALESFORCE_URL . 'assets' ) );
	}

	/**
		 * Pugin Include Required Files
		 */
	private function includes() {
		require_once 'vendor/autoload.php';
		if ( is_admin() ) {
			require_once 'includes/admin/class-ld-salesforce-settings.php';
		}
		require_once 'includes/class-ld-salesforce-api.php';
		require_once 'includes/class-ld-salesforce-integration.php';
	}

	private function hooks() {
		add_filter( 'plugin_action_links_' . LD_SALESFORCE_BASE_DIR, [ $this, 'settings_link' ], 10, 1 );
	}

	public function init() {
		self::$instance->options = get_option( 'learndash_salesforce_settings', [] );

		self::$instance->api        = new LD_Salesforce_API( self::$instance->options );
		self::$instance->salesforce = new LD_Salesforce_Integration();

		if ( is_admin() ) {
			self::$instance->settings = new LD_Salesforce_Settings();
		}
	}

	/**
	 * Add settings link on plugin page
	 *
	 * @return void
	 */
	public function settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=learndash-salesforce-settings' ) . '">' . __( 'Settings', 'crm-salesforce-learndash-integration' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function admin_notice() {
		require_once 'vendor/autoload.php';
		$data           = [];
		$data['notice'] = sprintf(
			__( '%1$s plugin requires %2$s to be activated.', 'crm-salesforce-learndash-integration' ),
			'<strong>LearnDash LMS - Salesforce</strong>',
			'<strong>LearnDash LMS</strong>'
		);
		self::render( 'admin/dependency-notice.twig', $data );
	}

	/**
	 * @param string $template
	 * @param array $data
	 * @param bool $echo
	 * @return string
	 */
	public function render( $template, $data = [], $echo = true ) {
		if ( ! $echo ) {
			ob_start();
		}

		Timber\Timber::render( $template, $data );

		if ( ! $echo ) {
			return ob_get_clean();
		}
	}

	/**
	 * Get access_token and instance_url
	 * @return array|bool
	 */
	public function is_connected() {
		$access_token = get_option( 'learndash_salesforce_access_token' );
		$instance_url = get_option( 'learndash_salesforce_instance_url' );

		if ( empty( $access_token ) || empty( $instance_url ) ) {
			return false;
		}

		return [
			'access_token' => $access_token,
			'instance_url' => $instance_url,
		];
	}

	/**
	 * Simply removes the access token from options table
	 * @return bool
	 */
	public function disconnect() {
		if ( $this->is_connected() ) {
			delete_option( 'learndash_salesforce_access_token' );
			delete_option( 'learndash_salesforce_instance_url' );
			set_transient( 'learndash_salesforce_connection_status', 'disconnected', HOUR_IN_SECONDS );
			return true;
		}

		return false;
	}
}

/**
 * @return LD_Salesforce|bool
 */
function ld_salesforce() {
	if ( ! class_exists( 'SFWD_LMS' ) ) {
		add_action( 'admin_notices', [ 'LD_Salesforce', 'admin_notice' ] );
		return false;
	}

	return LD_Salesforce::instance();
}
add_action( 'plugins_loaded', 'ld_salesforce' );
