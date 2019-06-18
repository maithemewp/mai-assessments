<?php

/**
 * Plugin Name:     Mai Assessments
 * Plugin URI:      https://maitheme.com
 * Description:     Assessment management, scores, and results via WP Forms and ACF Pro.
 * Version:         0.1.1
 *
 * Author:          BizBudding, Mike Hemberger
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Mai_Assessments_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_Assessments_Plugin {

	/**
	 * @var   Mai_Assessments_Plugin The one true Mai_Assessments_Plugin
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_Assessments_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_Assessments_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_Assessments_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_Assessments_Plugin::includes() Include the required files.
	 * @uses    Mai_Assessments_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_Assessments_Plugin()
	 * @return  object | Mai_Assessments_Plugin The one true Mai_Assessments_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup
			self::$instance = new Mai_Assessments_Plugin;
			// Methods
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-assessments' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-assessments' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'MAI_ASSESSMENTS_VERSION' ) ) {
			define( 'MAI_ASSESSMENTS_VERSION', '0.1.1' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_ASSESSMENTS_PLUGIN_DIR' ) ) {
			define( 'MAI_ASSESSMENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Includes Path.
		if ( ! defined( 'MAI_ASSESSMENTS_INCLUDES_DIR' ) ) {
			define( 'MAI_ASSESSMENTS_INCLUDES_DIR', MAI_ASSESSMENTS_PLUGIN_DIR . 'includes/' );
		}

		// Plugin Folder URL.
		if ( ! defined( 'MAI_ASSESSMENTS_PLUGIN_URL' ) ) {
			define( 'MAI_ASSESSMENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'MAI_ASSESSMENTS_PLUGIN_FILE' ) ) {
			define( 'MAI_ASSESSMENTS_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'MAI_ASSESSMENTS_BASENAME' ) ) {
			define( 'MAI_ASSESSMENTS_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
		}

	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function includes() {
		// Include vendor libraries.
		require_once __DIR__ . '/vendor/autoload.php';
		// Includes.
		foreach ( glob( MAI_ASSESSMENTS_INCLUDES_DIR . '*.php' ) as $file ) { include $file; }
	}

	/**
	 * Run the hooks.
	 *
	 * @since   0.1.0
	 * @return  void
	 */
	public function hooks() {

		add_action( 'admin_init', array( $this, 'updater' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_settings_link' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @uses    https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return  void
	 */
	public function updater() {

		// Bail if current user cannot manage plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'Puc_v4_Factory' ) ) {
			return;
		}

		// Setup the updater.
		$updater = Puc_v4_Factory::buildUpdateChecker( 'https://github.com/maithemewp/mai-assessments/', __FILE__, 'mai-assessments' );
	}

	function plugin_settings_link( $actions ) {
		$actions[] = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=assessment-results' ), __( 'Settings', 'mai-assessments' ) );
		return $actions;
	}

}

/**
 * The main function for that returns Mai_Assessments_Plugin
 *
 * The main function responsible for returning the one true Mai_Assessments_Plugin
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Mai_Assessments_Plugin(); ?>
 *
 * @since 0.1.0
 *
 * @return object|Mai_Assessments_Plugin The one true Mai_Assessments_Plugin Instance.
 */
function Mai_Assessments_Plugin() {
	return Mai_Assessments_Plugin::instance();
}

// Get Mai_Assessments_Plugin Running.
Mai_Assessments_Plugin();
