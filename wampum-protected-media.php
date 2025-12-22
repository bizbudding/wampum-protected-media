<?php
/**
 * Plugin Name:       Wampum - Protected Media
 * Plugin URI:        https://bizbudding.com
 * Description:       Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to. Files are protected with time-limited tokens. Requires Genesis for file display and ACF Pro for the files metabox.
 * Version:           1.4.0
 *
 * Author:            Mike Hemberger, BizBudding
 * Author URI:        https://bizbudding.com
 *
 * GitHub Plugin URI: bizbudding/wampum-protected-media
 * GitHub Plugin URI: https://github.com/bizbudding/wampum-protected-media
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Must be at the top of the file.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Main Wampum_Protected_Media Class.
 *
 * @since 1.0.0
 */
final class Wampum_Protected_Media {

	/**
	 * @var Wampum_Protected_Media The one true Wampum_Protected_Media
	 * @since 1.0.0
	 */
	private static $instance;

	public $key_name       = 'wampum_protected_media';
	public $directory_name = 'wampum_protected_uploads';

	/**
	 * Main Wampum_Protected_Media Instance.
	 *
	 * Insures that only one instance of Wampum_Protected_Media exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   1.0.0
	 * @static  var array $instance
	 * @uses    Wampum_Protected_Media::setup_constants() Setup the constants needed.
	 * @uses    Wampum_Protected_Media::includes() Include the required files.
	 * @uses    Wampum_Protected_Media::setup() Activate, deactivate, etc.
	 * @see     ppdfs()
	 * @return  object | Wampum_Protected_Media The one true Wampum_Protected_Media
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup
			self::$instance = new Wampum_Protected_Media;
			// Methods
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
			self::$instance->filters();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @return  void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wampum-protected-media' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @return  void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wampum-protected-media' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   1.0.0
	 * @return  void
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_VERSION' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_VERSION', '1.4.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_DIR' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}


		// Plugin Folder URL.
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_URL' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_FILE' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_BASENAME' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   1.0.0
	 * @return  void
	 */
	private function includes() {
		// Include vendor libraries.
		require_once __DIR__ . '/vendor/autoload.php';
	}

	public function hooks() {
		register_activation_hook(   __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

		add_action( 'plugins_loaded',        array( $this, 'updater' ), 12 );
		add_action( 'init',                  array( $this, 'field_group' ) );
		add_action( 'admin_init',            array( $this, 'create_protection_files' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'register_scripts' ) );
		add_action( 'genesis_entry_content', array( $this, 'display' ), 20 );
		add_action( 'template_redirect',     array( $this, 'serve_protected_file' ) );
	}

	public function activate() {
		flush_rewrite_rules();
	}

	public function filters() {
		add_filter( 'acf/upload_prefilter/key=field_59ee1e45dc4b8', array( $this, 'upload_prefilter' ), 10, 3 );
		add_filter( 'acf/validate_value/key=field_59ee1e45dc4b8',   array( $this, 'validate_value' ), 10, 4 );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @since 0.1.0
	 *
	 * @uses https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return void
	 */
	public function updater() {
		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// Setup the updater.
		$updater = PucFactory::buildUpdateChecker( 'https://github.com/bizbudding/wampum-protected-media/', __FILE__, 'wampum-protected-media' );

		// Maybe set github api token.
		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		}
	}

	/**
	 * Creates blank index.php file
	 *
	 * This function runs approximately once per day in order to ensure all folders
	 * have their necessary protection files.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force
	 */
	function create_protection_files( $force = false ) {
		if ( false === get_transient( 'wampum_check_protection_files' ) || $force ) {
			$upload_path = $this->get_upload_dir();
			// Make sure the upload directory is created
			wp_mkdir_p( $upload_path );
			// Top level blank index.php
			if ( ! file_exists( $upload_path . '/index.php' ) && wp_is_writable( $upload_path ) ) {
				@file_put_contents( $upload_path . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' );
			}
			// Check for the files once per day
			set_transient( 'wampum_check_protection_files', true, 3600 * 24 );
		}
	}

	/**
	 * Retrieve the absolute path to the file upload directory without the trailing slash
	 *
	 * @return string $path Absolute path to the upload directory
	 */
	function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();
		wp_mkdir_p( $wp_upload_dir['basedir'] . '/' . $this->directory_name );
		$path = $wp_upload_dir['basedir'] . '/' . $this->directory_name;
		return $path;
	}


	// Register scripts for later enqueue.
	public function register_scripts() {
		wp_register_style( 'wampum-protected-media', WAMPUM_PROTECTED_MEDIA_PLUGIN_URL . 'assets/css/wampum-protected-media.css', array(), WAMPUM_PROTECTED_MEDIA_VERSION );
	}

	/**
	 * Generate a protected URL for a file attachment.
	 *
	 * Creates a time-limited token and returns a protected URL that goes through WordPress action hooks.
	 *
	 * @since 1.4.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Protected URL.
	 */
	public function generate_protected_url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		// Bail if invalid attachment ID.
		if ( ! $attachment_id ) {
			return '';
		}

		// Get expiration time (default 2 hours).
		$expiration_seconds = apply_filters( 'wampum_protected_media_token_expiration', 2 * HOUR_IN_SECONDS );
		$expiration        = time() + $expiration_seconds;

		// Generate secure token.
		$token = wp_generate_password( 32, false );

		// Store token in transient with expiration.
		// Format: attachment_id|expiration_timestamp
		$token_data = $attachment_id . '|' . $expiration;
		set_transient( 'wpm_token_' . $token, $token_data, $expiration_seconds );

		// Build protected URL using WordPress endpoint.
		$protected_url = add_query_arg(
			array(
				'wpm_download' => '1',
				'token'        => $token,
				'file'         => $attachment_id,
			),
			home_url( '/' )
		);

		return $protected_url;
	}

