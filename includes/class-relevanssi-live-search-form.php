<?php
/**
 * The Relevanssi_Live_Search_Form class.
 *
 * @package Relevanssi Live Ajax Search
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Relevanssi_Live_Search_Form
 *
 * The Relevanssi Live Ajax Search search form and it's configuration.
 *
 * @since 1.0
 */
class Relevanssi_Live_Search_Form extends Relevanssi_Live_Search {
	/**
	 * The default configuration
	 *
	 * Developers can add their own configs using the
	 * relevanssi_live_search_configs filter which is applied at runtime.
	 * You are responsible for keeping the $configs array in tact, and either
	 * substituting your own customizations in the existing data, or adding
	 * your own by appending your own array key with values based on the
	 * default.
	 *
	 * To use: set the data-rlvconfig attribute value on your search form input
	 * to be the config you want to use.
	 *
	 * @since 1.0
	 *
	 * @var array All configurations available for use at runtime
	 */
	public $configs = array(
		'default' => array(                // 'default' config
			'input'   => array(
				'delay'     => 300,        // Impose delay (in milliseconds) before firing a search.
				'min_chars' => 3,          // Wait for at least 3 characters before triggering a search.
			),
			'results' => array(
				'position' => 'bottom',   // Where to position the results (bottom|top).
				'width'    => 'auto',     // Whether the width should automatically match the input (auto|css).
				'offset'   => array(
					'x' => 0,              // X offset (in pixels).
					'y' => 5,              // Y offset (in pixels).
				),
			),
			'spinner' => array( // Powered by https://spin.js.org/.
				'lines'     => 12,                                 // The number of lines to draw.
				'length'    => 8,                                  // The length of each line.
				'width'     => 3,                                  // The line thickness.
				'radius'    => 8,                                  // The radius of the inner circle.
				'scale'     => 1,                                  // Scales overall size of the spinner.
				'corners'   => 1,                                  // Corner roundness (0..1).
				'color'     => '#424242',                          // CSS color or array of colors.
				'fadeColor' => 'transparent',                      // CSS color or array of colors.
				'speed'     => 1,                                  // Rounds per second.
				'rotate'    => 0,                                  // The rotation offset.
				'animation' => 'relevanssi-spinner-line-fade-quick', // The CSS animation name for the lines.
				'direction' => 1,                                  // 1: clockwise, -1: counterclockwise.
				'zIndex'    => 2e9,                                // The z-index (defaults to 2000000000).
				'className' => 'spinner',                          // The CSS class to assign to the spinner.
				'top'       => '50%',                              // Top position relative to parent.
				'left'      => '50%',                              // Left position relative to parent.
				'shadow'    => '0 0 1px transparent',              // Box-shadow for the lines.
				'position'  => 'absolute',                         // Element positioning.
			),
		),
	);

	/**
	 * Equivalent of __construct() - implement our hooks
	 *
	 * @since 1.0
	 *
	 * @uses add_action() to trigger asset enqueue and output base styles in
	 * the footer.
	 * @uses add_filter() to filter search forms generated by get_search_form().
	 * @uses apply_filters() to ensure developer can filter the configs array
	 * via relevanssi_live_search_configs filter.
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'get_search_form', array( $this, 'get_search_form' ), 999, 1 );
		add_filter( 'render_block', array( $this, 'render_block' ), 999, 2 );
		add_action( 'wp_footer', array( $this, 'base_styles' ) );

		// Gutenberg integration.
		add_filter( 'wp_footer', array( $this, 'gutenberg_integration' ) );

		// The configs store all of the various configuration arrays that can
		// be used at runtime.
		$this->configs = apply_filters( 'relevanssi_live_search_configs', $this->configs );
	}

	/**
	 * Adds the search parameters to the core/search block.
	 *
	 * @param string $block_content The block HTML
	 */
	function render_block( $block_content, $block ) {
		if ( 'core/search' !== $block['blockName'] ) {
			return $block_content;
		}
		if ( ! apply_filters( 'relevanssi_live_search_hijack_get_search_form', true ) ) {
			return $block_content;
		}

		$config = apply_filters( 'relevanssi_live_search_get_search_form_config', 'default' );

		$block_content = str_replace( 'name="s"', 'name="s" data-rlvlive="true" data-rlvconfig="' . esc_attr( $config ) . '"', $block_content );
		return $block_content;
	}


	/**
	 * Adds Gutenberg variables.
	 */
	public function gutenberg_integration() {
		if ( apply_filters( 'relevanssi_live_search_hijack_search_form_block', true ) ) {
			$config = apply_filters( 'relevanssi_live_search_get_search_form_config', 'default' );

			// Allow for block-specific.
			$config = apply_filters( 'relevanssi_live_search_get_search_form_config_blocks', $config );

			?>
			<script>
			var _RELEVANSSI_LIVE_AJAX_SEARCH_BLOCKS = true;
			var _RELEVANSSI_LIVE_AJAX_SEARCH_CONFIG = '<?php echo esc_js( $config ); ?>';
			</script>
			<?php
		}
	}

