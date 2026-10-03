# Morn Lazy Loader

图片懒加载与占位符插件。为 `<img>`、`<iframe>`、`<video poster>` 与内联 CSS `background-image` 注入懒加载，提供模糊微缩图（LQIP）、纯色、骨架屏三种占位符，内置流量节省统计面板。

零外部资源：不引用任何 CDN，不对外部图片发起请求（LQIP 仅对本地附件生成）。前端脚本为原生实现，不依赖 jQuery。

- 版本：1.0.0
- 需要 WordPress：6.0+
- 需要 PHP：7.4+
- 测试至：WordPress 6.6
- 许可证：MIT

## 特性

- **原生属性注入**：为图片注入 `loading="lazy"` 与 `decoding="async"`，两者可独立开关。
- **四种懒加载目标**：`img`、`iframe`、`video poster`、内联 CSS `background-image`。
- **三种占位符**：LQIP 模糊微缩图（渐进增强）、纯色、骨架屏。
- **智能观察器**：使用 `IntersectionObserver`，不支持时自动降级为 `scroll`/`resize` 被动监听，队列清空后自动解绑。
- **首屏豁免**：前 N 张图片保持原样，不影响 Largest Contentful Paint。
- **占位无闪烁**：加载完成后自动移除模糊与骨架动画，附 CSS transition。
- **流量统计**：记录延迟加载的图片数量与估算节省流量，7 天柱状图展示。
- **多重排除**：URL 列表 + CSS 类名，对所有懒加载目标生效。
- **LQIP 磁盘缓存**：以 URL 的 MD5 为键缓存在 `uploads/morn-lazy-loader/`，避免每次请求重复生成。

## 安装

1. 将 `morn-lazy-loader` 目录上传到 `wp-content/plugins/`。
2. 在后台「插件」中启用「Morn Lazy Loader」。
3. 进入后台菜单「懒加载」进行配置。
4. 启用 LQIP 占位符时，确认服务器启用了 GD 或 Imagick 扩展。

检查图片处理扩展：

```bash
php -m | grep -iE 'gd|imagick'
```

两者都没有时，LQIP 会自动降级为纯色占位，不会报错。

## 配置说明

后台路径：**懒加载 → 图片懒加载与占位符**（顶层菜单，`manage_options` 权限）。

设置页顶部是统计面板，显示累计节省流量、节省比例、延迟加载图片数、统计页面数与最近 7 天柱状图。

### 基本设置

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 启用懒加载 | 启用 | 总开关。关闭后不处理任何内容、不输出任何资源。 |
| 启用范围 | 全站 | `全站` / `文章与归档页` / `仅文章与页面`。 |
| 提前加载距离（px） | `200` | 元素进入视口前多少像素开始加载。范围 0~2000。数值越大越早加载。 |
| 跳过前 N 张图片 | `1` | 前 N 张图片不懒加载。首屏主图通常应豁免，建议 1~3。范围 0~50。 |
| 注入 `loading="lazy"` | 启用 | 为被处理的图片添加原生 loading 属性。 |
| 注入 `decoding="async"` | 启用 | 为被处理的图片添加原生 decoding 属性。 |

### 懒加载目标

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 懒加载 iframe | 启用 | 将 iframe 的 `src` 移至 `data-morn-src`，进入视口后恢复。 |
| 懒加载 video poster | 启用 | poster 移至 `data-morn-poster`，同时设置 `preload="none"`。 |
| 懒加载 CSS background-image | 关闭 | 解析内联 `style` 中的 `url(...)`。**默认关闭**：并非所有主题都用内联样式，误开可能影响特殊布局。 |
| 排除图片 URL（每行一条） | 空 | 只要图片 URL 包含其中任一字符串即跳过。最多 100 条。 |
| 排除 CSS 类名（逗号分隔） | 空 | 元素的 class 含其中任一类名即跳过。最多 30 个。 |

