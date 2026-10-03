# Changelog

本项目遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [1.0.0] - 2026-10-02

### 新增

- 自动为 `<img>` 注入 `loading="lazy"` 与 `decoding="async"` 原生懒加载属性（可分别开关）。
- `data-src` 延迟加载机制：将 `src` 替换为 1×1 透明 SVG 占位，原图地址存入 `data-morn-src`，`srcset` 存入 `data-morn-srcset`。
- 支持 `<iframe>` 懒加载：移除 `src`，改由 `data-morn-src` 保存，进入视口后恢复。
- 支持 `<video poster>` 懒加载：poster 移入 `data-morn-poster`，同时设置 `preload="none"`。
- 支持内联 `style` 中的 CSS `background-image` 懒加载：识别 `url(...)` 并转为 `data-morn-bg`，仅作用于 div/section/span/a/header/footer/article/aside/li/figure。
- 三种占位符模式：
  - **LQIP 模糊微缩图**：把本地图片缩小到指定宽度（默认 20px）后以 JPEG q40 转为 base64 data URI 内联。
  - **纯色占位**：使用自定义十六进制背景色。
  - **骨架屏 Skeleton**：底色 + 循环微光渐变动画。
- `IntersectionObserver` 观察器，不支持时自动降级为 `scroll` + `resize` 被动监听；队列清空后自动解绑事件。
- 首屏图片豁免：可指定前 N 张图片保持原样（默认 1 张），避免影响 LCP。
- URL 排除列表与 CSS 类名排除规则，对 img/iframe/video/background 全部生效。
- 后台设置页：启用范围、占位样式、提前加载距离、排除规则、LQIP 缓存有效期等。
- 流量节省统计面板：累计节省流量、节省比例、延迟加载图片数、统计页面数，以及最近 7 天柱状图。
- 统计通过 WordPress AJAX 上报，带 nonce 校验；数据保留最近 30 天，每日定时清理并重算汇总。
- LQIP 缓存写入 `uploads/morn-lazy-loader/`，以图片 URL 的 MD5 作为键；目录含 `index.html` 防止目录列表。

### 性能

- LQIP 仅对**本地**附件生成（URL 必须位于 uploads 目录内），不对外部图片发起任何请求，天然满足零外部资源约束。
- 关键占位样式直接内联输出在 `<head>`，不产生额外 CSS 请求。
- 统计上报走 `requestIdleCallback`（降级为 `load` 事件后延时 3 秒），不阻塞首屏渲染。
- 前端脚本 `defer` 加载，零外部依赖，不使用 jQuery。
- HTML 处理挂在 `the_content` 优先级 9999，确保在主题与其它插件输出之后执行。

### 安全

- HTML 重写使用正则回调，输出属性统一经 `esc_attr()` 处理。
- LQIP 缓存写入前校验内容必须为 `data:image/` 前缀，读取时同样校验，阻断缓存投毒。
- 本地路径映射做 `wp_normalize_path` 前缀校验，阻断路径穿越。
- 统计 AJAX 执行 `morn_lazy_loader_stats` nonce 校验；数值输入经 `absint()` 并设上限。
- 设置项经 `sanitize_callback` 白名单校验；颜色值仅接受十六进制格式。
- 卸载脚本仅删除自身选项、定时任务与 `uploads/morn-lazy-loader/` 缓存目录，不触碰媒体附件。

### 兼容

- PHP 7.4 ~ 8.3 语法兼容，未使用 enum、readonly、构造器属性提升、命名参数等 PHP 8 独有特性。
- WordPress 6.0 ~ 6.6。
- 零外部资源：不引用任何 CDN、远程 API 或第三方脚本。
