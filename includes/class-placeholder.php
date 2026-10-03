<?php
/**
 * 占位符生成（LQIP / 纯色 / 骨架屏）。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 负责生成图片占位符数据。
 */
class Morn_Lazy_Loader_Placeholder {

	/**
	 * 构造 LQIP data URI。
	 *
	 * @param string $url 原图 URL。
	 * @return string data URI，空字符串表示不可用。
	 */
	public static function get_lqip( $url ) {
		$url = esc_url_raw( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$cached = morn_lazy_loader_get_lqip( $url );

		if ( '' !== $cached ) {
			return $cached;
		}

		$data = self::generate_lqip( $url );

		if ( '' === $data ) {
			return '';
		}

		morn_lazy_loader_set_lqip( $url, $data );

		/**
		 * 过滤生成的 LQIP 内容。
		 *
		 * @param string $data data URI。
		 * @param string $url  原图 URL。
		 */
		return apply_filters( 'morn_lazy_loader_lqip', $data, $url );
	}

	/**
	 * 实际生成 LQIP。
	 *
	 * @param string $url 原图 URL。
	 * @return string
	 */
	private static function generate_lqip( $url ) {
		$width = (int) morn_lazy_loader_get_setting( 'lqip_width', 20 );

		// 优先使用本地附件，读取缩略图路径。
		$path = self::get_local_path( $url );

		if ( '' === $path ) {
			// 无本地文件时不发起任何远程请求，直接降级。
			return '';
		}

		$editor = self::get_editor();

		if ( ! $editor ) {
			return '';
		}

		$height = max( 1, (int) round( $width * 0.75 ) );

		$source = $editor->load_image( $path );

		if ( is_wp_error( $source ) ) {
			return '';
		}

		$resized = $editor->resize( $source, $width, $height, false );

		if ( is_wp_error( $resized ) ) {
			return '';
		}

		// resize() 返回的是编辑器实例，直接输出字符串即可，无需再包一层编辑器。
		$quality = 40;
		$blob    = $resized->set_quality( $quality )->generate( 'string', 'jpeg' );

		if ( is_wp_error( $blob ) || ! is_string( $blob ) || '' === $blob ) {
			return '';
		}

		return 'data:image/jpeg;base64,' . base64_encode( $blob ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- 这是图片数据的标准编码方式。
	}

	/**
	 * 尝试把 URL 映射到本地文件路径。
	 *
	 * @param string $url 图片 URL。
	 * @return string 绝对路径，未命中返回空字符串。
	 */
	private static function get_local_path( $url ) {
		// 允许主题/插件通过过滤器提供自定义映射。
		/**
		 * 过滤图片 URL 对应的本地文件路径。
		 *
		 * @param string $path 本地绝对路径，默认空字符串。
		 * @param string $url  图片 URL。
		 */
		$path = apply_filters( 'morn_lazy_loader_local_path', '', $url );

		if ( is_string( $path ) && '' !== $path && file_exists( $path ) ) {
			return $path;
		}

		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}

		$base_url = $uploads['baseurl'];
		$base_dir = trailingslashit( $uploads['basedir'] );

		// 处理协议差异与大小写。
		$normalized_url  = str_replace( array( 'http://', 'https://' ), '', $url );
		$normalized_base = str_replace( array( 'http://', 'https://' ), '', $base_url );

		if ( 0 !== strpos( $normalized_url, $normalized_base ) ) {
			return '';
		}

		$relative = substr( $normalized_url, strlen( $normalized_base ) );
		$relative = ltrim( strtok( $relative, '?' ), '/' );

		if ( '' === $relative ) {
			return '';
		}

		$candidate = $base_dir . $relative;

		// 防止路径穿越。
		$real = wp_normalize_path( $candidate );
		$root = wp_normalize_path( $base_dir );

		if ( 0 !== strpos( $real, $root ) || ! file_exists( $real ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * 获取可用的图片编辑器。
	 *
	 * @return WP_Image_Editor|null
	 */
	private static function get_editor() {
		if ( ! function_exists( 'wp_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! function_exists( 'wp_image_editor' ) ) {
			return null;
		}

		/**
		 * 过滤懒加载使用的图片编辑器实现。
		 *
		 * @param string $implementation 实现名称，默认 GD。
		 */
		$implementation = apply_filters( 'morn_lazy_loader_image_editor', 'gd' );

		$editor = wp_image_editor( $implementation, array( 'mime_type' => 'image/jpeg' ) );

		return is_wp_error( $editor ) ? null : $editor;
	}

	/**
	 * 构造元素的占位样式与属性。
	 *
	 * @param string $src 图片 URL。
	 * @return array
	 */
	public static function get_attributes( $src ) {
		$mode  = (string) morn_lazy_loader_get_setting( 'placeholder', 'lqip' );
		$attrs = array(
			'style'                 => '',
			'data-morn-placeholder' => $mode,
		);

		switch ( $mode ) {
			case 'color':
				$attrs['style'] = 'background-color:' . morn_lazy_loader_placeholder_color() . ';';
				break;

			case 'skeleton':
				$style          = 'background-color:' . morn_lazy_loader_skeleton_color() . ';';
				$style         .= 'background-image:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);';
				$style         .= 'background-size:200% 100%;';
				$attrs['style'] = $style;
				$attrs['class'] = 'morn-ll-skeleton';
				break;

			case 'lqip':
				$lqip = self::get_lqip( $src );

				if ( '' !== $lqip ) {
					$style          = 'background-image:url("' . $lqip . '");';
					$style         .= 'background-size:cover;background-position:center;';
					$style         .= 'filter:blur(8px);transform:scale(1.05);';
					$attrs['style'] = $style;
					$attrs['class'] = 'morn-ll-lqip';
				} else {
					// 无 GD 或非本地文件时降级为纯色。
					$attrs['style']                 = 'background-color:' . morn_lazy_loader_placeholder_color() . ';';
					$attrs['data-morn-placeholder'] = 'color';
				}
				break;

			default:
				$attrs['data-morn-placeholder'] = 'none';
				break;
		}

		return $attrs;
	}
}