**背景图懒加载的适用范围**：仅处理 `div`、`section`、`span`、`a`、`header`、`footer`、`article`、`aside`、`li`、`figure` 这 10 种元素上的内联 `style`。建议先在设置里加好排除类名再启用。

### 占位符

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 占位符样式 | 模糊微缩图（LQIP） | 可选 `LQIP` / `纯色占位` / `骨架屏` / `不使用占位符`。 |
| LQIP 微缩图宽度（px） | `20` | 生成微缩图的宽度。范围 5~200。宽度越大越清晰，base64 越大。 |
| 纯色占位颜色 | `#f1f1f4` | 仅接受十六进制颜色（`#RGB` ~ `#RRGGBBAA`）。 |
| 骨架屏颜色 | `#e8e8ec` | 同上。骨架屏为该底色叠加 45° 微光渐变动画。 |
| LQIP 缓存有效期（秒） | `604800` | 缓存键有效期说明性配置。实际缓存以文件形式存在，删除缓存目录即失效。范围 3600~2592000。 |

**占位符降级链**：LQIP → 若图片非本地附件或无图片处理扩展 → 纯色占位。

### 统计

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 启用流量节省统计 | 启用 | 关闭后不再上报数据。 |

统计仅对**已登录且具备 `edit_posts` 权限**的用户生效（避免访客大量 AJAX 请求）。

## 工作原理

### 图片处理流程

1. 遍历 `<img>` 标签，序号从 1 开始。
2. 若序号 ≤ 「跳过前 N 张」，只注入原生属性后放行。
3. 若命中排除 URL 或排除类名，只注入原生属性后放行。
4. 否则：`src` → `data-morn-src`，`srcset` → `data-morn-srcset`，`src` 替换为 1×1 透明 SVG。
5. 按占位符模式注入 `style` 与 `class`（`morn-ll-lqip` / `morn-ll-skeleton`）。
6. 估算本地文件字节数，累计到本页总量。

已处理过的标签（带 `data-morn-src` 或 `data-morn-native`）不会被重复处理，重复调用过滤器是安全的。

### 前端加载流程

```
IntersectionObserver 命中
  → 绑定 load 事件（图片）
  → 恢复 src / srcset
  → 加载完成 → 移除模糊与透明度过渡
```

不支持 `IntersectionObserver` 的浏览器降级为 `scroll` + `resize` 监听（`{ passive: true }`），逐帧检查元素位置，队列清空后自动移除监听器。

### HTML 挂载点

HTML 重写挂在以下过滤器上，均为优先级 `9999`（确保最后执行）：

- `the_content` — 文章与页面正文
- `the_content_feed` — RSS 输出
- `comment_text` — 评论内容

> **注意**：本插件只处理上述三个过滤器的内容。若主题把图片输出在页头、页脚、侧边栏或自定义模板中，需要主题自行调用：
>
> ```php
> $lazy = new Morn_Lazy_Loader_Lazy();
> echo $lazy->process( $html );
> ```

## Hook 列表

### 动作（do_action）

| Hook | 回调 | 优先级 | 用途 |
| --- | --- | --- | --- |
| `plugins_loaded` | `morn_lazy_loader_boot` | 10 | 加载文本域并启动插件。 |
| `wp_enqueue_scripts` | `Morn_Lazy_Loader_Assets::enqueue` | 20 | 注册并加载前台脚本。 |
| `wp_head` | `Morn_Lazy_Loader_Assets::print_style` | 8 | 内联输出关键占位样式。 |
| `wp_enqueue_scripts` | `Morn_Lazy_Loader_Observer::render` | 22 | 输出前端配置 `mornLazyLoaderConfig`（内联在脚本之前）。 |
| `wp_enqueue_scripts` | `Morn_Lazy_Loader_Assets::add_stats_config` | 21 | 为可登录用户附加统计 AJAX 配置（内联在脚本之前）。 |
| `wp_footer` | `Morn_Lazy_Loader_Plugin::output_data_attr` | 5 | 输出本页字节估算的 data 属性。 |
| `wp_ajax_morn_lazy_loader_stats` | `Morn_Lazy_Loader_Assets::handle_stats_ajax` | — | 记录统计数据（需 nonce + `edit_posts` 能力）。 |
| `init` | `Morn_Lazy_Loader_Stats::maybe_schedule` | 10 | 注册每日清理定时任务。 |
| `morn_lazy_loader_daily_cleanup` | `Morn_Lazy_Loader_Stats::cleanup` | — | 清理 30 天前的统计数据并重算汇总。 |

