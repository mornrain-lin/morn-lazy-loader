<?php
/**
 * 前台资源加载。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册并输出前台 CSS/JS。
 */
class Morn_Lazy_Loader_Assets {

	/**
	 * 脚本句柄。
	 */
	const HANDLE = 'morn-lazy-loader';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		// 统计配置紧随脚本注册之后附加，确保内联数据在脚本之前输出。
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_stats_config' ), 21 );
		add_action( 'wp_head', array( __CLASS__, 'print_style' ), 8 );
		// 仅注册登录态处理器：未登录用户的 nonce 可被公开推导，
		// 若开放 nopriv 会变成匿名刷写 option 的入口。
		add_action( 'wp_ajax_morn_lazy_loader_stats', array( __CLASS__, 'handle_stats_ajax' ) );
	}

	/**
	 * 判断当前是否应输出懒加载资源。
	 *
	 * @return bool
	 */
	private static function should_load() {
		return (bool) morn_lazy_loader_get_setting( 'enabled' ) && morn_lazy_loader_in_scope();
	}

	/**
	 * 注册前端资源。
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::should_load() || is_admin() ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			MORN_LAZY_LOADER_URL . 'assets/js/lazy-loader.js',
			array(),
			MORN_LAZY_LOADER_VERSION,
			true
		);

		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * 附加统计上报所需的 AJAX 配置。
	 *
	 * 使用 wp_add_inline_script + wp_json_encode，避免 wp_localize_script
	 * 把所有值强制转为字符串（布尔与数字会丢失类型）。
	 *
	 * @return void
	 */
	public static function add_stats_config() {
		if ( ! self::should_load() || is_admin() ) {
			return;
		}

		if ( ! wp_script_is( self::HANDLE, 'registered' ) && ! wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		if ( ! morn_lazy_loader_get_setting( 'stats_enabled' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'morn_lazy_loader_stats' ),
		);

		$json = wp_json_encode( $config );

		if ( false === $json ) {
			return;
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.mornLazyLoaderStats = ' . $json . ';',
			'before'
		);
	}

	/**
	 * 生成内联占位样式。
	 *
	 * @return string
	 */
	private static function inline_style() {
		$mode = (string) morn_lazy_loader_get_setting( 'placeholder', 'lqip' );

		$css = '.morn-ll-lqip{background-repeat:no-repeat;transition:filter .35s ease,transform .35s ease;}' . "\n";
		$css .= '.morn-ll-lqip.morn-ll-loaded{filter:none;transform:none;}' . "\n";
		$css .= '.morn-ll-skeleton{animation:morn-ll-shimmer 1.4s linear infinite;background-repeat:no-repeat;}' . "\n";
		$css .= '@keyframes morn-ll-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}' . "\n";
		$css .= '.morn-ll-lazy{transition:opacity .3s ease;}' . "\n";
		$css .= '.morn-ll-lazy:not([data-morn-loaded]){opacity:0;}' . "\n";
		$css .= '[data-morn-bg]{min-height:1px;}' . "\n";

		if ( 'none' === $mode ) {
			$css .= '.morn-ll-lqip,.morn-ll-skeleton{animation:none;filter:none;transform:none;}' . "\n";
		}

		/**
		 * 过滤懒加载内联样式。
		 *
		 * @param string $css  CSS 文本。
		 * @param string $mode 占位符模式。
		 */
		return apply_filters( 'morn_lazy_loader_inline_style', $css, $mode );
	}

	/**
	 * 直接在 head 输出关键样式，避免额外请求。
	 *
	 * @return void
	 */
	public static function print_style() {
		if ( ! self::should_load() || is_admin() ) {
			return;
		}

		$css = self::inline_style();

		if ( '' === $css ) {
			return;
		}

		echo '<style id="morn-lazy-loader-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
	}

	/**
	 * 处理统计上报 AJAX。
	 *
	 * @return void
	 */
	public static function handle_stats_ajax() {
		check_ajax_referer( 'morn_lazy_loader_stats', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array( 'message' => __( '校验失败。', 'morn-lazy-loader' ) ),
				403
			);
		}

		$stats = Morn_Lazy_Loader_Stats::record();

		wp_send_json_success(
			array(
				'message' => __( '统计已记录。', 'morn-lazy-loader' ),
				'totals'  => $stats['totals'],
			)
		);
	}
}