	/**
	 * Serve protected file via WordPress action hook.
	 *
	 * This avoids path issues with symlinked plugins.
	 *
	 * @since 1.4.0
	 */
	public function serve_protected_file() {
		// Only handle our download requests.
		if ( ! isset( $_GET['wpm_download'] ) || '1' !== $_GET['wpm_download'] ) {
			return;
		}

		// Get token and file ID from query parameters.
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
		$file_id = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0;

		// Validate token and file ID.
		if ( empty( $token ) || empty( $file_id ) ) {
			status_header( 404 );
			nocache_headers();
			die( 'Invalid request' );
		}

		// Get token data from transient.
		$transient_key = 'wpm_token_' . $token;
		$token_data   = get_transient( $transient_key );

		// Validate token exists and matches file ID.
		if ( false === $token_data ) {
			status_header( 404 );
			nocache_headers();
			die( 'Invalid or expired token' );
		}

		// Parse token data: attachment_id|expiration_timestamp
		$token_parts = explode( '|', $token_data );
		if ( count( $token_parts ) !== 2 ) {
			status_header( 404 );
			nocache_headers();
			die( 'Invalid token format' );
		}

		$stored_file_id = absint( $token_parts[0] );
		$expiration     = absint( $token_parts[1] );

		// Verify file ID matches.
		if ( $stored_file_id !== $file_id ) {
			status_header( 404 );
			nocache_headers();
			die( 'Token does not match file' );
		}

		// Check if token has expired.
		if ( time() > $expiration ) {
			status_header( 404 );
			nocache_headers();
			die( 'Token has expired' );
		}

		// Get file path.
		$file_path = get_attached_file( $file_id );

		// Verify file exists.
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			status_header( 404 );
			nocache_headers();
			die( 'File not found' );
		}

		// Get file MIME type.
		$mime_type = get_post_mime_type( $file_id );
		if ( ! $mime_type ) {
			$mime_type = 'application/octet-stream';
		}

		// Get file extension.
		$file_ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// Determine content disposition.
		// PDFs should display inline (in browser), others should download.
		$is_pdf      = ( 'pdf' === $file_ext );
		$disposition = $is_pdf ? 'inline' : 'attachment';

		// Get filename for download.
		$filename = basename( $file_path );