### 过滤器（apply_filters）

| Hook | 签名 | 说明 |
| --- | --- | --- |
| `morn_lazy_loader_settings` | `apply_filters( 'morn_lazy_loader_settings', array $settings )` | 过滤合并默认值后的全部设置。 |
| `morn_lazy_loader_active` | `apply_filters( 'morn_lazy_loader_active', bool $active, string $scope )` | 覆盖「是否启用」的判定逻辑。 |
| `morn_lazy_loader_should_process` | `apply_filters( 'morn_lazy_loader_should_process', bool $process, string $content )` | 逐段内容控制是否执行懒加载处理。 |
| `morn_lazy_loader_processed_html` | `apply_filters( 'morn_lazy_loader_processed_html', string $html, int $index )` | 过滤处理后的 HTML。 |
| `morn_lazy_loader_excluded_urls` | `apply_filters( 'morn_lazy_loader_excluded_urls', array $urls )` | 追加不参与懒加载的图片 URL。 |
| `morn_lazy_loader_local_path` | `apply_filters( 'morn_lazy_loader_local_path', string $path, string $url )` | 自定义图片 URL 到本地路径的映射（用于接入 CDN 或自定义存储）。 |
| `morn_lazy_loader_lqip` | `apply_filters( 'morn_lazy_loader_lqip', string $data, string $url )` | 过滤生成的 LQIP data URI。 |
| `morn_lazy_loader_image_editor` | `apply_filters( 'morn_lazy_loader_image_editor', string $implementation )` | 指定图片编辑器实现（`gd` / `imagick`）。 |
| `morn_lazy_loader_inline_style` | `apply_filters( 'morn_lazy_loader_inline_style', string $css, string $mode )` | 过滤内联占位样式。 |
| `morn_lazy_loader_script_config` | `apply_filters( 'morn_lazy_loader_script_config', array $settings )` | 过滤传给前端脚本的配置。 |
| `morn_lazy_loader_stats_panel` | `apply_filters( 'morn_lazy_loader_stats_panel', string $html, array $data )` | 过滤统计面板 HTML。 |

### 使用示例

为自定义模板输出区域启用懒加载：

```php
add_filter( 'morn_lazy_loader_should_process', function ( $process, $content ) {
    if ( is_page_template( 'template-gallery.php' ) ) {
        return true;
    }

    return $process;
}, 10, 2 );
```

为 CDN 域名提供本地路径映射：

```php
add_filter( 'morn_lazy_loader_local_path', function ( $path, $url ) {
    if ( 0 === strpos( $url, 'https://cdn.example.com/wp-content/uploads/' ) ) {
        $relative = substr( $url, strlen( 'https://cdn.example.com/wp-content/uploads/' ) );
        $uploads  = wp_get_upload_dir();

        return trailingslashit( $uploads['basedir'] ) . $relative;
    }

    return $path;
}, 10, 2 );
```

**永久排除**特定图片（如站点 Logo）：

```php
add_filter( 'morn_lazy_loader_excluded_urls', function ( $urls ) {
    $urls[] = '/wp-content/uploads/logo.png';

    return $urls;
} );
```

## FAQ

**Q：图片一直不显示？**
A：按顺序排查：1) 「跳过前 N 张」是否被误设过大；2) 图片 URL 是否命中排除列表；3) 元素的 class 是否命中排除类名；4) 浏览器控制台是否有 JS 报错；5) 脚本是否正常加载（检查 `window.mornLazyLoaderConfig` 是否存在）。

