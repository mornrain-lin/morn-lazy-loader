<?php
/**
 * Plugin Name: Morn Lazy Loader
 * Plugin URI: https://github.com/mornrain-lin/morn-lazy-loader
 * Description: 图片懒加载与占位符插件。为 img、iframe、video poster 及 CSS background-image 注入原生懒加载，支持模糊微缩图（LQIP）、纯色与 Skeleton 三种占位符，基于 IntersectionObserver 延迟加载并带降级方案，内置流量节省统计面板。零外部资源。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * Author: MornRain
 * Author URI: https://github.com/mornrain-lin
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: morn-lazy-loader
 * Domain Path: /languages
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 插件版本。
 */
define( 'MORN_LAZY_LOADER_VERSION', '1.0.0' );

/**
 * 主文件路径。
 */
define( 'MORN_LAZY_LOADER_FILE', __FILE__ );

/**
 * 插件目录路径。
 */
define( 'MORN_LAZY_LOADER_DIR', plugin_dir_path( __FILE__ ) );

/**
 * 插件目录 URL。
 */
define( 'MORN_LAZY_LOADER_URL', plugin_dir_url( __FILE__ ) );

/**
 * 设置选项名。
 */
define( 'MORN_LAZY_LOADER_OPTION', 'morn_lazy_loader_settings' );

/**
 * 统计数据选项名。
 */
define( 'MORN_LAZY_LOADER_STATS', 'morn_lazy_loader_stats' );

require_once MORN_LAZY_LOADER_DIR . 'includes/functions.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-settings.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-stats.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-placeholder.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-observer.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-assets.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-lazy.php';
require_once MORN_LAZY_LOADER_DIR . 'includes/class-plugin.php';

/**
 * 启动插件。
 *
 * @return void
 */
function morn_lazy_loader_boot() {
	load_plugin_textdomain( 'morn-lazy-loader', false, dirname( plugin_basename( MORN_LAZY_LOADER_FILE ) ) . '/languages' );

	Morn_Lazy_Loader_Plugin::init();
}
add_action( 'plugins_loaded', 'morn_lazy_loader_boot' );

/**
 * 激活钩子。
 *
 * @return void
 */
function morn_lazy_loader_activate() {
	Morn_Lazy_Loader_Stats::seed();
}
register_activation_hook( __FILE__, 'morn_lazy_loader_activate' );

/**
 * 停用钩子：清理统计数据。
 *
 * @return void
 */
function morn_lazy_loader_deactivate() {
	wp_clear_scheduled_hook( 'morn_lazy_loader_daily_cleanup' );
	delete_option( MORN_LAZY_LOADER_STATS );
}
register_deactivation_hook( __FILE__, 'morn_lazy_loader_deactivate' );
