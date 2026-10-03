/**
 * Morn Lazy Loader 前台懒加载脚本。
 *
 * 纯原生实现，无外部依赖。
 * 优先使用 IntersectionObserver，不支持时降级为 scroll + resize 监听。
 */
( function () {
	'use strict';

	var config = window.mornLazyLoaderConfig || {};
	var rootMargin = config.rootMargin || '200px';

	/**
	 * 正在观察的元素集合。
	 */
	var pending = [];

	/**
	 * 已处理的图片数量。
	 */
	var imageCount = 0;

	/**
	 * 未在视口内加载的图片总字节数（由 data-total-bytes 提供）。
	 */
	var totalBytes = 0;

	/**
	 * 降级模式下已绑定的滚动监听标记。
	 */
	var fallbackBound = false;

	/**
	 * 标记元素为已加载，触发动画。
	 *
	 * @param {Element} element 元素。
	 * @return {void}
	 */
	function markLoaded( element ) {
		element.setAttribute( 'data-morn-loaded', '1' );
	}

	/**
	 * 加载单个元素。
	 *
	 * @param {Element} element 元素。
	 * @return {void}
	 */
	function load( element ) {
		var type = element.getAttribute( 'data-morn-type' );

		if ( 'background' === type ) {
			var bg = element.getAttribute( 'data-morn-bg' );

			if ( bg ) {
				element.style.backgroundImage = 'url("' + bg + '")';
			}

			markLoaded( element );
			return;
		}

		if ( 'video' === type ) {
			var poster = element.getAttribute( 'data-morn-poster' );

			if ( poster ) {
				element.setAttribute( 'poster', poster );
			}

			markLoaded( element );
			return;
		}

		if ( 'iframe' === type ) {
			var iframeSrc = element.getAttribute( 'data-morn-src' );

			if ( iframeSrc ) {
				element.setAttribute( 'src', iframeSrc );
			}

			markLoaded( element );
			return;
		}

		// 默认：img。
		var src = element.getAttribute( 'data-morn-src' );
		var srcset = element.getAttribute( 'data-morn-srcset' );

		if ( srcset ) {
			element.setAttribute( 'srcset', srcset );
		}

		if ( src ) {
			element.setAttribute( 'src', src );
		}

		markLoaded( element );
	}

	/**
	 * 加载元素并绑定加载完成回调。
	 *
	 * @param {Element} element 元素。
	 * @return {void}
	 */
	function loadWithEvents( element ) {
		if ( 'IMG' === element.tagName ) {
			element.addEventListener(
				'load',
				function () {
					markLoaded( element );
					loadedCount++;
				},
				{ once: true }
			);

			// 缓存命中的图片可能不触发 load 事件。
			if ( element.complete && element.naturalWidth > 0 ) {
				markLoaded( element );
				loadedCount++;
			}

			load( element );
			return;
		}

		if ( element.hasAttribute( 'data-morn-src' ) ) {
			loadedCount++;
		}

		load( element );
	}

	/**
	 * 从待加载队列中移除元素。
	 *
	 * @param {Element} element 元素。
	 * @return {void}
	 */
	function removeFromPending( element ) {
		var index = pending.indexOf( element );

		if ( index !== -1 ) {
			pending.splice( index, 1 );
		}
	}

	/**
	 * 降级模式：滚动时检查元素是否进入视口。
	 *
	 * @return {void}
	 */
	function checkFallback() {
		var margin = parseInt( rootMargin, 10 ) || 200;
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var remaining = [];

		for ( var i = 0; i < pending.length; i++ ) {
			var element = pending[ i ];
			var rect = element.getBoundingClientRect();

			if ( rect.top - margin <= viewportHeight && rect.bottom + margin >= 0 ) {
				loadWithEvents( element );
			} else {
				remaining.push( element );
			}
		}

		pending = remaining;

		if ( pending.length === 0 && fallbackBound ) {
			window.removeEventListener( 'scroll', checkFallback );
			window.removeEventListener( 'resize', checkFallback );
			fallbackBound = false;
		}
	}

	/**
	 * 建立观察器。
	 *
	 * @return {void}
	 */
	function observe() {
		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver(
				function ( entries ) {
					for ( var i = 0; i < entries.length; i++ ) {
						if ( entries[ i ].isIntersecting ) {
							loadWithEvents( entries[ i ].target );
							removeFromPending( entries[ i ].target );
							observer.unobserve( entries[ i ].target );
						}
					}
				},
				{ rootMargin: rootMargin }
			);

			for ( var i = 0; i < pending.length; i++ ) {
				observer.observe( pending[ i ] );
			}

			return;
		}

		// 降级：scroll + resize。
		fallbackBound = true;
		window.addEventListener( 'scroll', checkFallback, { passive: true } );
		window.addEventListener( 'resize', checkFallback );
		checkFallback();
	}

	/**
	 * 收集需要懒加载的元素。
	 *
	 * @return {void}
	 */
	function collect() {
		var selectors = [
			'[data-morn-src]',
			'[data-morn-bg]',
			'[data-morn-poster]'
		];

		var elements = document.querySelectorAll( selectors.join( ',' ) );

		for ( var i = 0; i < elements.length; i++ ) {
			var element = elements[ i ];

			if ( element.getAttribute( 'data-morn-loaded' ) ) {
				continue;
			}

			if ( element.hasAttribute( 'data-morn-src' ) ) {
				imageCount++;
			}

			pending.push( element );
		}
	}

	/**
	 * 已完成加载的图片数量。
	 */
	var loadedCount = 0;

	/**
	 * 上传统计数据。
	 *
	 * @return {void}
	 */
	function reportStats() {
		var stats = window.mornLazyLoaderStats;

		if ( ! stats || ! stats.ajaxUrl || ! stats.nonce || imageCount === 0 ) {
			return;
		}

		if ( ! window.fetch || ! window.FormData ) {
			return;
		}

		// 未在视口内加载的图片按平均字节数折算为节省量。
		var deferred = Math.max( 0, imageCount - loadedCount );
		var average = imageCount > 0 ? totalBytes / imageCount : 0;
		var saved = Math.round( average * deferred );

		var form = new FormData();

		form.append( 'action', 'morn_lazy_loader_stats' );
		form.append( 'nonce', stats.nonce );
		form.append( 'images', String( imageCount ) );
		form.append( 'saved', String( saved ) );
		form.append( 'baseline', String( totalBytes ) );

		window.fetch( stats.ajaxUrl, {
			method: 'POST',
			body: form,
			credentials: 'same-origin'
		} ).catch( function () {
			// 统计失败不影响页面，静默忽略。
		} );
	}

	/**
	 * 读取服务端注入的总字节数。
	 *
	 * @return {void}
	 */
	function readTotalBytes() {
		var node = document.querySelector( '.morn-ll-stats-data' );

		if ( node ) {
			totalBytes = parseInt( node.getAttribute( 'data-total-bytes' ), 10 ) || 0;
		}
	}

	/**
	 * 初始化。
	 *
	 * @return {void}
	 */
	function init() {
		readTotalBytes();
		collect();

		if ( pending.length === 0 ) {
			return;
		}

		observe();

		// 统计在页面空闲时上报一次。
		if ( config.stats && window.requestIdleCallback ) {
			window.requestIdleCallback( reportStats, { timeout: 5000 } );
		} else {
			window.addEventListener( 'load', function () {
				window.setTimeout( reportStats, 3000 );
			}, { once: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
