<?php
/**
 * Plugin Name:       Protected PDFs
 * Plugin URI:        https://bizbudding.com
 * Description:       Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to (via PDF.js). Requires Genesis for file display and ACF Pro for the files metabox.
 * Version:           1.0.1
 *
 * Author:            BizBudding, Mike Hemberger
 * Author URI:        https://bizbudding.com
 *
 * GitHub Plugin URI: bizbudding/protected-pdfs
 * GitHub Plugin URI: https://github.com/bizbudding/protected-pdfs
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'PPDFS' ) ) :

/**
 * Main PPDFS Class.
 *
 * @since 1.0.0
 */
final class PPDFS {

	/**
	 * @var PPDFS The one true PPDFS
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Main PPDFS Instance.
	 *
	 * Insures that only one instance of PPDFS exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   1.0.0
	 * @static  var array $instance
	 * @uses    PPDFS::setup_constants() Setup the constants needed.
	 * @uses    PPDFS::includes() Include the required files.
	 * @uses    PPDFS::setup() Activate, deactivate, etc.
	 * @see     ppdfs()
	 * @return  object | PPDFS The one true PPDFS
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup
			self::$instance = new PPDFS;
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'protected-pdfs' ), '1.0' );
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'protected-pdfs' ), '1.0' );
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
		if ( ! defined( 'PPDFS_VERSION' ) ) {
			define( 'PPDFS_VERSION', '1.0.1' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'PPDFS_PLUGIN_DIR' ) ) {
			define( 'PPDFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Includes Path
		// if ( ! defined( 'PPDFS_INCLUDES_DIR' ) ) {
		// 	define( 'PPDFS_INCLUDES_DIR', PPDFS_PLUGIN_DIR . 'includes/' );
		// }

		// Plugin Folder URL.
		if ( ! defined( 'PPDFS_PLUGIN_URL' ) ) {
			define( 'PPDFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'PPDFS_PLUGIN_FILE' ) ) {
			define( 'PPDFS_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'PPDFS_BASENAME' ) ) {
			define( 'PPDFS_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
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
		// foreach ( glob( PPDFS_INCLUDES_DIR . '*.php' ) as $file ) { include $file; }
	}

	public function hooks() {
		register_activation_hook(   __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

		add_action( 'plugins_loaded',        array( $this, 'init' ) );
		add_action( 'admin_init',            array( $this, 'create_protection_files' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'register_scripts' ) );
		add_action( 'after_setup_theme',     array( $this, 'image_size' ) );
		add_action( 'genesis_entry_content', array( $this, 'display' ) );
	}

	public function activate() {
		flush_rewrite_rules();
	}

	public function filters() {
		add_filter( 'acf/upload_prefilter/key=field_59ee1e45dc4b8', array( $this, 'upload_prefilter' ) );
		add_filter( 'acf/validate_value/key=field_59ee1e45dc4b8',   array( $this, 'validate_value' ), 10, 4 );
	}

	public function init() {
		$this->field_group();
		/**
		 * Setup the updater.
		 *
		 * @uses    https://github.com/YahnisElsts/plugin-update-checker/
		 *
		 * @return  void
		 */
		if ( ! class_exists( 'Puc_v4_Factory' ) ) {
			require_once MAI_FAVORITES_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
		}
		$updater = Puc_v4_Factory::buildUpdateChecker( 'https://github.com/bizbudding/protected-pdfs/', __FILE__, 'protected-pdfs' );
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
		if ( false === get_transient( 'ppdfs_check_protection_files' ) || $force ) {
			$upload_path = $this->get_upload_dir();
			// Make sure the /protected-pdfs/ folder is created
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
			set_transient( 'ppdfs_check_protection_files', true, 3600 * 24 );
		}
	}

	/**
	 * Retrieve the absolute path to the file upload directory without the trailing slash
	 *
	 * @since  1.8
	 * @return string $path Absolute path to the EDD upload directory
	 */
	function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();
		wp_mkdir_p( $wp_upload_dir['basedir'] . '/protected_pdfs' );
		$path = $wp_upload_dir['basedir'] . '/protected_pdfs';
		return $path;
	}

	/**
	 * Retrieve the .htaccess rules to wp-content/uploads/edd/
	 *
	 * @since 1.6
	 *
	 * @return mixed|void The htaccess rules
	 */
	function get_htaccess_rules() {
		$rules = '';
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteCond %{HTTP_REFERER} !^" . trailingslashit( home_url() ) . ".*$ [NC]\n";
		$rules .= "RewriteRule \.(pdf)$ - [NC,L,F]\n";
		return $rules;
	}

	/**
	 * Checks if the .htaccess file exists in wp-content/uploads/edd
	 *
	 * @since 1.8
	 * @return bool
	 */
	function htaccess_exists() {
		$upload_path = $this->get_upload_dir();
		return file_exists( $upload_path . '/.htaccess' );
	}

	// Register scripts for later enqueue.
	public function register_scripts() {
		wp_register_style( 'protected-pdfs', PPDFS_PLUGIN_URL . 'assets/css/protected-pdfs.css', array(), PPDFS_VERSION );
	}

	// Change the upload directory.
	public function upload_prefilter( $errors, $file, $field ) {
		add_filter( 'upload_dir',  array( $this, 'upload_directory' ) );
		return $errors;
	}

	public function upload_directory( $param ){
		$new_dir = '/protected_pdfs';
		$param['path'] = $param['basedir'] . $new_dir;
		$param['url']  = $param['baseurl'] . $new_dir;
		return $param;
	}

	public function validate_value( $valid, $value, $field, $input ){
		// Bail if already invalid.
		if( ! $valid ) {
			return $valid;
		}
		// Get the file URL.
		$file = wp_get_attachment_url( $value );
		// If the file doesn't contain the 'protected_pdfs' directory, it's not protected.
		if ( false === strpos( $file, 'protected_pdfs' ) ) {
			// Error message.
			$valid = 'This PDF is not in the protected_pdfs directory and may not be protected. Please upload a new file or choose one from the protected_pdfs directory.';
		}
		return $valid;
	}

	public function image_size() {
		add_image_size( 'vertical-thumb', 150, 210, true );
	}

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
		$items = $items['protected_pdfs'];

		// Bail if no items.
		if ( ! $items ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style( 'protected-pdfs' );

		echo '<ul id="ppdf-list" style="margin-left:0;">';

			echo '<li class="ppdf-row">';
				echo '<span class="ppdf-header">Files</span>';
			echo '</li>';

			foreach ( $items as $item ) {

				// Skip if no file.
				if ( ! $item['file'] ) {
					continue;
				}

				// File URL.
				$direct_url = wp_get_attachment_url( $item['file'] );
				$file_url   = esc_url( sprintf( '%spdfjs/web/viewer.html?file=%s', PPDFS_PLUGIN_URL, $direct_url ) );

				// Image.
				$image = '<span class="ppdf-cell ppdf-image ppdf-launcher"></span>';
				if ( $item['image'] ) {
					$image = sprintf( '<a href="#" ppdf="%s" class="ppdf-cell ppdf-image ppdf-launcher">%s</a>',
						$file_url,
						wp_get_attachment_image( $item['image'], 'vertical-thumb' )
					);
				}

				// Title.
				$title = '';
				if ( $item['title'] ) {
					$title = sprintf( '<span class="ppdf-title"><a href="#" ppdf="%s" class="ppdf-launcher">%s</a></span>',
						esc_attr( $item['title'] ),
						esc_html( $item['title'] )
					);
				} else {
					// Use filename as title.
					$title = basename( $direct_url );
				}

				// Description.
				$desc = '';
				if ( $item['desc'] ) {
					$desc = sprintf( '<span class="ppdf-desc">%s</span>',
						wp_kses_post( $item['desc'] )
					);
				}

				// Content: Title and/or Desc.
				$content = sprintf( '<span class="ppdf-cell ppdf-grow ppdf-content">%s</span>',
					$title . $desc
				);

				// Actions/buttons.
				$actions = '<span class="ppdf-cell ppdf-auto ppdf-actions"><button class="ppdf-button ppdf-launcher more-link" ppdf="' . $file_url . '">View</button></span>';

				// Output the row.
				echo '<li class="ppdf-row">' . $image . $content . $actions . '</li>';

			}

			echo '<div style="display:none;" id="ppdf-viewer">
					<div class="ppdf-close-bar"><button id="ppdf-close"><span class="screen-reader-text">Close</span></button></div>
					<iframe width="100%" height="100%" src="' . $file_url . '"></iframe>
				</div>';

			echo "<script type='text/javascript'>
				(function($) {
					$( '#ppdf-list' ).on( 'click', '.ppdf-launcher', function(e) {
						e.preventDefault();
						var src    = $(this).attr( 'ppdf' );
						var viewer = $( '#ppdf-viewer' );
						viewer.find( 'iframe' ).src = src;
						viewer.show();
						$(document).keydown(function(e) {
							switch(e.which) {
								case 27: // esc key.
								viewer.hide();
								break;
								default: return;
							}
						});
						viewer.on( 'click', '#ppdf-close', function(f) {
							viewer.hide();
						});
					});
				})(jQuery);
			</script>";

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
				'name'       => 'protected_pdfs',
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
			'title'  => 'Protected PDFs',
			'fields' => array (
				array (
					'key'               => 'field_59ee1e45d9126',
					'label'             => 'PDFs',
					'name'              => 'protected_pdfs',
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
					'button_label' => 'Add PDF',
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
							'preview_size'  => 'vertical-thumb',
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
							'mime_types'    => 'pdf',
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
		$post_types = apply_filters( 'ppdfs_post_types', $post_types );
		return (array) $post_types;
	}

}
endif; // End if class_exists check.

/**
 * The main function for that returns PPDFS
 *
 * The main function responsible for returning the one true PPDFS
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = PPDFS(); ?>
 *
 * @since 1.0.0
 *
 * @return object|PPDFS The one true PPDFS Instance.
 */
function ppdfs() {
	return PPDFS::instance();
}

// Get PPDFS Running.
ppdfs();
