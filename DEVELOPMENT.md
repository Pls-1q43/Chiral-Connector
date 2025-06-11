# Chiral Connector 插件开发文档

本文档旨在提供 Chiral Connector 插件的详细开发信息，补充主开发文档中未完全解释的设置、功能、机制等实现细节。

## 1. 核心组件

插件主要由以下几个核心 PHP 类组成：

*   `Chiral_Connector_Admin`: 处理所有与 WordPress 后台管理界面相关的功能，包括设置页面、meta box、AJAX 处理等。
*   `Chiral_Connector_Api`: 封装了与 Chiral Hub API 的所有通信逻辑，包括发送数据、删除数据、获取相关文章等。
*   `Chiral_Connector_Sync`: 负责处理本地文章与 Chiral Hub 之间的数据同步，包括文章发布、更新、删除时的自动同步，以及批量同步功能。
*   `Chiral_Connector_Utils`: 提供一些辅助工具函数，如日志记录、生成 Node ID 等。
*   `Chiral_Connector_Public`: 处理插件在前台的显示逻辑，主要是相关文章的展示。
*   `Chiral_Connector` (主插件类): 负责插件的初始化、加载依赖、定义国际化、以及协调各个组件。
*   `Chiral_Connector_Loader`: 负责注册 WordPress 的 actions 和 filters。
*   `Chiral_Connector_Activator`: 处理插件激活时的逻辑。
*   `Chiral_Connector_Deactivator`: 处理插件停用时的逻辑，包括清理设置和计划任务。

## 2. 主要功能与实现细节

### 2.1. 后台管理 (Chiral_Connector_Admin)

#### 2.1.1. 设置页面

*   **菜单注册**: 通过 `admin_menu` action hook，使用 `add_menu_page` 函数在 WordPress 后台添加名为 “Chiral Connector” 的顶级菜单，指向 `display_plugin_setup_page` 方法渲染的设置页面 (`admin/views/settings-page.php`)。
*   **设置注册**: 通过 `admin_init` action hook，使用 `register_setting` 函数注册插件的设置项 `chiral_connector_settings`，并指定了 `sanitize_settings` 方法进行数据清理。
*   **设置区域 (Sections) 和字段 (Fields)**:
    *   **Hub Connection Settings**: 包括 Hub URL, Hub Username, Hub Application Password。提供 “Test Connection” 按钮，触发 `ajax_test_connection` AJAX 操作。
    *   **Synchronization Settings**: 包括 Current Node ID。提供 “Batch Sync All Posts” 按钮，触发 `ajax_trigger_batch_sync` AJAX 操作。
    *   **Display Settings**: 包括 Enable Related Posts Display, Number of Related Posts to Show, Enable Related Posts Cache, Clear Cache 按钮 (触发 `ajax_clear_cache`)。
    *   **Debugging Settings**: 包括 Enable Debug Logging。
*   **设置项清理 (`sanitize_settings`)**: 对用户输入的设置项进行安全处理，例如 `esc_url_raw` 用于 URL，`sanitize_text_field` 用于文本字段，`absint` 用于整数等。
*   **AJAX 处理**:
    *   `ajax_test_connection`: 测试与 Hub 的连接。成功后会调用 `get_and_save_hub_user_id` 从 Hub 获取当前用户的 ID 并保存到设置中。
    *   `ajax_trigger_batch_sync`: 触发批量同步。它会设置一个 transient `chiral_connector_batch_sync_running` 防止重复执行，并使用 `wp_schedule_single_event` 调度 `chiral_connector_batch_sync_posts` action hook 来异步执行实际的批量同步任务（由 `Chiral_Connector_Sync` 类处理）。
    *   `ajax_quit_network`: 处理“退出 Chiral 网络”的请求。此操作会：
        1.  调用 `Chiral_Connector_Api::get_all_node_data_ids_from_hub` 获取该节点在 Hub 上的所有数据 ID。
        2.  遍历这些 ID，调用 `Chiral_Connector_Api::delete_data_from_hub` 从 Hub 删除对应数据。
        3.  调用 `Chiral_Connector_Deactivator::clear_all_plugin_data` 清理本地插件设置和计划任务。
        4.  调用 `deactivate_plugins` 停用插件自身。
    *   `ajax_clear_cache`: 清除相关文章的缓存 (transients)。

#### 2.1.2. 文章编辑页面的 Meta Box 和 Quick Edit

