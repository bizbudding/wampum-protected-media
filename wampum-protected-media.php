<?php
/**
 * Plugin Name:       Wampum - Protected Media
 * Plugin URI:        https://bizbudding.com
 * Description:       Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to (via PDF.js). Requires Genesis for file display and ACF Pro for the files metabox.
 * Version:           1.2.0
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
			define( 'WAMPUM_PROTECTED_MEDIA_VERSION', '1.2.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_DIR' ) ) {
			define( 'WAMPUM_PROTECTED_MEDIA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Includes Path
		// if ( ! defined( 'WAMPUM_PROTECTED_MEDIA_INCLUDES_DIR' ) ) {
		// 	define( 'WAMPUM_PROTECTED_MEDIA_INCLUDES_DIR', WAMPUM_PROTECTED_MEDIA_PLUGIN_DIR . 'includes/' );
		// }

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
		// foreach ( glob( WAMPUM_PROTECTED_MEDIA_INCLUDES_DIR . '*.php' ) as $file ) { include $file; }
	}

	public function hooks() {
		register_activation_hook(   __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

		add_action( 'plugins_loaded',        array( $this, 'updater' ), 12 );
		add_action( 'init',                  array( $this, 'field_group' ) );
		add_action( 'admin_init',            array( $this, 'create_protection_files' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'register_scripts' ) );
		add_action( 'genesis_entry_content', array( $this, 'display' ), 20 );
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
	 * Creates blank index.php and .htaccess files
	 *
	 * This function runs approximately once per month in order to ensure all folders
	 * have their necessary protection files.
	 *
	 * Take from edd_create_protection_files() in https://github.com/easydigitaldownloads/easy-digital-downloads/blob/master/includes/admin/upload-functions.php.
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
			// Top level .htaccess file
			$rules = $this->get_htaccess_rules();
			if ( $this->htaccess_exists() ) {
				$contents = @file_get_contents( $upload_path . '/.htaccess' );
				if ( $contents !== $rules || ! $contents ) {
					// Update the .htaccess rules if they don't match
					@file_put_contents( $upload_path . '/.htaccess', $rules );
				}
			} elseif( wp_is_writable( $upload_path ) ) {
				// Create the file if it doesn't exist
				@file_put_contents( $upload_path . '/.htaccess', $rules );
			}
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

	/**
	 * Retrieve the .htaccess rules to wp-content/uploads/edd/
	 *
	 * @return mixed|void The htaccess rules
	 */
	function get_htaccess_rules() {
		$rules = '';
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteCond %{HTTP_REFERER} !^" . trailingslashit( home_url() ) . ".*$ [NC]\n";
		$rules .= "RewriteRule .* - [NC,L,F]\n";
		return $rules;
	}

	/**
	 * Checks if the .htaccess file exists in wp-content/uploads/edd
	 *
	 * @return bool
	 */
	function htaccess_exists() {
		$upload_path = $this->get_upload_dir();
		return file_exists( $upload_path . '/.htaccess' );
	}

	// Register scripts for later enqueue.
	public function register_scripts() {
		wp_register_style( 'wampum-protected-media', WAMPUM_PROTECTED_MEDIA_PLUGIN_URL . 'assets/css/wampum-protected-media.css', array(), WAMPUM_PROTECTED_MEDIA_VERSION );
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

		// Get items in the field group.
		$items = $this->get_field( get_the_ID(), $this->field_group_config() );

		// Bail if no items.
		if ( ! $items ) {
			return;
		}

		/**
		 * Get items.
		 * The initial $items array was the entire field group,
		 * this grabs only the repeater field.
		 */
		$items = $items[$this->key_name];

		// Bail if no items.
		if ( ! $items ) {
			return;
		}

		// Enqueue styles and scripts.
		// wp_enqueue_script( 'pdfjs', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs', [ 'jquery' ], '4.10.38', true );
		// wp_enqueue_script( 'pdfjs-worker', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs', [], '4.10.38', true );
		// wp_enqueue_style( 'pdfjs', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf_viewer.min.css', [ 'wampum-protected-media' ], '4.10.38' );
		wp_enqueue_style( 'wampum-protected-media' );

		echo '<ul id="wpm-list" style="margin-left:0;">';

			echo '<li class="wpm-row">';
				echo '<span class="wpm-header">Files</span>';
			echo '</li>';

			$has_pdf    = false;
			$viewer_url = esc_url( sprintf( '%spdfjs/web/viewer.html?file=', WAMPUM_PROTECTED_MEDIA_PLUGIN_URL ) );

			foreach ( $items as $item ) {

				// Skip if no file.
				if ( ! $item['file'] ) {
					continue;
				}

				$is_pdf = false;

				// File URL.
				$direct_url = wp_get_attachment_url( $item['file'] );
				$ext        = pathinfo( $direct_url, PATHINFO_EXTENSION );

				// This file is a PDF.
				if ( 'pdf' === $ext ) {
					// This list has a pdf.
					if ( ! $has_pdf ) {
						$has_pdf = true;
					}
					$is_pdf    = true;
					$file_url  = esc_url( $direct_url );
				} else {
					$file_url = $direct_url;
				}

				// Encode.
				$file_url = base64_encode( esc_url( $file_url ) );

				// Maybe add launcher class to the item.
				$launcher_class = '';
				if ( $is_pdf ) {
					$launcher_class = ' wpm-pdf-launcher';
				} else {
					$launcher_class = ' wpm-launcher';
				}

				// Image.
				$image = sprintf( '<span class="wpm-cell wpm-image%s"></span>', $launcher_class );
				if ( $item['image'] ) {
					$image_size = apply_filters( 'wampum_protected_media_image_size', 'thumbnail' );
					$image      = sprintf( '<a href="%s" class="wpm-cell wpm-image%s">%s</a>',
						$file_url,
						$launcher_class,
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
				$title = sprintf( '<span class="wpm-title"><a href="%s" class="%s">%s</a> (%s)</span>', $file_url, trim( $launcher_class ), $title, $ext );

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

				// Actions/buttons.
				$button_text = __( 'View', 'wampum-protected-media' );
				if ( 'zip' === $ext ) {
					$button_text = __( 'Download', 'wampum-protected-media' );
				}
				$actions = sprintf( '<span class="wpm-cell wpm-auto wpm-actions"><a href="%s" class="wpm-button more-link%s">%s</a></span>', $file_url, $launcher_class, $button_text );

				// Output the row.
				printf( '<li class="wpm-row">%s%s%s</li>', $image, $content, $actions );

			}

			if ( $has_pdf ) {
				// Add the overlay to the footer.
				add_action( 'wp_footer', function() {
					?>
					<div id="wpm-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;border:none;z-index:100000;">
						<button id="wpm-close" style="position:absolute;top:36px;right:20px;z-index:100001;"><span class="screen-reader-text">Close</span></button>
						<iframe
							id="wpm-iframe"
							src=""
							type="application/pdf"
							width="100%"
							height="100%"
							style="border:none;"
							sandbox="allow-scripts allow-same-origin"
						></iframe>
					</div>
					<script type="text/javascript">
						document.addEventListener('DOMContentLoaded', function() {
							const list    = document.getElementById('wpm-list');
							const overlay = document.getElementById('wpm-overlay');
							const iframe  = document.getElementById('wpm-iframe');
							list.addEventListener('click', function(e) {
								if (e.target.closest('.wpm-pdf-launcher')) {
									e.preventDefault();
									const launcher = e.target.closest('.wpm-pdf-launcher');
									iframe.src = 'https://docs.google.com/gview?url=' + window.atob(launcher.getAttribute('href')) + '&embedded=true';
									overlay.style.display = 'block';
									document.documentElement.style.overflow = 'hidden';
								}
							});
							overlay.addEventListener('click', function(e) {
								if (e.target.matches('#wpm-close')) {
									overlay.style.display = 'none';
									iframe.src = '';
									document.documentElement.style.overflow = '';
								}
							});
						});
					</script>
					<?php
				});
			}
		echo '</ul>';
	}

	/**
	 * Retrieves all post meta data according to the structure in the $config
	 * array.
	 *
	 * Provides a convenient and more performant alternative to ACF's
	 * `get_field()`.
	 *
	 * This function is especially useful when working with ACF repeater fields and
	 * flexible content layouts.
	 *
	 * @link    https://www.timjensen.us/acf-get-field-alternative/
	 * @link    https://gist.github.com/timothyjensen/eec64d73f2a44d8b38a078e05abfad4b
	 *
	 * @version 1.2.5
	 *
	 * @param   integer  $post_id  Required. Post ID.
	 * @param   array    $config   Required. An array that represents the structure of
	 *                             the custom fields. Follows the same format as the
	 *                             ACF export field groups array.
	 * @return array
	 */
	public function get_field( $post_id, array $config ) {
		$results = array();
		foreach ( $config as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}
			$meta_key = $field['name'];
			if ( isset( $field['meta_key_prefix'] ) ) {
				$meta_key = $field['meta_key_prefix'] . $meta_key;
			}
			$field_value = get_post_meta( $post_id, $meta_key, true );
			if ( isset( $field['layouts'] ) ) { // We're dealing with flexible content layouts.
				if ( empty( $field_value ) ) {
					continue;
				}
				// Build a keyed array of possible layout types.
				$layout_types = [];
				foreach ( $field['layouts'] as $key => $layout_type ) {
					$layout_types[ $layout_type['name'] ] = $layout_type;
				}
				foreach ( $field_value as $key => $current_layout_type ) {
					$new_config = $layout_types[ $current_layout_type ]['sub_fields'];
					if ( empty( $new_config ) ) {
						continue;
					}
					foreach ( $new_config as &$field_config ) {
						$field_config['meta_key_prefix'] = $meta_key . "_{$key}_";
					}
					$results[ $field['name'] ][] = array_merge(
						[
							'acf_fc_layout' => $current_layout_type,
						],
						$this->get_field( $post_id, $new_config ) // Recursive!!!!
					);
				}
			} elseif ( isset( $field['sub_fields'] ) ) { // We're dealing with repeater fields.
				if ( empty( $field_value ) ) {
					continue;
				}
				for ( $i = 0; $i < $field_value; $i ++ ) {
					$new_config = $field['sub_fields'];
					if ( empty( $new_config ) ) {
						continue;
					}
					foreach ( $new_config as &$field_config ) {
						$field_config['meta_key_prefix'] = $meta_key . "_{$i}_";
					}
					$results[ $field['name'] ][] = $this->get_field( $post_id, $new_config ); // Recursive!!!!
				}
			} else {
				$results[ $field['name'] ] = $field_value;
			} // End if().
		} // End foreach().
		return $results;
	}

	public function field_group_config() {
		return array(
			array (
				'name'       => $this->key_name,
				'sub_fields' => array (
					array (
						'name' => 'title',
					),
					array (
						'name' => 'desc',
					),
					array (
						'name' => 'image',
					),
					array (
						'name' => 'file',
					),
				),
			),
		);
	}

	public function field_group() {

		if ( ! function_exists('acf_add_local_field_group') ) {
			return;
		}

		acf_add_local_field_group(array (
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
