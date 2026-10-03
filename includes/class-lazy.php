<?php
/**
 * 前台 HTML 重写：注入懒加载属性。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 解析页面 HTML 并为图片等元素注入懒加载属性。
 */
class Morn_Lazy_Loader_Lazy {

	/**
	 * 排除的 URL 片段列表。
	 *
	 * @var array
	 */
	private $excluded = array();

	/**
	 * 排除的 CSS 类名列表。
	 *
	 * @var array
	 */
	private $excluded_classes = array();

	/**
	 * 懒加载的图片总字节数估算。
	 *
	 * @var int
	 */
	private $total_bytes = 0;

	/**
	 * 被保护、不参与重写的区域。
	 *
	 * @var array
	 */
	private $shields = array();

	/**
	 * 执行 HTML 处理。
	 *
	 * @param string $html 页面 HTML。
	 * @return string
	 */
	public function process( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		$settings = morn_lazy_loader_get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return $html;
		}

		$this->prepare_exclusions();

		$skip_first = (int) $settings['skip_first'];
		$mode       = (string) $settings['placeholder'];

		// 先把 script/pre/code/textarea 抽离，避免重写代码示例里的类名与标签。
		$html = $this->shield( $html );

		// 统计偏移量：跳过前 N 张后，后续图片的 data 索引从 N 开始。
		$index = 0;

		$html = $this->process_images( $html, $index, $skip_first, $mode, $settings );
		$html = $this->process_iframes( $html, $settings );
		$html = $this->process_videos( $html, $settings );
		$html = $this->process_backgrounds( $html, $settings );

		// 还原被保护区域。
		$html = $this->unshield( $html );