*   **Meta Box ('Send to Chiral Network Sync')**:
    *   通过 `add_meta_boxes_post` action hook，使用 `add_meta_box` 为 'post' 文章类型添加 Meta Box。
    *   `render_chiral_send_metabox`: 渲染 Meta Box 内容，包含一个 “Send to Chiral Hub?” 复选框 (`_chiral_send_to_hub` post meta)。默认值为 'yes'。
    *   `save_chiral_send_metabox_data`: 通过 `save_post` action hook 保存 Meta Box 数据。会进行 nonce 校验、权限检查、防止自动保存。如果复选框从勾选变为未勾选，且文章之前已同步过 (存在 `_chiral_hub_cpt_id` post meta)，则会调用 `Chiral_Connector_Api::delete_data_from_hub` 从 Hub 删除该文章。
*   **文章列表自定义列**: 
    *   `add_chiral_send_column`: 通过 `manage_post_posts_columns` filter hook 添加 “Send to Chiral?” 列。
    *   `render_chiral_send_column_content`: 通过 `manage_post_posts_custom_column` action hook 渲染该列的内容 (Yes/No)。
*   **Quick Edit 支持**:
    *   `add_chiral_quick_edit_fields`: 通过 `quick_edit_custom_box` action hook 在 Quick Edit 区域添加 “Send to Chiral Hub?” 复选框。
    *   `save_chiral_send_metabox_data` 同样处理来自 Quick Edit 的保存请求 (通过检查 `$_POST['_inline_edit']` 和 `check_ajax_referer('inlineeditnonce', '_inline_edit')`)。
    *   JavaScript (`admin/assets/js/chiral-connector-admin.js`) 辅助处理 Quick Edit 字段的填充和提交。

#### 2.1.3. 静态资源加载

*   `enqueue_styles`: 加载后台 CSS (`admin/assets/css/chiral-connector-admin.css`)。
*   `enqueue_scripts`: 加载后台 JavaScript (`admin/assets/js/chiral-connector-admin.js`)，并使用 `wp_localize_script` 传递 PHP 变量 (如 AJAX URL, nonce, 国际化文本) 给 JS。

### 2.2. API 通信 (Chiral_Connector_Api)

此类封装了所有与 Chiral Hub 的 REST API 交互。

*   **`send_data_to_hub`**: 发送文章数据到 Hub 的 `/wp-json/wp/v2/chiral_data` 端点。
    *   如果提供了 `$hub_cpt_id`，则为更新操作 (请求依然是 POST 到 `/wp-json/wp/v2/chiral_data/{$hub_cpt_id}`)。
    *   使用 `wp_remote_request` 发送请求，包含 `Authorization: Basic` 头进行身份验证。
    *   处理 API 响应，成功则返回解码后的 JSON 数据，失败则返回 `WP_Error` 对象。
*   **`delete_data_from_hub`**: 从 Hub 删除数据。
    *   向 `/wp-json/wp/v2/chiral_data/{$hub_cpt_id}` 发送 DELETE 请求。
    *   成功返回 `true`，失败返回 `WP_Error`。
*   **`get_related_data_from_hub`**: 从 Hub 获取相关文章数据。
    *   此方法的核心是调用 WordPress.com 的相关文章 API (`https://public-api.wordpress.com/rest/v1.1/sites/{$site}/posts/{$post_id}/related`)。
    *   `$site` 参数是 Hub 的域名，`$post_id` 参数是当前文章在 Hub 上的对应 CPT ID (`_chiral_hub_cpt_id` post meta)。
    *   调用 `get_related_post_ids_from_wp_api` 获取相关文章的 ID 列表。
    *   遍历这些 ID，再调用 `get_post_details_from_wp_api` 获取每篇相关文章的详细信息 (标题, URL, 摘要, 特色图片, 作者, 元数据等)。
    *   特别处理 `chiral_source_url` 和 `other_URLs` (元数据) 来确定相关文章的最终链接，优先使用源站点的链接。
    *   提取 `chiral_network_name` 元数据作为相关文章的来源网络名称。
*   **`get_related_post_ids_from_wp_api`**: 调用 WordPress.com 的 `/related` API。
    *   **重要**: 为了获取更广泛的相关文章 (而非仅仅是同作者的文章)，请求体中必须包含 `filter[terms][post_type]` 参数，指定搜索的文章类型 (如 `['post', 'chiral_data']`)。
    *   API 端点: `https://public-api.wordpress.com/rest/v1.1/sites/{$site_identifier}/posts/{$post_id}/related`。
    *   使用 `wp_remote_post` 发送请求。
    *   返回结果中，`hits` 字段包含了相关文章的信息。
