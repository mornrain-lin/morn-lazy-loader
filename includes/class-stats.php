<?php
/**
 * 流量节省统计。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 记录与展示懒加载统计。
 */
class Morn_Lazy_Loader_Stats {

	/**
	 * 统计保留天数。
	 */
	const RETENTION_DAYS = 30;

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'morn_lazy_loader_daily_cleanup', array( __CLASS__, 'cleanup' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * 初始化统计数据结构。
	 *
	 * @return void
	 */
	public static function seed() {
		$stats = get_option( MORN_LAZY_LOADER_STATS, array() );

		if ( ! is_array( $stats ) || ! isset( $stats['daily'] ) || ! is_array( $stats['daily'] ) ) {
			$stats = array(
				'daily'     => array(),
				'totals'    => array(
					'pages'    => 0,
					'images'   => 0,
					'saved'    => 0,
					'baseline' => 0,
				),
				'last_prune' => 0,
			);

			update_option( MORN_LAZY_LOADER_STATS, $stats, false );
		}
	}

	/**
	 * 注册每日清理任务。
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( 'morn_lazy_loader_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'morn_lazy_loader_daily_cleanup' );
		}
	}

	/**
	 * 清理过期统计数据。
	 *
	 * @return void
	 */
	public static function cleanup() {
		$stats = get_option( MORN_LAZY_LOADER_STATS, array() );

		if ( ! is_array( $stats ) || empty( $stats['daily'] ) ) {
			return;
		}

		$cutoff    = gmdate( 'Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		$daily     = $stats['daily'];
		$kept      = array();

		foreach ( $daily as $date => $data ) {
			if ( (string) $date >= $cutoff ) {
				$kept[ $date ] = $data;
			}
		}

		// 重算汇总，避免历史数据与明细不一致。
		$totals = array(
			'pages'    => 0,
			'images'   => 0,
			'saved'    => 0,
			'baseline' => 0,
		);

		foreach ( $kept as $data ) {
			$totals['pages']    += isset( $data['pages'] ) ? (int) $data['pages'] : 0;
			$totals['images']   += isset( $data['images'] ) ? (int) $data['images'] : 0;
			$totals['saved']    += isset( $data['saved'] ) ? (int) $data['saved'] : 0;
			$totals['baseline'] += isset( $data['baseline'] ) ? (int) $data['baseline'] : 0;
		}

		$stats['daily']      = $kept;
		$stats['totals']     = $totals;
		$stats['last_prune'] = time();

		update_option( MORN_LAZY_LOADER_STATS, $stats, false );
	}

	/**
	 * 记录一次页面访问统计（由前端 AJAX 触发）。
	 *
	 * @return array
	 */
	public static function record() {
		self::seed();

		$stats = get_option( MORN_LAZY_LOADER_STATS, array() );
		$date  = gmdate( 'Y-m-d' );

		$images   = isset( $_POST['images'] ) ? min( 999, max( 0, absint( wp_unslash( $_POST['images'] ) ) ) ) : 0;
		$saved    = isset( $_POST['saved'] ) ? min( 104857600, max( 0, absint( wp_unslash( $_POST['saved'] ) ) ) ) : 0;
		$baseline = isset( $_POST['baseline'] ) ? min( 104857600, max( 0, absint( wp_unslash( $_POST['baseline'] ) ) ) ) : 0;

		if ( ! isset( $stats['daily'][ $date ] ) || ! is_array( $stats['daily'][ $date ] ) ) {
			$stats['daily'][ $date ] = array(
				'pages'    => 0,
				'images'   => 0,
				'saved'    => 0,
				'baseline' => 0,
			);
		}

		$stats['daily'][ $date ]['pages']    += 1;
		$stats['daily'][ $date ]['images']   += $images;
		$stats['daily'][ $date ]['saved']    += $saved;
		$stats['daily'][ $date ]['baseline'] += $baseline;

		// 逐日累加，保持与 daily 明细一致。
		$stats['totals']['pages']    = 0;
		$stats['totals']['images']   = 0;
		$stats['totals']['saved']    = 0;
		$stats['totals']['baseline'] = 0;

		foreach ( $stats['daily'] as $data ) {
			$stats['totals']['pages']    += isset( $data['pages'] ) ? (int) $data['pages'] : 0;
			$stats['totals']['images']   += isset( $data['images'] ) ? (int) $data['images'] : 0;
			$stats['totals']['saved']    += isset( $data['saved'] ) ? (int) $data['saved'] : 0;
			$stats['totals']['baseline'] += isset( $data['baseline'] ) ? (int) $data['baseline'] : 0;
		}

		update_option( MORN_LAZY_LOADER_STATS, $stats, false );

		return $stats;
	}

	/**
	 * 获取统计数据。
	 *
	 * @return array
	 */
	public static function get_data() {
		$stats = get_option( MORN_LAZY_LOADER_STATS, array() );

		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		return array(
			'daily'  => isset( $stats['daily'] ) && is_array( $stats['daily'] ) ? $stats['daily'] : array(),
			'totals' => isset( $stats['totals'] ) && is_array( $stats['totals'] ) ? $stats['totals'] : array(
				'pages'    => 0,
				'images'   => 0,
				'saved'    => 0,
				'baseline' => 0,
			),
		);
	}

	/**
	 * 格式化字节数。
	 *
	 * @param int $bytes 字节数。
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes <= 0 ) {
			return '0 B';
		}

		$units = array( 'B', 'KB', 'MB', 'GB' );
		$index = (int) floor( log( $bytes, 1024 ) );
		$index = min( $index, count( $units ) - 1 );

		return round( $bytes / pow( 1024, $index ), 2 ) . ' ' . $units[ $index ];
	}

	/**
	 * 渲染统计面板 HTML。
	 *
	 * @return string
	 */
	public static function render_panel() {
		$data   = self::get_data();
		$totals = $data['totals'];
		$saved  = isset( $totals['saved'] ) ? (int) $totals['saved'] : 0;

		$percent = 0;
		if ( isset( $totals['baseline'] ) && (int) $totals['baseline'] > 0 ) {
			$percent = round( $saved / (int) $totals['baseline'] * 100, 1 );
		}

		$html  = '<div class="morn-ll-panel">';
		$html .= '<h2>' . esc_html__( '流量节省统计', 'morn-lazy-loader' ) . '</h2>';
		$html .= '<div class="morn-ll-cards">';

		$cards = array(
			array(
				'label' => __( '累计节省流量', 'morn-lazy-loader' ),
				'value' => self::format_bytes( $saved ),
			),
			array(
				'label' => __( '节省比例', 'morn-lazy-loader' ),
				'value' => $percent . '%',
			),
			array(
				'label' => __( '延迟加载图片', 'morn-lazy-loader' ),
				'value' => number_format_i18n( isset( $totals['images'] ) ? (int) $totals['images'] : 0 ),
			),
			array(
				'label' => __( '统计页面数', 'morn-lazy-loader' ),
				'value' => number_format_i18n( isset( $totals['pages'] ) ? (int) $totals['pages'] : 0 ),
			),
		);

		foreach ( $cards as $card ) {
			$html .= '<div class="morn-ll-card"><span class="morn-ll-card-label">' . esc_html( $card['label'] );
			$html .= '</span><span class="morn-ll-card-value">' . esc_html( $card['value'] ) . '</span></div>';
		}

		$html .= '</div>';

		// 最近 7 天柱状图，纯 CSS 宽度实现。
		$recent = array_slice( $data['daily'], -7, 7, true );
		$max    = 1;

		foreach ( $recent as $row ) {
			$max = max( $max, isset( $row['saved'] ) ? (int) $row['saved'] : 0 );
		}

		if ( ! empty( $recent ) ) {
			$html .= '<h3>' . esc_html__( '最近 7 天', 'morn-lazy-loader' ) . '</h3><div class="morn-ll-chart">';

			foreach ( $recent as $date => $row ) {
				$value = isset( $row['saved'] ) ? (int) $row['saved'] : 0;
				$width = $max > 0 ? (int) round( $value / $max * 100 ) : 0;

				$html .= '<div class="morn-ll-bar-row">';
				$html .= '<span class="morn-ll-bar-date">' . esc_html( (string) $date ) . '</span>';
				$html .= '<span class="morn-ll-bar"><i style="width:' . (int) $width . '%"></i></span>';
				$html .= '<span class="morn-ll-bar-value">' . esc_html( self::format_bytes( $value ) ) . '</span>';
				$html .= '</div>';
			}

			$html .= '</div>';
		}

		$html .= '<p class="description">' . esc_html__( '统计基于访客浏览器实际观察到的未加载图片估算，仅供参考。数据保留最近 30 天。', 'morn-lazy-loader' ) . '</p>';
		$html .= '</div>';

		/**
		 * 过滤统计面板 HTML。
		 *
		 * @param string $html 面板 HTML。
		 * @param array  $data 统计数据。
		 */
		return apply_filters( 'morn_lazy_loader_stats_panel', $html, $data );
	}
}
