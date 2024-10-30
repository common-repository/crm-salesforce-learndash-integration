<?php

/**
 * Cslass LD_Salesforce_API
 * @property array $config
 */
class LD_Salesforce_API {
	protected $config = [];

	public function __construct( $config = [] ) {
		$this->config = [
			'client_id'     => isset( $config['client_id'] ) ? $config['client_id'] : '',
			'client_secret' => isset( $config['client_secret'] ) ? $config['client_secret'] : '',
			'login_uri'     => isset( $config['login_uri'] ) ? $config['login_uri'] : '',
			'callback_uri'  => isset( $config['callback_uri'] ) ? $config['callback_uri'] : '',
		];

		$access = ld_salesforce()->is_connected();
		if ( $access ) {
			$this->config = array_merge( $this->config, $access );
		}
	}

	public function auth() {
		$auth_url = sprintf(
			'%s/services/oauth2/authorize?response_type=code&client_id=%s&redirect_uri=%s',
			$this->config['login_uri'],
			$this->config['client_id'],
			urlencode( $this->config['callback_uri'] )
		);
		wp_redirect( $auth_url, 301, 'LearnDash LMS - Salesforce' );
		exit;
	}

	public function oauth_cb() {
		$sf    = filter_input( INPUT_GET, 'sf' );
		$code  = filter_input( INPUT_GET, 'code' );
		$title = __( 'LearnDash Salesforce Callback', 'crm-salesforce-learndash-integration' );

		if ( empty( $sf ) || empty( $code ) ) {
			return;
		}

		$token_url = $this->config['login_uri'] . '/services/oauth2/token';

		$params = 'code=' . $code
		. '&grant_type=authorization_code'
		. '&client_id=' . $this->config['client_id']
		. '&client_secret=' . $this->config['client_secret']
		. '&redirect_uri=' . $this->config['callback_uri'];

		$curl = curl_init( $token_url );
		curl_setopt( $curl, CURLOPT_HEADER, false );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $params );

		$json_response = curl_exec( $curl );

		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		if ( 200 != $status ) {
			wp_die(
				sprintf(
					__( 'Error: call to token URL %1$s failed with status %2$s, response %3$s, curl_error %4$s, curl_errno %5$d', 'crm-salesforce-learndash-integration' ),
					$token_url,
					$status,
					$json_response,
					curl_error( $curl ),
					curl_errno( $curl )
				),
				$title
			);
		}

		curl_close( $curl );

		$response = json_decode( $json_response, true );

		$access_token = $response['access_token'];
		$instance_url = $response['instance_url'];

		if ( ! isset( $access_token ) || empty( $access_token ) ) {
			wp_die( __( 'Error - access token missing from response!', 'crm-salesforce-learndash-integration' ), $title );
		}

		if ( ! isset( $instance_url ) || empty( $instance_url ) ) {
			wp_die( __( 'Error - instance URL missing from response!', 'crm-salesforce-learndash-integration' ), $title );
		}

		update_option( 'learndash_salesforce_access_token', $access_token );
		update_option( 'learndash_salesforce_instance_url', $instance_url );

		set_transient( 'learndash_salesforce_connection_status', 'connected', HOUR_IN_SECONDS );
		wp_redirect( remove_query_arg( 'code' ), $query );
		exit;
	}

	/**
	 * Create account
	 * @see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_objects_account.htm
	 * @param string $email
	 * @return string|bool Account ID, otherwise false
	 */
	public function create_account( $props = [] ) {
		return $this->std_objects( 'Account', $props );
	}

	/**
	 * Find contact via email
	 * @param string $email
	 * @return array|bool Salesforce response array
	 */
	public function find_contact( $email = '' ) {
		$url = sprintf(
			'%s/services/data/v37.0/search/?q=%s',
			$this->config['instance_url'],
			urlencode( sprintf( 'FIND{%s}', $email ) )
		);

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => "OAuth {$this->config['access_token']}",
				],
			]
		);

		$response       = json_decode( wp_remote_retrieve_body( $response ), true );
		$search_records = $response['searchRecords'];

		if ( empty( $search_records ) ) {
			return false;
		}

		foreach ( $search_records as $key => $search_record ) {
			if ( 'Contact' === $search_record['attributes']['type'] ) {
				return $search_record;
			}
		}

		return false;
	}

	/**
	 * Create contact
	 * @see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_objects_contact.htm
	 * @param string $email
	 * @return string|bool Contact ID, otherwise false
	 */
	public function create_contact( $props = [] ) {
		return $this->std_objects( 'Contact', $props );
	}

	/**
	 * Create contract
	 * @see https://developer.salesforce.com/docs/atlas.en-us.api.meta/api/sforce_api_objects_contract.htm
	 * @param array $props
	 * @return string|bool Contract ID, otherwise false
	 */
	public function create_contract( $props = [] ) {
		return $this->std_objects( 'Contract', $props );
	}

	protected function std_objects( $channel = '', $props = [] ) {
		$url = sprintf(
			'%s/services/data/v20.0/sobjects/%s/',
			$this->config['instance_url'],
			$channel
		);

		$body = json_encode( $props );

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Content-type'  => 'application/json',
					'Authorization' => "OAuth {$this->config['access_token']}",
				],
				'body'    => $body,
			]
		);

		$status = wp_remote_retrieve_response_code( $response );

		if ( 201 != $status ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		return $response['id'];
	}
}