*   **`get_post_details_from_wp_api`**: 调用 WordPress.com 的 `/sites/{$site_identifier}/posts/{$post_id}` API 获取单篇文章详情。
    *   使用 `fields` 参数指定需要的字段，以减少响应数据量。
*   **`test_hub_connection`**: 测试与 Hub 的连接。
    *   向 Hub 的 `/wp-json/chiral-network/v1/ping` 端点发送 GET 请求。
    *   2xx 响应码表示成功。
*   **`get_all_node_data_ids_from_hub`**: 获取当前节点 (由 `$user_id_on_hub` 标识) 在 Hub 上的所有 `chiral_data` 文章 ID。
    *   通过分页 (per_page=100) 查询 Hub 的 `/wp-json/wp/v2/chiral_data` 端点，筛选条件为 `author = $user_id_on_hub` 和 `_fields = id`。

### 2.3. 数据同步 (Chiral_Connector_Sync)

此类处理本地 WordPress 文章与 Chiral Hub 之间的数据同步。

*   **钩子定义 (`define_hooks`)**:
    *   `publish_post`: 文章发布时触发 `sync_on_publish_post`。
    *   `save_post`: 文章更新时触发 `sync_on_save_post`。
    *   `wp_trash_post`: 文章移至回收站时触发 `sync_on_trash_post`。
    *   `delete_post`: 文章永久删除时触发 `sync_on_delete_post`。
    *   `chiral_connector_batch_sync_posts`: 自定义 action hook，用于执行批量同步，由 `batch_sync_posts` 方法处理。
*   **`sync_on_publish_post` 和 `sync_on_save_post`**:
    *   进行必要的检查 (如是否为 revision/autosave, 文章类型是否为 'post', 文章状态是否为 'publish')。
    *   调用 `sync_post_to_hub` 执行实际同步。
*   **`sync_post_to_hub` (核心同步逻辑)**:
    1.  检查文章的 `_chiral_send_to_hub` meta 值，如果为 'no' 则跳过同步。
    2.  获取 Hub 连接设置 (URL, Username, App Password, Node ID)。
    3.  准备发送到 Hub 的数据 `$post_data`，包含：
        *   `title`, `content`, `excerpt`, `date_gmt`
        *   `meta`: 包含 `chiral_source_url` (本文的永久链接), `_chiral_data_original_post_id` (本文ID), `_chiral_node_id` (本站点ID), `_chiral_data_original_title`, `_chiral_data_original_categories` (JSON字符串), `_chiral_data_original_tags` (JSON字符串), `_chiral_data_original_featured_image_url`, `_chiral_data_original_publish_date`。
    4.  获取 `_chiral_hub_cpt_id` post meta (如果之前同步过)。
    5.  调用 `Chiral_Connector_Api::send_data_to_hub` 发送数据。
    6.  如果成功，更新 `_chiral_hub_cpt_id` post meta。
    7.  如果失败 (API 返回 `WP_Error`)：
        *   检查错误是否为 `rest_post_invalid_id` (通常表示 Hub 上的 CPT ID 无效，可能已被删除)。如果是，则删除本地的 `_chiral_hub_cpt_id` meta，并尝试作为新文章重新同步。
        *   其他错误则记录日志，并调用 `schedule_retry_sync` 安排重试。
*   **`sync_on_trash_post` 和 `sync_on_delete_post`**:
    *   调用 `delete_post_from_hub`。
*   **`delete_post_from_hub`**: 
    1.  获取 `_chiral_hub_cpt_id` post meta。如果为空，则不执行任何操作。
    2.  获取 Hub 连接设置。
    3.  调用 `Chiral_Connector_Api::delete_data_from_hub`。
    4.  如果成功，记录日志并删除 `_chiral_hub_cpt_id` meta。
    5.  如果失败，记录日志并调用 `schedule_retry_sync` 安排重试。
*   **`schedule_retry_sync` (失败重试机制)**:
    *   使用 WordPress cron (`wp_schedule_single_event`) 调度一个名为 `chiral_connector_retry_sync_event` 的事件，在5分钟后执行。
    *   传递参数 `$post_id`, `$action` ('send' 或 'delete'), `$hub_cpt_id` (删除时需要), 和重试次数 `$retry_count`。
    *   最多重试3次 (通过 `_chiral_sync_retry_count` post meta 跟踪)。