		// Allow filtering of headers.
		$headers = apply_filters( 'wampum_protected_media_download_headers', array(
			'X-Robots-Tag'        => 'noindex',
			'Content-Type'        => $mime_type,
			'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
			'Content-Length'      => filesize( $file_path ),
		), $file_id, $is_pdf );

		// Set headers.
		nocache_headers();
		foreach ( $headers as $header_name => $header_value ) {
			header( $header_name . ': ' . $header_value );
		}

		// Stream the file.
		readfile( $file_path );
		exit;
	}

	// Change the upload directory.
	public function upload_prefilter( $errors, $file, $field ) {
		add_filter( 'upload_dir',  array( $this, 'upload_directory' ) );
		return $errors;
	}

	// Build the upload directory name.
	public function upload_directory( $param ){
		$param['path'] = $param['basedir'] . '/' . $this->directory_name;
		$param['url']  = $param['baseurl'] . '/' . $this->directory_name;
		return $param;
	}

	// Make sure the upload is in the right directory.
	public function validate_value( $valid, $value, $field, $input ){
		// Bail if already invalid.
		if( ! $valid ) {
			return $valid;
		}
		// Get the file URL.
		$file = wp_get_attachment_url( $value );
		// If the file doesn't contain directory name, it's not protected.
		if ( false === strpos( $file, $this->directory_name ) ) {
			// Error message.
			$valid = sprintf( 'This File is not in the %s directory and may not be protected. Please upload a new file or choose one from the %s directory.', $this->directory_name, $this->directory_name );
		}
		return $valid;
	}

	// Display the file list.
	public function display() {
		// Bail if not a post type for ppdfs.
		if ( ! is_singular( $this->get_metabox_post_types() ) ) {
			return;
		}

		// Bail if ACF is not available.
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		// Get items in the field group using ACF's get_field.
		$items = get_field( $this->key_name, get_the_ID() );

		// Bail if no items.
		if ( ! $items ) {
			return;
		}

		// Enqueue styles and scripts.
		wp_enqueue_style( 'wampum-protected-media' );

		echo '<ul id="wpm-list" style="margin-left:0;">';

			echo '<li class="wpm-row">';
				echo '<span class="wpm-header">Files</span>';
			echo '</li>';

			// Loop through items.
			foreach ( $items as $item ) {

				// Skip if no file.
				if ( ! $item['file'] ) {
					continue;
				}

				// Get protected URL.
				$protected_url = $this->generate_protected_url( $item['file'] );

				// Get file extension from attachment.
				$direct_url = wp_get_attachment_url( $item['file'] );
				$ext        = strtolower( pathinfo( $direct_url, PATHINFO_EXTENSION ) );

				// Use protected URL for all files.
				$file_url = esc_url( $protected_url );

				// Image.
				$image = sprintf( '<span class="wpm-cell wpm-image"></span>' );
				if ( $item['image'] ) {
					$image_size = apply_filters( 'wampum_protected_media_image_size', 'thumbnail' );
					$image      = sprintf( '<a href="%s" class="wpm-cell wpm-image">%s</a>',
						$file_url,
						wp_get_attachment_image( $item['image'], $image_size )
					);
				}

				// Title.
				if ( $item['title'] ) {
					$title = esc_html( $item['title'] );
				} else {
					// Use filename as title.
					$title = basename( $direct_url );
				}
				$title = sprintf( '<span class="wpm-title"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a> (%s)</span>', esc_url( $file_url ), $title, $ext );

				// Description.
				$desc = '';
				if ( $item['desc'] ) {
					$desc = sprintf( '<span class="wpm-desc">%s</span>',
						wp_kses_post( $item['desc'] )
					);
				}

				// Content: Title and/or Desc.
				$content = sprintf( '<span class="wpm-cell wpm-grow wpm-content">%s</span>',
					$title . $desc
				);

				// Handle most files.
				if ( 'zip' !== $ext ) {
					// View/Download button.
					$actions = sprintf( '<a href="%s" class="wpm-button button button-secondary button-smallmore-link" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $file_url ), __( 'View', 'wampum-protected-media' ) );
				}

				// Handle PDFs and ZIPs.
				if ( 'pdf' === $ext || 'zip' === $ext ) {
					$actions .= sprintf(
						'<a href="%s" class="wpm-button button button-secondary button-small" download="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $file_url ), // Use protected URL.
						basename( $direct_url ), // Set the download filename.
						__( 'Download', 'wampum-protected-media' )
					);
				}

				// Wrap the actions in a span.
				$actions = sprintf( '<span class="wpm-cell wpm-auto wpm-actions">%s</span>', $actions );

				// Output the row.
				printf( '<li class="wpm-row">%s%s%s</li>', $image, $content, $actions );
			}

		echo '</ul>';
	}


	public function field_group() {

		if ( ! function_exists('acf_add_local_field_group') ) {
			return;
		}

		acf_add_local_field_group( array(
			'key'    => 'group_59ee1e45d32c5',
			'title'  => 'Protected Media',
			'fields' => array (
				array (
					'key'               => 'field_59ee1e45d9126',
					'label'             => 'Files',
					'name'              => $this->key_name,
					'type'              => 'repeater',
					'value'             => NULL,
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array (
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'collapsed'    => 'field_59ee1e45dc435',
					'min'          => 0,
					'max'          => 0,
					'layout'       => 'block',
					'button_label' => 'Add File',
					'sub_fields'   => array (
						array (
							'key'               => 'field_59ee1e45dc435',
							'label'             => 'Title',
							'name'              => 'title',
							'type'              => 'text',
							'value'             => NULL,
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array (
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'default_value' => '',
							'placeholder'   => '',
							'prepend'       => '',
							'append'        => '',
							'maxlength'     => '',
						),
						array (
							'key'               => 'field_59ee1e45dc463',
							'label'             => 'Description',
							'name'              => 'desc',
							'type'              => 'textarea',
							'value'             => NULL,
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array (
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'default_value' => '',
							'placeholder'   => '',
							'maxlength'     => '',
							'rows'          => 3,
							'new_lines'     => '',
						),
						array (
							'key'               => 'field_59ee1e45dc48e',
							'label'             => 'Image',
							'name'              => 'image',
							'type'              => 'image',
							'value'             => NULL,
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array (
								'width' => '30',
								'class' => '',
								'id'    => '',
							),
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'library'       => 'all',
							'min_width'     => '',
							'min_height'    => '',
							'min_size'      => '',
							'max_width'     => '',
							'max_height'    => '',
							'max_size'      => '',
							'mime_types'    => '',
						),
						array (
							'key'               => 'field_59ee1e45dc4b8',
							'label'             => 'File',
							'name'              => 'file',
							'type'              => 'file',
							'value'             => NULL,
							'instructions'      => '',
							'required'          => 1,
							'conditional_logic' => 0,
							'wrapper'           => array (
								'width' => '70',
								'class' => '',
								'id'    => '',
							),
							'return_format' => 'id',
							'library'       => 'all',
							'min_size'      => '',
							'max_size'      => '',
							'mime_types'    => '',
						),
					),
				),
			),
			'location'              => $this->get_metabox_post_types_config(),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => 1,
			'description'           => '',
		));
	}

	public function get_metabox_post_types_config() {
		$config     = '';
		$post_types = $this->get_metabox_post_types();
		if ( $post_types ) {
			$config = array();
			foreach ( $post_types as $post_type ) {
				$config[] = array( array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => $post_type,
				) );
			}
		}
		return $config;
	}

	public function get_metabox_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = apply_filters( 'wampum_protected_media_post_types', $post_types );
		return (array) $post_types;
	}
}

/**
 * The main function for that returns Wampum_Protected_Media
 *
 * The main function responsible for returning the one true Wampum_Protected_Media
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Wampum_Protected_Media(); ?>
 *
 * @since 1.0.0
 *
 * @return object|Wampum_Protected_Media The one true Wampum_Protected_Media Instance.
 */
function wampum_protected_media() {
	return Wampum_Protected_Media::instance();
}

// Get Wampum_Protected_Media Running.
wampum_protected_media();