		/**
		 * 过滤处理后的前台 HTML。
		 *
		 * @param string $html  处理后的 HTML。
		 * @param int    $index 处理的图片数量。
		 */
		return apply_filters( 'morn_lazy_loader_processed_html', $html, $index );
	}

	/**
	 * 抽离不应被重写的区域，并用占位符替换。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	private function shield( $html ) {
		$this->shields = array();

		$pattern = '#<(script|pre|code|textarea)\b[^>]*>.*?</\1\s*>#is';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$this->shields[] = $matches[0];

				// 占位符不含尖括号，不会被后续正则命中。
				return '<!--morn-ll-shield-' . ( count( $this->shields ) - 1 ) . '-->';
			},
			$html
		);
	}

	/**
	 * 还原被抽离的区域。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	private function unshield( $html ) {
		if ( empty( $this->shields ) ) {
			return $html;
		}

		$shields = $this->shields;

		$this->shields = array();

		return preg_replace_callback(
			'#<!--morn-ll-shield-(\d+)-->#',
			function ( $matches ) use ( $shields ) {
				$index = (int) $matches[1];

				return isset( $shields[ $index ] ) ? $shields[ $index ] : '';
			},
			$html
		);
	}

	/**
	 * 准备排除规则。
	 *
	 * @return void
	 */
	private function prepare_exclusions() {
		$settings = morn_lazy_loader_get_settings();

		$this->excluded = array();
		$this->excluded_classes = array();

		/**
		 * 过滤不参与懒加载的图片 URL 列表。
		 *
		 * @param array $excluded URL 列表。
		 */
		$urls = apply_filters( 'morn_lazy_loader_excluded_urls', morn_lazy_loader_split_lines( $settings['exclude_urls'] ) );

		foreach ( (array) $urls as $url ) {
			$url = trim( (string) $url );

			if ( '' !== $url ) {
				$this->excluded[] = $url;
			}
		}

		$classes = isset( $settings['exclude_classes'] ) ? (string) $settings['exclude_classes'] : '';

		foreach ( explode( ',', $classes ) as $class ) {
			$class = sanitize_html_class( trim( $class ) );

			if ( '' !== $class ) {
				$this->excluded_classes[] = $class;
			}
		}
	}

	/**
	 * 判断 URL 是否被排除。
	 *
	 * @param string $url 图片 URL。
	 * @return bool
	 */
	private function is_excluded_url( $url ) {
		if ( '' === $url ) {
			return true;
		}

		// 跳过 data: 与 base64 内联图片。
		if ( 0 === strpos( $url, 'data:' ) ) {
			return true;
		}

		foreach ( $this->excluded as $excluded ) {
			if ( '' !== $excluded && false !== strpos( $url, $excluded ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 判断类名是否命中排除规则。
	 *
	 * @param string $class_attr 原始 class 属性。
	 * @return bool
	 */
	private function is_excluded_class( $class_attr ) {
		if ( '' === $class_attr || empty( $this->excluded_classes ) ) {
			return false;
		}

		foreach ( preg_split( '/\s+/', (string) $class_attr ) as $class ) {
			if ( in_array( $class, $this->excluded_classes, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 处理 img 标签。
	 *
	 * @param string $html      HTML。
	 * @param int    $index     当前图片序号（引用传递）。
	 * @param int    $skip_first 跳过前 N 张。
	 * @param string $mode      占位模式。
	 * @param array  $settings  设置。
	 * @return string
	 */
	private function process_images( $html, &$index, $skip_first, $mode, $settings ) {
		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		$native_loading  = ! empty( $settings['native_loading'] );
		$native_decoding = ! empty( $settings['native_decoding'] );

		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) use ( &$index, $skip_first, $mode, $native_loading, $native_decoding ) {
				$tag = $matches[0];

				// 已经处理过的标签不再重复处理。
				if ( false !== strpos( $tag, 'data-morn-src' ) || false !== strpos( $tag, 'data-morn-native' ) ) {
					return $tag;
				}

				$index++;

				$src = self::get_attr( $tag, 'src' );

				// 前 N 张图保持原样（通常是首屏 LCP 图）。
				if ( $index <= $skip_first ) {
					return self::inject_native( $tag, $native_loading, $native_decoding, false );
				}

				$class_attr = self::get_attr( $tag, 'class' );

				if ( $this->is_excluded_class( $class_attr ) || $this->is_excluded_url( $src ) ) {
					return self::inject_native( $tag, $native_loading, $native_decoding, false );
				}

				$srcset = self::get_attr( $tag, 'srcset' );
				$sizes  = self::get_attr( $tag, 'sizes' );

				// 移除原生懒加载标记，改由本插件接管。
				$tag = self::remove_attr( $tag, 'loading' );

				$tag = self::set_attr( $tag, 'data-morn-src', $src );
				$tag = self::set_attr( $tag, 'class', trim( $class_attr . ' morn-ll-lazy' ) );
				$tag = self::set_attr( $tag, 'data-morn-placeholder', $mode );

				// 原图地址从 src 移除，避免浏览器立即下载。
				$tag = self::set_attr( $tag, 'src', 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg"/%3E' );

				if ( '' !== $srcset ) {
					$tag = self::set_attr( $tag, 'data-morn-srcset', $srcset );
					$tag = self::remove_attr( $tag, 'srcset' );
				}

				if ( '' !== $sizes ) {
					$tag = self::set_attr( $tag, 'sizes', $sizes );
				}

				// 保留原生 loading 属性可交给浏览器做二次优化。
				if ( $native_loading ) {
					$tag = self::set_attr( $tag, 'loading', 'lazy' );
				}

				if ( $native_decoding ) {
					$tag = self::set_attr( $tag, 'decoding', 'async' );
				}

				if ( 'lqip' === $mode ) {
					$lqip = Morn_Lazy_Loader_Placeholder::get_lqip( $src );

					if ( '' !== $lqip ) {
						$style = 'background-image:url(' . $lqip . ');background-size:cover;background-position:center;';
						$tag   = self::merge_style( $tag, $style );
						$tag   = self::set_attr( $tag, 'class', trim( $class_attr . ' morn-ll-lazy morn-ll-lqip' ) );
					}
				} elseif ( 'skeleton' === $mode ) {
					$style = 'background-color:' . morn_lazy_loader_skeleton_color() . ';';
					$tag   = self::merge_style( $tag, $style );
					$tag   = self::set_attr( $tag, 'class', trim( $class_attr . ' morn-ll-lazy morn-ll-skeleton' ) );
				} elseif ( 'color' === $mode ) {
					$style = 'background-color:' . morn_lazy_loader_placeholder_color() . ';';
					$tag   = self::merge_style( $tag, $style );
				}

				$this->total_bytes += self::estimate_size( $src, $srcset );

				return $tag;
			},
			$html
		);

		return $html;
	}

	/**
	 * 处理 iframe 标签。
	 *
	 * @param string $html     HTML。
	 * @param array  $settings 设置。
	 * @return string
	 */
	private function process_iframes( $html, $settings ) {
		if ( empty( $settings['iframes'] ) || false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		$root_margin = (int) $settings['root_margin'];

		return preg_replace_callback(
			'/<iframe\b[^>]*><\/iframe>/i',
			function ( $matches ) use ( $root_margin ) {
				$tag = $matches[0];

				if ( false !== strpos( $tag, 'data-morn-src' ) ) {
					return $tag;
				}

				$src = self::get_attr( $tag, 'src' );

				if ( '' === $src || $this->is_excluded_url( $src ) ) {
					return $tag;
				}

				$class_attr = self::get_attr( $tag, 'class' );

				if ( $this->is_excluded_class( $class_attr ) ) {
					return $tag;
				}

				$tag = self::set_attr( $tag, 'data-morn-src', $src );
				$tag = self::set_attr( $tag, 'data-morn-type', 'iframe' );
				$tag = self::set_attr( $tag, 'data-morn-root-margin', (string) $root_margin );
				$tag = self::remove_attr( $tag, 'src' );
				$tag = self::set_attr( $tag, 'class', trim( $class_attr . ' morn-ll-lazy morn-ll-iframe' ) );
				$tag = self::set_attr( $tag, 'loading', 'lazy' );

				return $tag;
			},
			$html
		);
	}

	/**
	 * 处理 video poster。
	 *
	 * @param string $html     HTML。
	 * @param array  $settings 设置。
	 * @return string
	 */
	private function process_videos( $html, $settings ) {
		if ( empty( $settings['video_poster'] ) || false === stripos( $html, '<video' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<video\b[^>]*>/i',
			function ( $matches ) {
				$tag = $matches[0];

				if ( false !== strpos( $tag, 'data-morn-poster' ) ) {
					return $tag;
				}

				$poster = self::get_attr( $tag, 'poster' );

				if ( '' === $poster || $this->is_excluded_url( $poster ) ) {
					return $tag;
				}

				$class_attr = self::get_attr( $tag, 'class' );

				if ( $this->is_excluded_class( $class_attr ) ) {
					return $tag;
				}

				$tag = self::set_attr( $tag, 'data-morn-poster', $poster );
				$tag = self::remove_attr( $tag, 'poster' );
				$tag = self::set_attr( $tag, 'preload', 'none' );
				$tag = self::set_attr( $tag, 'class', trim( $class_attr . ' morn-ll-lazy morn-ll-video' ) );

				return $tag;
			},
			$html
		);
	}

	/**
	 * 处理 CSS background-image。
	 *
	 * 通过识别内联 style 中的 url(...) 并转为 data-morn-bg。
	 *
	 * @param string $html     HTML。
	 * @param array  $settings 设置。
	 * @return string
	 */
	private function process_backgrounds( $html, $settings ) {
		if ( empty( $settings['background_images'] ) || false === stripos( $html, 'url(' ) ) {
			return $html;
		}

		$mode = (string) $settings['placeholder'];

		return preg_replace_callback(
			'/<([a-z][a-z0-9]*)\b([^>]*?)style="([^"]*\burl\([^"]+\)[^"]*)"([^>]*)>/i',
			function ( $matches ) use ( $mode ) {
				$tag_name    = $matches[1];
				$before      = $matches[2];
				$style       = $matches[3];
				$after       = $matches[4];
				$whole_match = $matches[0];

				// 仅处理常见可设置背景的元素。
				$allowed = array( 'div', 'section', 'span', 'a', 'header', 'footer', 'article', 'aside', 'li', 'figure' );

				if ( ! in_array( strtolower( $tag_name ), $allowed, true ) ) {
					return $whole_match;
				}

				if ( false !== strpos( $before . $after, 'data-morn-bg' ) ) {
					return $whole_match;
				}

				$found = array();

				$clean_style = preg_replace_callback(
					'#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i',
					function ( $url_matches ) use ( &$found ) {
						$url = trim( $url_matches[2] );

						if ( $this->is_excluded_url( $url ) ) {
							return $url_matches[0];
						}

						$found[] = $url;

						return 'none';
					},
					$style
				);

				if ( empty( $found ) ) {
					return $whole_match;
				}

				$class_attr = self::get_attr( $whole_match, 'class' );

				if ( $this->is_excluded_class( $class_attr ) ) {
					return $whole_match;
				}

				$new_class = trim( $class_attr . ' morn-ll-bg' );

				if ( 'lqip' === $mode ) {
					$lqip = Morn_Lazy_Loader_Placeholder::get_lqip( $found[0] );

					if ( '' !== $lqip ) {
						$clean_style = rtrim( $clean_style, '; ' ) . ';background-image:url(' . $lqip . ');background-size:cover;';
						$new_class   = trim( $new_class . ' morn-ll-lqip' );
					}
				} elseif ( 'skeleton' === $mode ) {
					$clean_style = rtrim( $clean_style, '; ' ) . ';background-color:' . morn_lazy_loader_skeleton_color() . ';';
				} elseif ( 'color' === $mode ) {
					$clean_style = rtrim( $clean_style, '; ' ) . ';background-color:' . morn_lazy_loader_placeholder_color() . ';';
				}

				$attrs = ' data-morn-bg="' . esc_attr( $found[0] ) . '"';
				$attrs .= ' data-morn-type="background"';
				$attrs .= ' class="' . esc_attr( $new_class ) . '"';
				$attrs .= ' style="' . esc_attr( $clean_style ) . '"';

				// 移除原 style 与 class 再重建。
				$stripped = preg_replace( '/\sstyle="[^"]*"/i', '', $before );
				$stripped = preg_replace( '/\sclass="[^"]*"/i', '', $stripped );

				return '<' . $tag_name . $stripped . $attrs . $after . '>';
			},
			$html
		);
	}

	/**
	 * 注入原生 loading / decoding 属性。
	 *
	 * @param string $tag            标签。
	 * @param bool   $native_loading 是否注入 loading。
	 * @param bool   $native_decoding 是否注入 decoding。
	 * @param bool   $mark           是否标记已处理。
	 * @return string
	 */
	private static function inject_native( $tag, $native_loading, $native_decoding, $mark = true ) {
		if ( $native_loading && false === stripos( $tag, 'loading=' ) ) {
			$tag = self::set_attr( $tag, 'loading', 'lazy' );
		}

		if ( $native_decoding && false === stripos( $tag, 'decoding=' ) ) {
			$tag = self::set_attr( $tag, 'decoding', 'async' );
		}

		if ( $mark ) {
			$tag = self::set_attr( $tag, 'data-morn-native', '1' );
		}

		return $tag;
	}

	/**
	 * 读取标签属性值。
	 *
	 * @param string $tag     标签字符串。
	 * @param string $attr    属性名。
	 * @return string
	 */
	public static function get_attr( $tag, $attr ) {
		$pattern = '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

		if ( preg_match( $pattern, $tag, $matches ) ) {
			if ( isset( $matches[1] ) && '' !== $matches[1] ) {
				return $matches[1];
			}
			if ( isset( $matches[2] ) && '' !== $matches[2] ) {
				return $matches[2];
			}
			if ( isset( $matches[3] ) ) {
				return $matches[3];
			}
		}

		return '';
	}

	/**
	 * 设置标签属性，不存在则追加。
	 *
	 * @param string $tag   标签字符串。
	 * @param string $attr  属性名。
	 * @param string $value 属性值。
	 * @return string
	 */
	public static function set_attr( $tag, $attr, $value ) {
		$pattern = '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';

		$replacement = $attr . '="' . str_replace( '"', '&quot;', (string) $value ) . '"';

		if ( preg_match( $pattern, $tag ) ) {
			return preg_replace( $pattern, $replacement, $tag, 1 );
		}

		// 插入到自闭合斜杠或标签结束前。
		if ( '/>' !== substr( $tag, -2 ) ) {
			return preg_replace( '/\s*>\s*$/', ' ' . $replacement . '>', $tag, 1 );
		}

		return substr( $tag, 0, -2 ) . ' ' . $replacement . ' />';
	}

	/**
	 * 移除标签属性。
	 *
	 * @param string $tag  标签字符串。
	 * @param string $attr 属性名。
	 * @return string
	 */
	public static function remove_attr( $tag, $attr ) {
		$pattern = '/\s' . preg_quote( $attr, '/' ) . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';

		return preg_replace( $pattern, '', $tag, 1 );
	}

	/**
	 * 合并内联样式。
	 *
	 * @param string $tag   标签。
	 * @param string $style 追加样式。
	 * @return string
	 */
	public static function merge_style( $tag, $style ) {
		$existing = self::get_attr( $tag, 'style' );

		if ( '' === $existing ) {
			return self::set_attr( $tag, 'style', $style );
		}

		return self::set_attr( $tag, 'style', rtrim( $existing, '; ' ) . ';' . $style );
	}

	/**
	 * 估算图片字节数，用于流量节省统计。
	 *
	 * @param string $src    图片 URL。
	 * @param string $srcset srcset 属性。
	 * @return int
	 */
	private static function estimate_size( $src, $srcset = '' ) {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) || '' === $src ) {
			return 0;
		}

		$normalized_url  = str_replace( array( 'http://', 'https://' ), '', $src );
		$normalized_base = str_replace( array( 'http://', 'https://' ), '', $uploads['baseurl'] );

		if ( 0 !== strpos( $normalized_url, $normalized_base ) ) {
			return 0;
		}

		$relative = ltrim( substr( $normalized_url, strlen( $normalized_base ) ), '/' );

		if ( '' === $relative ) {
			return 0;
		}

		$path = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
		$root = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );

		if ( 0 !== strpos( $path, $root ) || ! file_exists( $path ) ) {
			return 0;
		}

		$size = (int) filesize( $path );

		// srcset 通常会选用更小的尺寸，按 60% 折算。
		if ( '' !== $srcset ) {
			$size = (int) round( $size * 0.6 );
		}

		return max( 0, $size );
	}

	/**
	 * 获取本页懒加载图片的总字节估算。
	 *
	 * @return int
	 */
	public function get_total_bytes() {
		return $this->total_bytes;
	}
}
