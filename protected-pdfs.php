<?php
/**
 * Plugin Name:     Protected PDFs
 * Plugin URI:      https://bizbudding.com
 * Description:     Attach PDFs to pages/posts/cpts that can only be viewed from the pages they are attached to. Requires ACF Pro.
 * Version:         1.0.0
 *
 * Author:          BizBudding, Mike Hemberger
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Protected_PDFS' ) ) :

/**
 * Main Protected_PDFS Class.
 *
 * @since 1.0.0
 */
final class Protected_PDFS {

	/**
	 * @var Protected_PDFS The one true Protected_PDFS
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Main Protected_PDFS Instance.
	 *
	 * Insures that only one instance of Protected_PDFS exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   1.0.0
	 * @static  var array $instance
	 * @uses    Protected_PDFS::setup_constants() Setup the constants needed.
	 * @uses    Protected_PDFS::includes() Include the required files.
	 * @uses    Protected_PDFS::setup() Activate, deactivate, etc.
	 * @see     Protected_PDFS()
	 * @return  object | Protected_PDFS The one true Protected_PDFS
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup
			self::$instance = new Protected_PDFS;
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
		if ( ! defined( 'PROTECTED_PDFS_VERSION' ) ) {
			define( 'PROTECTED_PDFS_VERSION', '1.0.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'PROTECTED_PDFS_PLUGIN_DIR' ) ) {
			define( 'PROTECTED_PDFS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Includes Path
		if ( ! defined( 'PROTECTED_PDFS_INCLUDES_DIR' ) ) {
			define( 'PROTECTED_PDFS_INCLUDES_DIR', PROTECTED_PDFS_PLUGIN_DIR . 'includes/' );
		}

		// Plugin Folder URL.
		if ( ! defined( 'PROTECTED_PDFS_PLUGIN_URL' ) ) {
			define( 'PROTECTED_PDFS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'PROTECTED_PDFS_PLUGIN_FILE' ) ) {
			define( 'PROTECTED_PDFS_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'PROTECTED_PDFS_BASENAME' ) ) {
			define( 'PROTECTED_PDFS_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
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
		foreach ( glob( PROTECTED_PDFS_INCLUDES_DIR . '*.php' ) as $file ) { include $file; }
	}

	public function hooks() {
		register_activation_hook(   __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

		add_action( 'plugins_loaded',        array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'register_scripts' ) );
		add_action( 'after_setup_theme',     array( $this, 'image_size' ) );
		add_action( 'genesis_entry_content', array( $this, 'display' ) );
	}

	public function filters() {
		add_filter( 'acf/upload_prefilter/key=field_59ee1e45dc4b8', array( $this, 'upload_prefilter' ) );
		// add_filter( 'acf/prepare_field/key=field_59ee1e45dc4b8',    array( $this, 'field_display' ) ); // Not sure if this is needed.

	}

	public function activate() {
		flush_rewrite_rules();
	}

	public function init() {
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
		$updater = Puc_v4_Factory::buildUpdateChecker( 'https://github.com/maiprowp/mai-testimonials/', __FILE__, 'mai-testimonials' );
	}

	// Register scripts for later enqueue.
	public function register_scripts() {
		wp_register_style( 'protected-pdfs', PROTECTED_PDFS_PLUGIN_URL . 'assets/css/protected-pdfs.css', array(), PROTECTED_PDFS_VERSION );
		// wp_register_script( 'pdf-js',        PROTECTED_PDFS_PLUGIN_URL . 'assets/js/pdf.js',             array(),                             '1.9.659',              true );
		// wp_register_script( 'pdf-js-worker', PROTECTED_PDFS_PLUGIN_URL . 'assets/js/pdf.worker.js',      array( 'pdf-js' ),                   '1.9.659',              true );
		// wp_register_script( 'protected-pdfs', PROTECTED_PDFS_PLUGIN_URL . 'assets/js/protected-pdfs.js', array( 'pdf-js', 'pdf-js-worker' ), PROTECTED_PDFS_VERSION, true );
	}

	// Change the upload directory.
	public function upload_prefilter( $errors, $file, $field ) {
		add_filter( 'upload_dir', array( $this, 'upload_directory' ) );
		return $errors;
	}

	// Update paths accordingly before displaying link to file/
	public function field_display( $field ) {
		// get_home_path();
		add_filter( 'upload_dir', array( $this, 'upload_directory' ) );
		return $field;
	}

	public function upload_directory( $param ){
		$new_dir = '/protected_pdfs';
		$param['path'] = $param['basedir'] . $new_dir;
		$param['url']  = $param['baseurl'] . $new_dir;
		return $param;
	}

	public function image_size() {
		add_image_size( 'vertical-thumb', 150, 210, true );
	}

	public function display() {

		// Bail if not a single post.
		if ( ! is_singular() ) {
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

				// File URL.
				$file_url = wp_get_attachment_url( $item['file'] );
				$file_url = esc_url( sprintf( '%spdfjs/web/viewer.html?file=%s', PROTECTED_PDFS_PLUGIN_URL, $file_url ) );

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
							'mime_types'    => 'pdf',
						),
					),
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
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

}
endif; // End if class_exists check.

/**
 * The main function for that returns Protected_PDFS
 *
 * The main function responsible for returning the one true Protected_PDFS
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Protected_PDFS(); ?>
 *
 * @since 1.0.0
 *
 * @return object|Protected_PDFS The one true Protected_PDFS Instance.
 */
function Protected_PDFS() {
	return Protected_PDFS::instance();
}

// Get Protected_PDFS Running.
Protected_PDFS();