	/**
	 * Register, localize, and enqueue all necessary JavaScript and CSS.
	 *
	 * @since 1.0
	 *
	 * @uses wp_enqueue_style() to enqueue CSS.
	 * @uses wp_enqueue_script() to enqueue JavaScript.
	 * @uses wp_register_script() to register JavaScript.
	 * @uses wp_localize_script() to pass PHP variables to JavaScript at
	 * runtime.
	 * @uses json_encode() to prepare the (potentially filtered) configs array.
	 */
	public function assets() {
		wp_enqueue_style( 'relevanssi-live-search', $this->url . '/assets/styles/style.css', null, $this->version );

		wp_enqueue_script( 'jquery' );

		// If WP is in script debug, or we pass ?script_debug in a URL, set
		// $debug to true.
		$debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) || ( isset( $_GET['script_debug'] ) ) ? '' : '.min';

		wp_register_script(
			'relevanssi-live-search-client',
			$this->url . "/assets/javascript/dist/script{$debug}.js",
			array( 'jquery' ),
			$this->version,
			true
		);

		$ajaxurl = admin_url( 'admin-ajax.php' );

		// Allow a direct search (e.g. avoid admin-ajax.php).
		if ( apply_filters( 'relevanssi_live_search_direct_search', false ) ) {
			$ajaxurl = trailingslashit( $this->url ) . 'direct.php';
		}

		// Set up our parameters.
		$params = array(
			'ajaxurl'             => esc_url( $ajaxurl ),
			'origin_id'           => get_queried_object_id(),
			'config'              => $this->configs,
			'msg_no_config_found' => __( 'No valid Relevanssi Live Search configuration found!', 'relevanssi-live-ajax-search' ),
			'aria_instructions'   => __( 'When autocomplete results are available use up and down arrows to review and enter to go to the desired page. Touch device users, explore by touch or with swipe gestures.' , 'relevanssi-live-ajax-search' ),
		);

		// We need to JSON encode the configs.
		$encoded_data = array(
			'l10n_print_after' => 'relevanssi_live_search_params = ' . wp_json_encode( $params ) . ';',
		);

		// Localize and enqueue the script with all of the variable goodness.
		wp_localize_script( 'relevanssi-live-search-client', 'relevanssi_live_search_params', $encoded_data );
		wp_enqueue_script( 'relevanssi-live-search-client' );
	}

	/**
	 * Callback to the get_search_form filter, allows us to automagically enable
	 * live search on form fields generated using get_search_form().
	 *
	 * @since 1.0
	 *
	 * @param string $html The generated markup for the search form.
	 *
	 * @uses apply_filters() to allow devs to disable this functionality.
	 * @uses apply_filters() to allow devs to set the default config to use.
	 * @uses str_replace() to inject our HTML5 data attributes where we want
	 * them.
	 * @uses esc_attr() to escape the search engine and config name.
	 *
	 * @return string Markup for the search form.
	 */
	public function get_search_form( string $html ) : string {
		if ( ! apply_filters( 'relevanssi_live_search_hijack_get_search_form', true ) ) {
			return $html;
		}

		$config = apply_filters( 'relevanssi_live_search_get_search_form_config', 'default' );
		// We're going to use 'name="s"' as our anchor for replacement.
		$html = str_replace( 'name="s"', 'name="s" data-rlvlive="true" data-rlvconfig="' . esc_attr( $config ) . '"', $html );

		return $html;
	}

	/**
	 * Output the base styles (absolutely minimal) necessary to properly set up
	 * the results wrapper.
	 *
	 * @since 1.0
	 *
	 * @uses apply_filters() to allow devs to disable this functionality.
	 */
	public function base_styles() {
		if ( apply_filters( 'relevanssi_live_search_base_styles', true ) ) {
			?>
				<style type="text/css">
					.relevanssi-live-search-results {
						opacity: 0;
						transition: opacity .25s ease-in-out;
						-moz-transition: opacity .25s ease-in-out;
						-webkit-transition: opacity .25s ease-in-out;
						height: 0;
						overflow: hidden;
						z-index: 9999995; /* Exceed SearchWP Modal Search Form overlay. */
						position: absolute;
						display: none;
					}

					.relevanssi-live-search-results-showing {
						display: block;
						opacity: 1;
						height: auto;
						overflow: auto;
					}

					.relevanssi-live-search-no-results {
						padding: 3em 2em 0;
						text-align: center;
					}

					.relevanssi-live-search-no-min-chars:after {
						content: "<?php echo esc_attr_e( 'Continue typing', 'relevanssi-live-ajax-search' ); ?>";
						display: block;
						text-align: center;
						padding: 2em 2em 0;
					}
				</style>
			<?php
		}
	}

}