**Q：LQIP 不生效，一直是纯色占位？**
A：LQIP 只对**本地 uploads 目录内**的图片生成。确认：1) 服务器有 GD 或 Imagick 扩展；2) 图片确实存放在本地上传目录（CDN 图片需用 `morn_lazy_loader_local_path` 映射）；3) `uploads/morn-lazy-loader/` 目录可写。

**Q：骨架屏动画一直闪？**
A：说明图片的 `load` 事件没有触发，多为图片地址错误。可在控制台查看该元素的 `data-morn-src` 是否可访问。

**Q：会影响我现有的懒加载插件吗？**
A：本插件会跳过已带 `data-morn-src` 或 `data-morn-native` 标记的标签，但不能识别其它插件的自定义属性。同时启用两个懒加载插件会产生冲突，请只保留一个。

**Q：占位符会导致布局跳动（CLS）吗？**
A：不会。插件保留原有的 `width` / `height` 属性，浏览器据此预留空间。前提是图片本身带有尺寸属性——建议上传图片时保留原始尺寸，或让主题输出宽高。

**Q：统计数据准吗？**
A：是基于浏览器实际观察到的「未加载图片数量」乘以平均字节数的估算值，仅供参考，不代表精确的带宽账单。真实节省量取决于访客是否滚动浏览了该页面。

**Q：为什么统计面板没有数据？**
A：统计仅对**已登录且有 `edit_posts` 权限**的用户上报，且页面需包含至少 1 张被懒加载的图片。请用管理员身份浏览一篇文章后回到设置页查看。

**Q：iframe 懒加载后高度会塌陷？**
A：iframe 本身有 `height` 属性，不受影响。但若 iframe 内部是响应式布局，建议主题在 `iframe` 上预留固定高度。

## 目录说明

```
morn-lazy-loader/
├── morn-lazy-loader.php    # 主文件：插件头、启动、激活/停用钩子
├── uninstall.php            # 卸载清理（选项、定时任务、LQIP 缓存）
├── README.md
├── LICENSE                  # MIT
├── CHANGELOG.md
├── .gitignore
├── .gitattributes
├── assets/
│   ├── css/admin.css        # 后台统计面板样式
│   └── js/lazy-loader.js    # 前台观察器（原生，含降级）
└── includes/
    ├── functions.php        # 设置读取、缓存目录、颜色校验、范围判定
    ├── class-plugin.php     # 启动与 HTML 过滤器挂载
    ├── class-settings.php   # Settings API 设置页与 sanitize
    ├── class-stats.php      # 流量节省统计与定时清理
    ├── class-placeholder.php # LQIP / 纯色 / 骨架屏占位符生成
    ├── class-observer.php   # 前端配置脚本输出
    ├── class-assets.php     # 资源加载、内联样式、统计 AJAX
    └── class-lazy.php       # HTML 重写核心（img/iframe/video/background）
```

**存储的选项**

| 选项名 | 类型 | 说明 |
| --- | --- | --- |
| `morn_lazy_loader_settings` | array | 插件全部设置。 |
| `morn_lazy_loader_stats` | array | 统计数据，含 `daily`（按日明细）与 `totals`（汇总）。 |

**定时任务**：`morn_lazy_loader_daily_cleanup`（每日执行，清理 30 天前数据）

**磁盘缓存**：`uploads/morn-lazy-loader/`，存放 `lqip-{md5}.txt` 格式的 LQIP data URI。

## 卸载说明

在后台「插件」中点击「删除」并确认卸载时，`uninstall.php` 会执行以下清理：

- 删除选项 `morn_lazy_loader_settings`
- 删除选项 `morn_lazy_loader_stats`
- 取消定时任务 `morn_lazy_loader_daily_cleanup`
- 删除 `uploads/morn-lazy-loader/` 下的所有 `.txt` 缓存文件与 `index.html`，目录为空时一并移除

**不会删除**：任何文章、媒体附件、用户数据或其它插件的数据。

## License

MIT License
Copyright (c) 2026 MornRain

详见 [LICENSE](LICENSE)。
