<?php
/**
 * 后台设置页。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 设置页控制器（Settings API）。
 */
class Morn_Lazy_Loader_Settings {

	/**
	 * 设置分组名。
	 */
	const GROUP = 'morn_lazy_loader_group';

	/**
	 * 菜单 slug。
	 */
	const PAGE = 'morn-lazy-loader';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * 注册菜单。
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( '图片懒加载', 'morn-lazy-loader' ),
			__( '懒加载', 'morn-lazy-loader' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-images-alt',
			59
		);
	}

	/**
	 * 注册设置项。
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			MORN_LAZY_LOADER_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'sec_general',
			__( '基本设置', 'morn-lazy-loader' ),
			array( __CLASS__, 'render_sec_general' ),
			self::PAGE
		);

		self::field( 'enabled', __( '启用懒加载', 'morn-lazy-loader' ), 'checkbox', 'sec_general' );
		self::field( 'scope', __( '启用范围', 'morn-lazy-loader' ), 'select_scope', 'sec_general' );
		self::field( 'root_margin', __( '提前加载距离（px）', 'morn-lazy-loader' ), 'number', 'sec_general' );
		self::field( 'skip_first', __( '跳过前 N 张图片', 'morn-lazy-loader' ), 'number', 'sec_general' );
		self::field( 'native_loading', __( '注入 loading="lazy"', 'morn-lazy-loader' ), 'checkbox', 'sec_general' );
		self::field( 'native_decoding', __( '注入 decoding="async"', 'morn-lazy-loader' ), 'checkbox', 'sec_general' );

		add_settings_section(
			'sec_target',
			__( '懒加载目标', 'morn-lazy-loader' ),
			array( __CLASS__, 'render_sec_target' ),
			self::PAGE
		);

		self::field( 'iframes', __( '懒加载 iframe', 'morn-lazy-loader' ), 'checkbox', 'sec_target' );
		self::field( 'video_poster', __( '懒加载 video poster', 'morn-lazy-loader' ), 'checkbox', 'sec_target' );
		self::field( 'background_images', __( '懒加载 CSS background-image', 'morn-lazy-loader' ), 'checkbox', 'sec_target' );
		self::field( 'exclude_urls', __( '排除图片 URL（每行一条）', 'morn-lazy-loader' ), 'textarea', 'sec_target' );
		self::field( 'exclude_classes', __( '排除 CSS 类名（逗号分隔）', 'morn-lazy-loader' ), 'text', 'sec_target' );

		add_settings_section(
			'sec_placeholder',
			__( '占位符', 'morn-lazy-loader' ),
			array( __CLASS__, 'render_sec_placeholder' ),
			self::PAGE
		);

		self::field( 'placeholder', __( '占位符样式', 'morn-lazy-loader' ), 'select_placeholder', 'sec_placeholder' );
		self::field( 'lqip_width', __( 'LQIP 微缩图宽度（px）', 'morn-lazy-loader' ), 'number', 'sec_placeholder' );
		self::field( 'placeholder_color', __( '纯色占位颜色', 'morn-lazy-loader' ), 'color', 'sec_placeholder' );
		self::field( 'skeleton_color', __( '骨架屏颜色', 'morn-lazy-loader' ), 'color', 'sec_placeholder' );
		self::field( 'lqip_cache_ttl', __( 'LQIP 缓存有效期（秒）', 'morn-lazy-loader' ), 'number', 'sec_placeholder' );

		add_settings_section(
			'sec_stats',
			__( '统计', 'morn-lazy-loader' ),
			array( __CLASS__, 'render_sec_stats' ),
			self::PAGE
		);

		self::field( 'stats_enabled', __( '启用流量节省统计', 'morn-lazy-loader' ), 'checkbox', 'sec_stats' );
	}

	/**
	 * 注册单个字段。
	 *
	 * @param string $key     设置键名。
	 * @param string $label   标签。
	 * @param string $type    控件类型。
	 * @param string $section 分组。
	 * @return void
	 */
	private static function field( $key, $label, $type, $section ) {
		add_settings_field(
			'morn_ll_field_' . $key,
			$label,
			array( __CLASS__, 'render_field' ),
			self::PAGE,
			$section,
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}

	/**
	 * 渲染设置页。
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您没有权限访问此页面。', 'morn-lazy-loader' ) );
		}
		?>
		<div class="wrap morn-ll-wrap">
			<h1><?php echo esc_html__( '图片懒加载与占位符', 'morn-lazy-loader' ); ?></h1>
			<?php echo wp_kses_post( Morn_Lazy_Loader_Stats::render_panel() ); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * 基本设置分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_general() {
		echo '<p class="description">' . esc_html__( '「提前加载距离」指元素进入视口前多少像素就开始加载。数值越大越早加载，用户感知越平滑。', 'morn-lazy-loader' ) . '</p>';
	}

	/**
	 * 目标分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_target() {
		echo '<p class="description">' . esc_html__( 'CSS background-image 依赖 data-bg 属性，请确保主题输出该属性。排除项对所有目标类型生效。', 'morn-lazy-loader' ) . '</p>';
	}

	/**
	 * 占位符分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_placeholder() {
		echo '<p class="description">' . esc_html__( 'LQIP（模糊微缩图）会把原图缩小后转为 base64 内联，需要服务器支持图片缩放；不支持时自动降级为纯色占位。', 'morn-lazy-loader' ) . '</p>';
	}

	/**
	 * 统计分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_stats() {
		echo '<p class="description">' . esc_html__( '统计数据记录在本地 option 中，保留最近 30 天，每天汇总一次。', 'morn-lazy-loader' ) . '</p>';
	}

	/**
	 * 渲染字段控件。
	 *
	 * @param array $args 字段参数。
	 * @return void
	 */
	public static function render_field( $args ) {
		$settings = morn_lazy_loader_get_settings();
		$key      = $args['key'];
		$type     = $args['type'];
		$name     = MORN_LAZY_LOADER_OPTION . '[' . $key . ']';
		$id       = 'morn-ll-' . $key;
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		switch ( $type ) {
			case 'checkbox':
				?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, (int) $value ); ?> />
					<?php echo esc_html__( '启用', 'morn-lazy-loader' ); ?>
				</label>
				<?php
				break;

			case 'select_scope':
				?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<option value="all" <?php selected( 'all', (string) $value ); ?>><?php echo esc_html__( '全站', 'morn-lazy-loader' ); ?></option>
					<option value="content" <?php selected( 'content', (string) $value ); ?>><?php echo esc_html__( '文章与归档页', 'morn-lazy-loader' ); ?></option>
					<option value="singular" <?php selected( 'singular', (string) $value ); ?>><?php echo esc_html__( '仅文章与页面', 'morn-lazy-loader' ); ?></option>
				</select>
				<?php
				break;

			case 'select_placeholder':
				?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<option value="lqip" <?php selected( 'lqip', (string) $value ); ?>><?php echo esc_html__( '模糊微缩图（LQIP）', 'morn-lazy-loader' ); ?></option>
					<option value="color" <?php selected( 'color', (string) $value ); ?>><?php echo esc_html__( '纯色占位', 'morn-lazy-loader' ); ?></option>
					<option value="skeleton" <?php selected( 'skeleton', (string) $value ); ?>><?php echo esc_html__( '骨架屏（Skeleton）', 'morn-lazy-loader' ); ?></option>
					<option value="none" <?php selected( 'none', (string) $value ); ?>><?php echo esc_html__( '不使用占位符', 'morn-lazy-loader' ); ?></option>
				</select>
				<?php
				break;

			case 'color':
				?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text morn-ll-color" placeholder="#f1f1f4" />
				<p class="description"><?php echo esc_html__( '十六进制颜色，如 #f1f1f4。', 'morn-lazy-loader' ); ?></p>
				<?php
				break;

			case 'textarea':
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="5" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
				break;

			case 'number':
				?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" />
				<?php
				break;

			default:
				?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text" />
				<?php
				break;
		}
	}

	/**
	 * 校验设置。
	 *
	 * @param mixed $input 原始输入。
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = morn_lazy_loader_get_settings();
		$input = is_array( $input ) ? $input : array();
		$clean = $old;

		$checkboxes = array(
			'enabled',
			'iframes',
			'video_poster',
			'background_images',
			'native_loading',
			'native_decoding',
			'stats_enabled',
		);

		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$scopes        = array( 'all', 'content', 'singular' );
		$placeholders  = array( 'lqip', 'color', 'skeleton', 'none' );
		$clean['scope']       = isset( $input['scope'] ) && in_array( $input['scope'], $scopes, true ) ? $input['scope'] : 'all';
		$clean['placeholder'] = isset( $input['placeholder'] ) && in_array( $input['placeholder'], $placeholders, true ) ? $input['placeholder'] : 'lqip';

		$clean['root_margin']    = isset( $input['root_margin'] ) ? max( 0, min( 2000, absint( $input['root_margin'] ) ) ) : 200;
		$clean['skip_first']     = isset( $input['skip_first'] ) ? max( 0, min( 50, absint( $input['skip_first'] ) ) ) : 1;
		$clean['lqip_width']     = isset( $input['lqip_width'] ) ? max( 5, min( 200, absint( $input['lqip_width'] ) ) ) : 20;
		$clean['lqip_cache_ttl'] = isset( $input['lqip_cache_ttl'] ) ? max( 3600, min( 2592000, absint( $input['lqip_cache_ttl'] ) ) ) : 604800;

		$clean['placeholder_color'] = self::sanitize_color(
			isset( $input['placeholder_color'] ) ? $input['placeholder_color'] : '',
			'#f1f1f4'
		);
		$clean['skeleton_color'] = self::sanitize_color(
			isset( $input['skeleton_color'] ) ? $input['skeleton_color'] : '',
			'#e8e8ec'
		);

		if ( isset( $input['exclude_urls'] ) ) {
			$raw   = is_scalar( $input['exclude_urls'] ) ? (string) $input['exclude_urls'] : '';
			$lines = array();

			foreach ( morn_lazy_loader_split_lines( $raw ) as $line ) {
				$line = esc_url_raw( trim( $line ) );

				if ( '' !== $line ) {
					$lines[] = $line;
				}

				if ( count( $lines ) >= 100 ) {
					break;
				}
			}

			$clean['exclude_urls'] = implode( "\n", $lines );
		}

		if ( isset( $input['exclude_classes'] ) ) {
			$raw  = is_scalar( $input['exclude_classes'] ) ? (string) $input['exclude_classes'] : '';
			$list = array_filter( array_map( 'sanitize_html_class', explode( ',', $raw ) ) );
			$clean['exclude_classes'] = implode( ',', array_slice( $list, 0, 30 ) );
		}

		return $clean;
	}

	/**
	 * 校验十六进制颜色。
	 *
	 * @param mixed  $value    输入值。
	 * @param string $fallback 默认值。
	 * @return string
	 */
	private static function sanitize_color( $value, $fallback ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' !== $value && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * 加载后台资源。
	 *
	 * @param string $hook 当前后台页。
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'morn-lazy-loader-admin',
			MORN_LAZY_LOADER_URL . 'assets/css/admin.css',
			array(),
			MORN_LAZY_LOADER_VERSION
		);
	}
}
