<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LD_Salesforce_Integration
 * @package LD_Salesforce
 */
class LD_Salesforce_Integration {
	public function __construct() {
		// Callback action
		add_action( 'admin_init', [ ld_salesforce()->api, 'oauth_cb' ], 0 );

		// Dispatch calls to Salesforce on enroll
		add_action( 'learndash_update_course_access', [ $this, 'course_access' ], 10, 4 );
	}

	/**
	 * Run actions after a users list of courses is updated
	 *
	 * @param  int      $user_id
	 * @param  int      $course_id
	 * @param  array    $access_list
	 */
	public function course_access( $user_id, $course_id, $access_list, $remove ) {
		if ( is_admin() ) {
			return;
		}

		$user_data = get_userdata( $user_id );

		if ( ! $user_data ) {
			return;
		}

		$course_title = get_the_title( $course_id );
		$meta         = get_post_meta( $course_id, '_sfwd-courses', true );
		$course_price = $meta['sfwd-courses_course_price'];

		$course_price = floatval( preg_replace( '/[^0-9.]/', '', $course_price ) );

		// Create contact if not already exists
		$contact = $this->create_contact(
			$user_data,
			[
				'Title' => $course_title,
			]
		);
	}

	/**
	 * Create contact if not already exists
	 * @param WP_User $user_data
	 * @param array $course_details
	 * @return array|bool
	 */
	protected function create_contact( WP_User $user_data, $course_details = [] ) {
		$contact = ld_salesforce()->api->find_contact( $user_data->user_email );

		if ( $contact ) {
			return $contact;
		}

		$account = ld_salesforce()->api->create_account(
			[
				'Name' => $user_data->display_name,
			]
		);

		if ( ! $account ) {
			return false;
		}

		$firstname = ( empty( $user_data->first_name ) ) ? $user_data->display_name : $user_data->first_name;
		$lastname  = ( empty( $user_data->last_name ) ) ? $user_data->display_name : $user_data->last_name;

		$contact = ld_salesforce()->api->create_contact(
			array_merge(
				[
					'FirstName' => $firstname,
					'LastName'  => $lastname,
					'Email'     => $user_data->user_email,
					'AccountId' => $account,
				],
				$course_details
			)
		);

		return [ $account, $contact ];
	}

	/**
	 * Create contract
	 * @param WP_User $user_data
	 * @return array|bool
	 */
	protected function create_contract( WP_User $user_data ) {
		$contact = ld_salesforce()->api->create_contact( $user_data->user_email );

		if ( $contact ) {
			return $contact;
		}

		$account = ld_salesforce()->api->create_account(
			[
				'Name' => $user_data->display_name,
			]
		);

		if ( ! $account ) {
			return false;
		}

		$contact = ld_salesforce()->api->create_contact(
			[
				'FirstName' => $user_data->first_name,
				'LastName'  => $user_data->last_name,
				'Email'     => $user_data->user_email,
				'AccountId' => $account,
			]
		);

		return $contact;
	}
}
