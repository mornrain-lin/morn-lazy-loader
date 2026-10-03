<?php
/**
 * 插件启动与 HTML 挂载点。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 协调各模块并挂载前台 HTML 过滤器。
 */
class Morn_Lazy_Loader_Plugin {

	/**
	 * 懒加载处理器实例。
	 *
	 * @var Morn_Lazy_Loader_Lazy|null
	 */
	private static $handler = null;

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		Morn_Lazy_Loader_Settings::init();
		Morn_Lazy_Loader_Stats::init();
		Morn_Lazy_Loader_Assets::init();
		Morn_Lazy_Loader_Observer::init();

		if ( ! morn_lazy_loader_get_setting( 'enabled' ) ) {
			return;
		}

		// 优先级 9999，确保在其它插件与主题输出之后执行。
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 9999 );
		add_filter( 'the_content_feed', array( __CLASS__, 'filter_content' ), 9999 );
		add_filter( 'comment_text', array( __CLASS__, 'filter_content' ), 9999 );

		add_action( 'wp_footer', array( __CLASS__, 'output_data_attr' ), 5 );
	}

	/**
	 * 过滤正文内容。
	 *
	 * @param string $content 内容 HTML。
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		if ( ! self::should_process() ) {
			return $content;
		}

		return self::get_handler()->process( $content );
	}

	/**
	 * 是否应处理当前内容。
	 *
	 * @return bool
	 */
	private static function should_process() {
		if ( is_feed() ) {
			return false;
		}

		/**
		 * 过滤是否对当前内容执行懒加载处理。
		 *
		 * @param bool   $process 是否处理。
		 * @param string $content 内容 HTML。
		 */
		return (bool) apply_filters( 'morn_lazy_loader_should_process', true, $content );
	}

	/**
	 * 获取处理器单例。
	 *
	 * @return Morn_Lazy_Loader_Lazy
	 */
	private static function get_handler() {
		if ( null === self::$handler ) {
			self::$handler = new Morn_Lazy_Loader_Lazy();
		}

		return self::$handler;
	}

	/**
	 * 输出本页字节估算的 data 属性，供前端统计使用。
	 *
	 * @return void
	 */
	public static function output_data_attr() {
		if ( ! morn_lazy_loader_get_setting( 'enabled' ) || is_admin() ) {
			return;
		}

		if ( ! morn_lazy_loader_in_scope() || ! morn_lazy_loader_get_setting( 'stats_enabled' ) ) {
			return;
		}

		$bytes = self::get_handler()->get_total_bytes();

		if ( $bytes <= 0 ) {
			return;
		}

		printf(
			'<div class="morn-ll-stats-data" data-total-bytes="%d" hidden aria-hidden="true"></div>',
			(int) $bytes
		);
	}
}
