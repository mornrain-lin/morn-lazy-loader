<?php
/**
 * 前台观察器脚本输出。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 输出 IntersectionObserver 懒加载脚本（原生 JS，含降级）。
 */
class Morn_Lazy_Loader_Observer {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		// 必须在脚本被打印之前附加配置，因此挂在 wp_enqueue_scripts 阶段。
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'render' ), 22 );
	}

	/**
	 * 输出内联脚本。
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! morn_lazy_loader_get_setting( 'enabled' ) || ! morn_lazy_loader_in_scope() ) {
			return;
		}

		// 脚本未注册时 wp_add_inline_script 无效，直接跳过。
		if ( ! wp_script_is( Morn_Lazy_Loader_Assets::HANDLE, 'registered' )
			&& ! wp_script_is( Morn_Lazy_Loader_Assets::HANDLE, 'enqueued' ) ) {
			return;
		}

		$settings = array(
			'rootMargin' => (int) morn_lazy_loader_get_setting( 'root_margin', 200 ) . 'px',
			'background' => (bool) morn_lazy_loader_get_setting( 'background_images', 0 ),
			'stats'      => (bool) morn_lazy_loader_get_setting( 'stats_enabled', 1 ),
		);

		/**
		 * 过滤传给前端脚本的配置。
		 *
		 * @param array $settings 前端配置。
		 */
		$settings = apply_filters( 'morn_lazy_loader_script_config', $settings );

		$json = wp_json_encode( $settings );

		if ( false === $json ) {
			return;
		}

		wp_add_inline_script(
			Morn_Lazy_Loader_Assets::HANDLE,
			'window.mornLazyLoaderConfig = ' . $json . ';',
			'before'
		);
	}
}