*   **`handle_retry_sync_event` (处理重试的 cron 事件)**:
    *   根据 `$action` 参数调用 `sync_post_to_hub` 或 `delete_post_from_hub`。
    *   成功后清除 `_chiral_sync_retry_count` meta。
    *   失败则再次调用 `schedule_retry_sync` (如果未达到最大重试次数)。
*   **`batch_sync_posts` (批量同步)**:
    1.  设置 `chiral_connector_batch_sync_running` transient 防止并发执行。
    2.  通过 `WP_Query` 分页查询所有状态为 'publish' 且 `_chiral_send_to_hub` 不为 'no' 的 'post' 文章。
    3.  遍历查询结果，对每篇文章调用 `sync_post_to_hub` 的核心逻辑进行同步。
    4.  记录同步的成功和失败数量。
    5.  完成后删除 `chiral_connector_batch_sync_running` transient。

### 2.4. 前台显示 (Chiral_Connector_Public)

*   **`display_related_posts`**: 
    *   通过 `the_content` filter hook 附加相关文章列表到文章内容末尾。
    *   检查是否为单篇文章页 (`is_singular('post')`) 以及是否启用了相关文章显示 (`chiral_connector_settings['display_enable']`)。
    *   **缓存机制**: 
        *   如果启用了缓存 (`chiral_connector_settings['enable_cache']`)，则尝试从 transient (`chiral_related_cache_{$post_id}`) 获取相关文章数据。
        *   缓存的有效期默认为12小时 (`12 * HOUR_IN_SECONDS`)。
        *   如果缓存未命中或已过期，则调用 `fetch_and_cache_related_posts`。
    *   如果获取到相关文章数据，则渲染 `public/views/related-posts-display.php` 视图。
*   **`fetch_and_cache_related_posts`**: 
    1.  获取当前文章 URL、Node ID、显示数量等设置。
    2.  调用 `Chiral_Connector_Api::get_related_data_from_hub` 获取相关文章数据。
    3.  如果成功获取到数据且启用了缓存，则使用 `set_transient` 将数据存入缓存。
*   **AJAX 加载相关文章 (`load_related_posts_ajax`)**:
    *   提供 `wp_ajax_nopriv_load_chiral_related_posts` 和 `wp_ajax_load_chiral_related_posts` AJAX action hooks。
    *   JavaScript (`public/assets/js/chiral-connector-public.js`) 在页面加载后发起 AJAX 请求到此端点。
    *   此方法与 `display_related_posts` 类似，获取或请求相关文章数据，然后渲染视图并以 JSON 格式返回 HTML。
*   **静态资源加载**:
    *   `enqueue_styles`: 加载前台 CSS (`public/assets/css/chiral-connector-public.css`)。
    *   `enqueue_scripts`: 加载前台 JavaScript (`public/assets/js/chiral-connector-public.js`)，并使用 `wp_localize_script` 传递 AJAX URL, nonce, post ID 等。

### 2.5. 工具类 (Chiral_Connector_Utils)

*   **`log_message`**: 记录日志信息。
    *   如果启用了调试日志 (`chiral_connector_settings['enable_debug_logging']`)，则使用 `error_log` 将消息写入服务器的错误日志。
    *   可以指定日志级别 (info, warning, error, debug)。
*   **`get_setting`**: 获取插件的完整设置数组，或单个设置项的值。
*   **`generate_node_id_from_url`**: 根据站点 URL 生成一个唯一的 Node ID (使用 `md5(site_url())`)。

## 3. 数据库与存储

*   **WordPress Options**: 
    *   `chiral_connector_settings`: 存储插件的所有设置，是一个数组。
    *   `chiral_connector_failed_sync_queue`: (似乎在代码中未被积极使用，主要依赖 `_chiral_sync_retry_count` post meta 和计划任务参数进行重试管理)。
    *   `chiral_connector_display_last_disabled`: 存储相关文章显示功能最后一次被禁用的时间戳。
*   **Post Meta**: 
    *   `_chiral_send_to_hub`: (string 'yes'/'no') 标记文章是否应发送到 Hub。
    *   `_chiral_hub_cpt_id`: (int) 存储文章在 Hub 上对应的 Custom Post Type ID。
    *   `_chiral_sync_retry_count`: (int) 记录特定文章同步失败的重试次数。
*   **Transients (缓存)**:
    *   `chiral_related_cache_{$post_id}`: 存储特定文章的相关文章数据，用于前台显示缓存。
    *   `chiral_connector_batch_sync_running`: 标记批量同步任务是否正在运行，防止并发执行。

