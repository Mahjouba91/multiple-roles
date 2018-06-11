<?php
/**
 * A set of action functions which handle the behavior of the roles checklist
 * on user edit screens.
 */
class MDMR_Checklist_Controller {

	/**
	 * The model object.
	 *
	 * @var \MDMR_Model $model
	 */
	var $model;

	/**
	 * Constructor. Define properties.
	 *
	 * @param object $model The model object.
	 */
	public function __construct( $model ) {
		$this->model = $model;
	}

	/**
	 * Remove the default WordPress role dropdown from the DOM.
	 *
	 * @param string $hook The current admin screen.
	 */
	public function remove_dropdown( $hook ) {
		if ( 'user-edit.php' !== $hook && 'user-new.php' !== $hook) {
			return;
		}
		wp_enqueue_script( 'md-multiple-roles', MDMR_URL . 'views/js/scripts.js', array( 'jquery' ), '1.0' );
	}

	/**
	 * Output the checklist view. If the user is not allowed to edit roles,
	 * nothing will appear.
	 *
	 * @param object $user The current user object.
	 */
	public function output_checklist( $user ) {

		if ( ! $this->model->can_update_roles() ) {
			return;
		}

		wp_nonce_field( 'update-md-multiple-roles', 'md_multiple_roles_nonce' );

		$roles        = $this->model->get_editable_roles();
        $user_roles   = ( isset( $user->roles ) ) ? $user->roles : null;

		include( apply_filters( 'mdmr_checklist_template', MDMR_PATH . 'views/checklist.html.php' ) );

	}

	/**
	 * Update the given user's roles as long as we've passed the nonce
	 * and permissions checks.
	 *
	 * @param int $user_id The user ID whose roles might get updated.
	 */
	public function process_checklist( $user_id ) {

		// The checklist is not always rendered when this method is triggered on 'profile_update' (i.e. when updating a profile programatically),
		// First check that the 'md_multiple_roles_nonce' is available, else bail. If we continue to process and update_roles(), all user roles will be lost.
		// We check for 'md_multiple_roles_nonce' rather than 'md_multiple_roles' as this input/variable will be empty if all role inputs are left unchecked.
		if ( !isset($_POST['md_multiple_roles_nonce']) )
			return;

		if ( ! wp_verify_nonce( $_POST['md_multiple_roles_nonce'], 'update-md-multiple-roles' ) ) {
			return;
		}

		if ( ! $this->model->can_update_roles() ) {
			return;
		}

		$new_roles = ( isset($_POST['md_multiple_roles']) && is_array($_POST['md_multiple_roles']) ) ? $_POST['md_multiple_roles'] : array();

		$this->model->update_roles( $user_id, $new_roles );
	}

	/**
	 * Add multiple roles in the $meta array in wp_signups db table
	 *
	 * @since 1.1.4
	 *
	 * @param $user
	 * @param $user_email
	 * @param $key
	 * @param $meta
	 */
	public function mu_add_roles_in_signup_meta( $user, $user_email, $key, $meta ) {
		if ( isset( $_POST['md_multiple_roles_nonce'] ) && ! wp_verify_nonce( $_POST['md_multiple_roles_nonce'], 'update-md-multiple-roles' ) ) {
			return;
		}

		if ( ! $this->model->can_update_roles() ) {
			return;
		}

		$new_roles = ( isset( $_POST['md_multiple_roles'] ) && is_array( $_POST['md_multiple_roles'] ) ) ? $_POST['md_multiple_roles'] : array();
		if ( empty( $new_roles ) ) {
			return;
		}

		global $wpdb;

		// Get user signup
		// Suppress errors in case the table doesn't exist
		$suppress = $wpdb->suppress_errors();
		$signup   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->signups} WHERE user_email = %s", $user_email ) );
		$wpdb->suppress_errors( $suppress );

		if ( empty( $signup ) || is_wp_error( $signup ) ) {
			return new WP_Error( 'md_get_user_signups_failed' );
		}

		// Add multiple roles to a new array in meta var
		$meta = maybe_unserialize( $meta );
		$meta['md_roles'] = $new_roles;
		$meta = maybe_serialize( $meta );

		// Update user signup with good meta
		$where        = array( 'signup_id' => (int) $signup->signup_id );
		$where_format = array( '%d' );
		$formats      = array( '%s' );
		$fields       = array( 'meta' => $meta );
		$result       = $wpdb->update( $wpdb->signups, $fields, $where, $formats, $where_format );

		// Check for errors
		if ( empty( $result ) && ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'md_update_user_signups_failed' );
		}
	}
	
	/**
	 * Add roles in signup meta with WP 4.8 filter : better method
	 *
	 * @since 1.2.0
	 *
	 * @param $meta
	 * @param $domain
	 * @param $path
	 * @param $title
	 * @param $user
	 * @param $user_email
	 * @param $key
	 */
	public function mu_add_roles_in_signup_meta_recently( $meta, $domain, $path, $title, $user, $user_email, $key ) {
		if ( isset( $_POST['md_multiple_roles_nonce'] ) && ! wp_verify_nonce( $_POST['md_multiple_roles_nonce'], 'update-md-multiple-roles' ) ) {
			return;
		}
		
		if ( ! $this->model->can_update_roles() ) {
			return;
		}
		
		$new_roles = ( isset( $_POST['md_multiple_roles'] ) && is_array( $_POST['md_multiple_roles'] ) ) ? $_POST['md_multiple_roles'] : array();
		if ( empty( $new_roles ) ) {
			return;
		}
		
		$meta['md_roles'] = $new_roles;
		
		return $meta;
		
	}
	
	/**
	 * Add multiple roles after user activation
	 *
	 * @since 1.1.4
	 *
	 * @param $user_id
	 * @param $password
	 * @param $meta
	 */
	public function mu_add_roles_after_activation( $user_id, $password, $meta ) {
		if ( ! empty( $meta['md_roles'] ) ) {
			$this->model->update_roles( $user_id, $meta['md_roles'] );
		}
	}

}