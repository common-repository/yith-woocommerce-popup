<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Newsletter Mailchimp Integration class
 *
 * @class   YITH_Popup_Newsletter_Mailchimp
 * @package YITH WooCommerce Popup
 * @since   1.0.0
 * @author  YITH <plugins@yithemes.com>
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'YITH_YPOP_INIT' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'YITH_Popup_Newsletter_Mailchimp' ) ) {
	/**
	 * YITH_Popup_Newsletter_Mailchimp class
	 *
	 * @since 1.0.0
	 */
	class YITH_Popup_Newsletter_Mailchimp {
		/**
		 * Single instance of the class
		 *
		 * @var \YITH_Popup_Newsletter_Mailchimp
		 * @since 1.0.0
		 */
		protected static $instance;


		/**
		 * Returns single instance of the class
		 *
		 * @return \YITH_Popup_Newsletter_Mailchimp
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			add_filter( 'yith-popup-newsletter-integration-type', array( $this, 'add_integration' ) );
			add_filter( 'yith-popup-newsletter-metabox', array( $this, 'add_metabox_field' ) );

			$this->add_form_handling();
			$this->add_admin_form_handling();
		}

		/**
		 * Get Mailchimp list for the apikey set in db
		 *
		 * Get mailchimp lists; if no apikey is set, return false. If lists are stored in a transient, return the transient.
		 * If no transient is set for lists, get the list from mailchimp server, store the transient and return the list.
		 * If update is set, force the update of the list and of the transient
		 *
		 * @param boolean $update Whether to update list or no. Default false.
		 * @param int     $post_id Post id.
		 *
		 * @return boolean|mixed array()
		 * @since 1.0.0
		 * @version 1.29.0
		 */
		public function get_mailchimp_lists( $update = false, $post_id = 0 ) {

			if ( isset( $_REQUEST['apikey'] ) ) { //phpcs:ignore
				$apikey = sanitize_text_field( wp_unslash( $_REQUEST['apikey'] ) ); //phpcs:ignore
			} else {
				$apikey = YITH_Popup()->get_meta( '_mailchimp-apikey', $post_id );
			}

			if ( isset( $_REQUEST['serverprefix'] ) ) { //phpcs:ignore
				$server_prefix = sanitize_text_field( wp_unslash( $_REQUEST['serverprefix'] ) ); //phpcs:ignore
			} else {
				$server_prefix = YITH_Popup()->get_meta( '_mailchimp-serverprefix', $post_id );
			}

			if ( ( isset( $apikey ) && strcmp( $apikey, '' ) !== 0 ) && ( isset( $server_prefix ) && strcmp( $server_prefix, '' ) !== 0 ) ) {

				if ( ! $update ) {
					$transient = get_transient( 'yith-popup-mailchimp-newsletter-list' );
					if ( false !== $transient ) {
						return $transient;
					} else {
						return $this->set_mailchimp_lists( $apikey, $server_prefix, $post_id );
					}
				} else {
					return $this->set_mailchimp_lists( $apikey, $server_prefix, $post_id );
				}

			} else {
				return false;
			}
		}

		/**
		 * Set Mailchimp list transient and return the list
		 *
		 * @param string $apikey  Mailchimp apikey.
		 * @param string $serverprefix Mailchimp server prefix.
		 * @param int    $post_id  Post id.
		 *
		 * @return boolean|mixed array()
		 * @since 1.0.0
		 * @version 1.29.0
		 */
		public function set_mailchimp_lists( $apikey, $serverprefix, $post_id ) {
			if ( isset( $apikey ) && strcmp( $apikey, '' ) !== 0 && ( isset( $serverprefix ) && strcmp( $serverprefix, '' ) !== 0 ) ) {

				require_once( YITH_YPOP_INC . 'vendor/mailchimp/vendor/autoload.php');

				$mailchimp = new MailchimpMarketing\ApiClient();

				$mailchimp->setConfig([
					                      'apiKey' => $apikey,
					                      'server' => $serverprefix,
				                      ]);

				$lists = array();

				foreach ( $mailchimp->lists->getAllLists()->lists as $list ) {
					$lists[ $list->id ] = $list->name;
				}

				// memorize result array in a transient.
				set_transient( 'yith-popup-mailchimp-newsletter-' . $post_id . '-list', $lists, WEEK_IN_SECONDS );

				return $lists;
			} else {
				return false;
			}
		}

		/**
		 * Add Form Handling
		 *
		 * Add the frontend form handling, if needed
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function add_form_handling() {
			// add mailchimp subscription.
			add_action( 'wp_ajax_ypop_subscribe_mailchimp_user', array( $this, 'subscribe_mailchimp_user' ) );
			add_action( 'wp_ajax_nopriv_ypop_subscribe_mailchimp_user', array( $this, 'subscribe_mailchimp_user' ) );
		}

		/**
		 * Subscribe Mailchimp user
		 *
		 * Add user to a mailchinmp list posted via AJAX-Request to wp_ajax_subscribe_mailchimp_user action.
		 *
		 * @return void
		 * @since 1.0.0
		 * @version 1.29.0
		 */
		public function subscribe_mailchimp_user() {

			$post_id = sanitize_text_field( wp_unslash( $_REQUEST['yit_mailchimp_newsletter_form_id'] ) ); //phpcs:ignore
			$mail    = sanitize_email( wp_unslash( $_REQUEST['yit_mailchimp_newsletter_form_email'] ) ); //phpcs:ignore
			$apikey  = '';
			$list    = '';

			if ( isset( $post_id ) && strcmp( $post_id, '' ) !== 0 ) {
				$apikey       = YITH_Popup()->get_meta( '_mailchimp-apikey', $post_id );
				$serverprefix = YITH_Popup()->get_meta( '_mailchimp-serverprefix', $post_id );
				$list         = YITH_Popup()->get_meta( '_mailchimp-list', $post_id );
				$double_optin = YITH_Popup()->get_meta( '_mailchimp-double_opt_in', $post_id );
				$double_optin = yith_plugin_fw_is_true( $double_optin );
			}

			if ( isset( $mail ) && is_email( $mail ) ) {
				if ( isset( $list ) && strcmp( $list, '-1' ) !== 0 && isset( $apikey ) && strcmp( $apikey, '' ) !== 0 && check_ajax_referer( 'yit_mailchimp_newsletter_form_nonce', 'yit_mailchimp_newsletter_form_nonce', false ) ) {

					require_once( YITH_YPOP_INC . 'vendor/mailchimp/vendor/autoload.php');

					$mailchimp = new MailchimpMarketing\ApiClient();

					$mailchimp->setConfig([
						                      'apiKey' => $apikey,
						                      'server' => $serverprefix,
					                      ]);


					$update_list = $mailchimp->lists->updateList( $list, [
						"double_optin" => $double_optin,
					]);


					$response = $mailchimp->lists->batchListMembers( $list, ["members" => [
						[
							'email_address' => $mail,
							'email_type'    => 'html',
							'status'         => $double_optin ? 'pending' : 'subscribed',
						]
					]]);

					if ( count( $response->errors ) > 0 ) {
						$message  = '<span class="error">' . apply_filters( 'ywpop_start_message_error', esc_html__( 'Something went wrong:', 'yith-woocommerce-popup' ) );
						$message .= '<ul>';

						foreach ( $response->errors as $error ) {
							$code = $error->error_code;

							switch ( $code ) {
								case 'ERROR_CONTACT_EXISTS':
									$message_in = esc_html__( 'Email is already in the list', 'yith-woocommerce-popup' );
									break;
								case 'ERROR_GENERIC':
								default:
									$message_in = esc_html__( 'Mailchimp general error', 'yith-woocommerce-popup' );
									$message_in .= ': ' . esc_html( $error->error );
							}

							$message_in = apply_filters( 'ywpop_message_error_filter', $message_in, $code, $error->error, $response->errors );
							$message   .= '<li>' . $message_in . '</li>';
						}

						$message .= '</ul></span>';

						echo wp_kses_post( $message );
					} else {
						echo wp_kses_post( apply_filters( 'ywpop_message_success_filter', '<span class="success">' . esc_html__( 'Email successfully registered', 'yith-woocommerce-popup' ) . '</span>' ) );
					}
					die();
				} else {
					echo wp_kses_post( apply_filters( 'ywpop_message_wrong_filter', '<span class="error">' . esc_html__( 'Ops! Something went wrong', 'yith-woocommerce-popup' ) . '</span>' ) );
					die();
				}
			} else {
				echo wp_kses_post( apply_filters( 'ywpop_message_notice_filter', '<span class="notice">' . esc_html__( 'Ops! You have to use a valid email address', 'yith-woocommerce-popup' ) . '</span>' ) );

				die();
			}
		}

		/**
		 * Add Admin form handling
		 *
		 * Add the backend form handling formetabox, if needed
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function add_admin_form_handling() {
			// add mailchimp lists refresh.
			add_action( 'wp_ajax_ypop_refresh_mailchimp_list', array( $this, 'refresh_mailchimp_list' ) );

			// add admin-side scripts.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}

		/**
		 * Enqueue admin script
		 *
		 * Enqueue backend scripts; constructor add it to admin_enqueue_scripts hook
		 *
		 * @return void
		 * @since  1.0.0
		 */
		public function admin_enqueue_scripts() {
			global $pagenow;
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			if ( get_post_type() === YITH_Popup()->post_type_name && ( strcmp( $pagenow, 'post.php' ) === 0 || strcmp( $pagenow, 'post-new.php' ) === 0 ) ) {
				wp_enqueue_script( 'ypop-refresh-mailchimp-list', YITH_YPOP_ASSETS_URL . '/js/refresh-mailchimp-list' . $suffix . '.js', array( 'jquery' ), YITH_YPOP_VERSION, true );
				wp_localize_script(
					'ypop-refresh-mailchimp-list',
					'mailchimp_localization',
					array(
						'url'           => admin_url( 'admin-ajax.php' ),
						'nonce_field'   => wp_create_nonce( 'yit_mailchimp_refresh_list_nonce' ),
						'refresh_label' => __(
							'Refreshing...',
							'yith-woocommerce-popup'
						),
					)
				);
			}
		}

		/**
		 * Refresh Mailchimp List
		 *
		 * Refresh Mailchimp list in db and return for ajax callback.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function refresh_mailchimp_list() {
			$post_id = sanitize_text_field( wp_unslash( $_REQUEST['post_id'] ) ); //phpcs:ignore

			if ( check_ajax_referer( 'yit_mailchimp_refresh_list_nonce', 'yit_mailchimp_refresh_list_nonce', false ) && isset( $post_id ) && strcmp( $post_id, '' ) !== 0 ) {
				echo wp_json_encode( $this->get_mailchimp_lists( true, $post_id ) );
				die();
			} else {
				echo wp_json_encode( false );
				die();
			}
		}

		/**
		 * Add Metabox Field
		 *
		 * Add mailchimp specific fields to newsletter cpt metabox.
		 *
		 * @param array $args Arguments.
		 *
		 * @return mixed
		 * @since 1.0.0
		 */
		public function add_metabox_field( $args ) {
			global $pagenow;
			// generate option array.
			$options = array( '-1' => __( 'Select a list', 'yith-woocommerce-popup' ) );

			if ( isset( $_REQUEST['post'] ) && strcmp( $pagenow, 'post.php' ) === 0 ) { //phpcs:ignore
				$post_id = intval( sanitize_text_field( wp_unslash( $_REQUEST['post'] ) ) ); //phpcs:ignore

				$lists = $this->get_mailchimp_lists( false, $post_id );
				if ( false !== $lists ) {
					$options = array_merge( $options, $lists );
				}
			}

			$args['fields'] = array_merge(
				$args['fields'],
				array(
					'mailchimp-apikey'               => array(
						'label' => __( 'Mailchimp API Key', 'yith-woocommerce-popup' ),
						'desc'  => __( 'The Mailchimp API Key, used to connect WordPress to the Mailchimp service. If you need help to create a valid API Key, refer to this <a href="http://kb.mailchimp.com/article/where-can-i-find-my-api-key">tutorial</a>', 'yith-woocommerce-popup' ),
						'type'  => 'text',
						'std'   => '',
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-serverprefix'         => array(
						'label' => __( 'Mailchimp Server prefix', 'yith-woocommerce-popup' ),
						'desc'  => __( 'The Mailchimp server prefix, used to connect WordPress to the Mailchimp service. You can get the server prefix by your mailchimp page url. Ex: https://yt34.admin.mailchimp.com/ yt34 will be the server prefix.', 'yith-woocommerce-popup' ),
						'type'  => 'text',
						'std'   => '',
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-list'                 => array(
						'label'       => __( 'Mailchimp List', 'yith-woocommerce-popup' ),
						'desc'        => __( 'A valid Mailchimp list name. You may need to save your configuration before displaying the correct contents. If the list is not up to date, click the Refresh button', 'yith-woocommerce-popup' ),
						'type'        => 'select-mailchimp',
						'std'         => '-1',
						'class'       => 'mailchimp-list-refresh',
						'button_name' => __( 'Refresh', 'yith-woocommerce-popup' ),
						'options'     => $options,
						'deps'        => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-double_opt_in'        => array(
						'label' => __( 'Double Opt-in', 'yith-woocommerce-popup' ),
						'desc'  => __( 'When you check this option, MailChimp will send a confirmation email before adding the user to the list', 'yith-woocommerce-popup' ),
						'type'  => 'onoff',
						'std'   => 'yes',
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-email-label'          => array(
						'label' => __( 'Email field label', 'yith-woocommerce-popup' ),
						'desc'  => __( 'The label for the Email field', 'yith-woocommerce-popup' ),
						'type'  => 'text',
						'std'   => 'Email',
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-add-privacy-checkbox' => array(
						'label' => __( 'Add Privacy Policy', 'yith-woocommerce-popup' ),
						'desc'  => '',
						'type'  => 'onoff',
						'std'   => 'no',
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),

					'mailchimp-privacy-label'        => array(
						'label' => __( 'Privacy Policy Label', 'yith-woocommerce-popup' ),
						'desc'  => '',
						'type'  => 'text',
						'std'   => __( 'I have read and agree to the website terms and conditions.', 'yith-woocommerce-popup' ),
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),

					'mailchimp-privacy-description'  => array(
						'label' => __( 'Privacy Policy Description', 'yith-woocommerce-popup' ),
						'desc'  => __( 'You can use the shortcode [privacy_policy] (from WordPress 4.9.6) to add the link to privacy policy page', 'yith-woocommerce-popup' ),
						'type'  => 'textarea',
						'std'   => __( 'Your personal data will be used to process your request, support your experience throughout this website, and for other purposes described in our [privacy_policy].', 'yith-woocommerce-popup' ),
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
					'mailchimp-submit-label'         => array(
						'label' => __( 'Submit button label', 'yith-woocommerce-popup' ),
						'desc'  => __( 'This field is not always used. It depends on the style of the form.', 'yith-woocommerce-popup' ),
						'type'  => 'text',
						'std'   => __( 'Add Me', 'yith-woocommerce-popup' ),
						'deps'  => array(
							'ids'    => '_newsletter-integration',
							'values' => 'mailchimp',
						),
					),
				)
			);

			return $args;
		}

		/**
		 * Add Integration Type
		 *
		 * Add mailchimp integration to integration mode select in newsletter plugin
		 *
		 * @param array $integration Integration.
		 *
		 * @return mixed
		 * @internal param $types
		 *
		 * @since    1.0.0
		 */
		public function add_integration( $integration ) {
			$integration['mailchimp'] = esc_html__( 'Mailchimp', 'yith-woocommerce-popup' );
			return $integration;
		}


	}

	/**
	 * Unique access to instance of YITH_Popup class
	 *
	 * @return \YITH_Popup_Newsletter_Mailchimp
	 */
	function YITH_Popup_Newsletter_Mailchimp() {  //phpcs:ignore
		return YITH_Popup_Newsletter_Mailchimp::get_instance();
	}

	YITH_Popup_Newsletter_Mailchimp();
}

