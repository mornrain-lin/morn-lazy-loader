<?php
/**
 * 插件通用辅助函数。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 读取插件设置并与默认值合并。
 *
 * @return array
 */
function morn_lazy_loader_get_settings() {
	$defaults = array(
		'enabled'          => 1,
		'scope'            => 'all',
		'placeholder'      => 'lqip',
		'lqip_width'       => 20,
		'placeholder_color' => '#f1f1f4',
		'skeleton_color'   => '#e8e8ec',
		'root_margin'      => 200,
		'skip_first'       => 1,
		'iframes'          => 1,
		'video_poster'     => 1,
		'background_images' => 0,
		'native_loading'   => 1,
		'native_decoding'  => 1,
		'exclude_urls'     => '',
		'exclude_classes'  => '',
		'lqip_cache_ttl'   => 604800,
		'stats_enabled'    => 1,
	);

	$saved = get_option( MORN_LAZY_LOADER_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	/**
	 * 过滤懒加载插件设置。
	 *
	 * @param array $settings 合并默认值后的设置。
	 */
	return apply_filters( 'morn_lazy_loader_settings', array_merge( $defaults, $saved ) );
}

/**
 * 读取单个设置项。
 *
 * @param string $key     设置键名。
 * @param mixed  $default 默认值。
 * @return mixed
 */
function morn_lazy_loader_get_setting( $key, $default = null ) {
	$settings = morn_lazy_loader_get_settings();

	if ( ! array_key_exists( $key, $settings ) ) {
		return $default;
	}

	return $settings[ $key ];
}

/**
 * 将多行文本按行拆分为去除空白的数组。
 *
 * @param string $value 多行文本。
 * @return array
 */
function morn_lazy_loader_split_lines( $value ) {
	if ( ! is_string( $value ) ) {
		return array();
	}

	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$out   = array();

	foreach ( (array) $lines as $line ) {
		$line = trim( $line );

		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return $out;
}

/**
 * 生成隐藏资源目录路径，带自动创建。
 *
 * @return string
 */
function morn_lazy_loader_cache_dir() {
	$uploads = wp_get_upload_dir();

	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return '';
	}

	$dir = trailingslashit( $uploads['basedir'] ) . 'morn-lazy-loader';

	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}

	$index = trailingslashit( $dir ) . 'index.html';

	if ( ! file_exists( $index ) ) {
		// 直接写文件，避免 WP_Filesystem 依赖。
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $index, '' );
	}

	// LQIP 缓存无需对外公开，阻止直接访问与目录列表。
	$htaccess = trailingslashit( $dir ) . '.htaccess';

	if ( ! file_exists( $htaccess ) ) {
		$rules = "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $htaccess, $rules );
	}

	return trailingslashit( $dir );
}

/**
 * 获取目录下的绝对 URL。
 *
 * @return string
 */
function morn_lazy_loader_cache_url() {
	$uploads = wp_get_upload_dir();

	if ( ! empty( $uploads['error'] ) || empty( $uploads['url'] ) ) {
		return '';
	}

	return trailingslashit( $uploads['url'] ) . 'morn-lazy-loader';
}

/**
 * 根据内容哈希生成缓存文件名。
 *
 * @param string $key 缓存键。
 * @return string
 */
function morn_lazy_loader_cache_filename( $key ) {
	return 'lqip-' . md5( (string) $key ) . '.txt';
}

/**
 * 读取 LQIP 缓存。
 *
 * @param string $key 缓存键。
 * @return string 空字符串表示未命中。
 */
function morn_lazy_loader_get_lqip( $key ) {
	$dir = morn_lazy_loader_cache_dir();

	if ( '' === $dir ) {
		return '';
	}

	$file = $dir . morn_lazy_loader_cache_filename( $key );

	if ( ! file_exists( $file ) ) {
		return '';
	}

	// 超过有效期视为未命中，交给调用方重新生成。
	$ttl = (int) morn_lazy_loader_get_setting( 'lqip_cache_ttl', 604800 );

	if ( $ttl > 0 ) {
		$mtime = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $mtime && ( time() - (int) $mtime ) > $ttl ) {
			return '';
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	$content = @file_get_contents( $file );

	if ( false === $content || '' === $content ) {
		return '';
	}

	// 只接受合法的 data URI。
	if ( 0 !== strpos( $content, 'data:image/' ) ) {
		return '';
	}

	return trim( $content );
}

/**
 * 写入 LQIP 缓存。
 *
 * @param string $key     缓存键。
 * @param string $content data URI 内容。
 * @return bool
 */
function morn_lazy_loader_set_lqip( $key, $content ) {
	$dir = morn_lazy_loader_cache_dir();

	if ( '' === $dir ) {
		return false;
	}

	if ( 0 !== strpos( (string) $content, 'data:image/' ) ) {
		return false;
	}

	$file = $dir . morn_lazy_loader_cache_filename( $key );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	return false !== @file_put_contents( $file, $content );
}

/**
 * 获取默认占位符颜色。
 *
 * @return string
 */
function morn_lazy_loader_placeholder_color() {
	$color = (string) morn_lazy_loader_get_setting( 'placeholder_color', '#f1f1f4' );

	return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : '#f1f1f4';
}

/**
 * 获取骨架屏颜色。
 *
 * @return string
 */
function morn_lazy_loader_skeleton_color() {
	$color = (string) morn_lazy_loader_get_setting( 'skeleton_color', '#e8e8ec' );

	return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : '#e8e8ec';
}

/**
 * 判断内容是否在启用范围内。
 *
 * @return bool
 */
function morn_lazy_loader_in_scope() {
	$scope = (string) morn_lazy_loader_get_setting( 'scope', 'all' );

	if ( 'all' === $scope ) {
		return true;
	}

	if ( 'singular' === $scope ) {
		return is_singular();
	}

	if ( 'content' === $scope ) {
		return is_singular() || is_archive();
	}

	/**
	 * 过滤是否启用懒加载。
	 *
	 * @param bool   $active 是否启用。
	 * @param string $scope  启用范围设置值。
	 */
	return (bool) apply_filters( 'morn_lazy_loader_active', false, $scope );
}