## 4. 关键钩子与流程

### 4.1. 文章同步流程 (新建/更新)

1.  用户在 WordPress 编辑器中保存/发布文章。
2.  `save_post` / `publish_post` action hook 触发 `Chiral_Connector_Sync::sync_on_save_post` / `sync_on_publish_post`。
3.  方法检查文章是否符合同步条件 (类型, 状态, `_chiral_send_to_hub` meta)。
4.  `Chiral_Connector_Sync::sync_post_to_hub` 被调用。
5.  准备数据，调用 `Chiral_Connector_Api::send_data_to_hub`。
6.  API 类发送 HTTP 请求到 Hub。
7.  Hub 处理请求并返回响应。
8.  API 类处理 Hub 的响应。
9.  Sync 类根据 API 响应更新 `_chiral_hub_cpt_id` meta 或安排重试 (`schedule_retry_sync`)。

### 4.2. 文章删除流程

1.  用户在 WordPress 中将文章移至回收站或永久删除。
2.  `wp_trash_post` / `delete_post` action hook 触发 `Chiral_Connector_Sync::sync_on_trash_post` / `sync_on_delete_post`。
3.  方法调用 `Chiral_Connector_Sync::delete_post_from_hub`。
4.  获取 `_chiral_hub_cpt_id` meta。
5.  调用 `Chiral_Connector_Api::delete_data_from_hub`。
6.  API 类发送 HTTP DELETE 请求到 Hub。
7.  Hub 处理请求并返回响应。
8.  API 类处理 Hub 的响应。
9.  Sync 类根据 API 响应删除 `_chiral_hub_cpt_id` meta 或安排重试。

### 4.3. 相关文章显示流程 (AJAX 方式)

1.  前端页面加载完成。
2.  `chiral-connector-public.js` 发起 AJAX 请求到 `load_chiral_related_posts` action。
3.  `Chiral_Connector_Public::load_related_posts_ajax` 处理请求。
4.  检查缓存 (`chiral_related_cache_{$post_id}`) 。
5.  如果缓存未命中，调用 `Chiral_Connector_Api::get_related_data_from_hub`。
6.  API 类向 WordPress.com 的 `/related` API 发起请求 (使用 Hub 域名和 Hub CPT ID)。
7.  API 类获取相关文章 ID 后，再多次调用 WordPress.com 的 `/sites/.../posts/...` API 获取每篇文章的详情。
8.  API 类返回格式化后的相关文章数据。
9.  Public 类将数据存入缓存 (如果启用)，并渲染 HTML 视图。
10. AJAX 响应将 HTML 返回给前端 JS。
11. JS 将 HTML 插入到页面指定位置。

## 5. 注意事项和潜在改进点

*   **错误处理与重试**: 当前的重试机制比较简单 (固定延迟，固定次数)。可以考虑更复杂的策略，如指数退避、死信队列等。
*   **API 认证**: 目前使用 HTTP Basic Auth。可以考虑更安全的认证方式，如 OAuth2。
*   **安全性**: 确保所有用户输入都经过严格的清理和转义。Nonce 校验在 AJAX 和表单处理中非常重要。
*   **性能**: 
    *   批量同步操作可能会消耗较多服务器资源，分批处理和异步执行是必要的。
    *   相关文章获取涉及多次 API 调用 (一次获取 ID 列表，N 次获取详情)，可以考虑 Hub 端是否能提供一个批量获取详情的接口，或者 WordPress.com API 是否支持一次性返回更完整的相关文章信息。
    *   缓存机制对前台性能至关重要。
*   **代码结构**: 依赖注入 (Dependency Injection) 可以使代码更松耦合、易于测试。例如，`Chiral_Connector_Api` 实例可以注入到 `Chiral_Connector_Sync` 和 `Chiral_Connector_Admin` 中，而不是在构造函数中直接 new。
*   **日志**: 详细且可配置的日志对于问题排查非常有帮助。
*   **国际化**: 确保所有面向用户的字符串都使用了 WordPress 的国际化函数 (如 `__`, `_e`, `esc_html__` 等) 并正确加载了文本域。
*   **WordPress.com API 依赖**: 相关文章功能强依赖 WordPress.com 的公共 API。需要注意该 API 的可用性、速率限制以及未来可能的变更。

本文档提供了 Chiral Connector 插件内部工作机制的深入概览。希望它能帮助开发者更好地理解和维护此插件。