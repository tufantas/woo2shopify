<?php
/**
 * Admin Interface Class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Woo2Shopify_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'show_admin_notices'));

        // AJAX handlers
        add_action('wp_ajax_woo2shopify_clear_video_cache', array($this, 'ajax_clear_video_cache'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Woo2Shopify Migration', 'woo2shopify'),
            __('Woo2Shopify', 'woo2shopify'),
            'manage_options',
            'woo2shopify',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'woo2shopify') === false) {
            return;
        }
        
        wp_enqueue_script(
            'woo2shopify-admin',
            WOO2SHOPIFY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WOO2SHOPIFY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'woo2shopify-admin',
            WOO2SHOPIFY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WOO2SHOPIFY_VERSION
        );
        
        wp_localize_script('woo2shopify-admin', 'woo2shopify_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo2shopify_nonce'),
            'strings' => array(
                'testing_connection' => __('Testing connection...', 'woo2shopify'),
                'connection_successful' => __('Connection successful!', 'woo2shopify'),
                'connection_failed' => __('Connection failed!', 'woo2shopify'),
                'migration_started' => __('Migration started!', 'woo2shopify'),
                'migration_completed' => __('Migration completed!', 'woo2shopify'),
                'migration_failed' => __('Migration failed!', 'woo2shopify'),
                'confirm_migration' => __('Are you sure you want to start the migration? This process cannot be undone.', 'woo2shopify')
            )
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('woo2shopify_settings', 'woo2shopify_shopify_store_url');
        register_setting('woo2shopify_settings', 'woo2shopify_shopify_access_token');
        register_setting('woo2shopify_settings', 'woo2shopify_shopify_api_key');
        register_setting('woo2shopify_settings', 'woo2shopify_shopify_api_secret');
        register_setting('woo2shopify_settings', 'woo2shopify_batch_size');
        register_setting('woo2shopify_settings', 'woo2shopify_image_quality');
        register_setting('woo2shopify_settings', 'woo2shopify_include_variations');
        register_setting('woo2shopify_settings', 'woo2shopify_include_categories');
        register_setting('woo2shopify_settings', 'woo2shopify_include_tags');
        register_setting('woo2shopify_settings', 'woo2shopify_include_meta');
        register_setting('woo2shopify_settings', 'woo2shopify_include_videos');
        register_setting('woo2shopify_settings', 'woo2shopify_video_as_metafield');
        register_setting('woo2shopify_settings', 'woo2shopify_include_translations');
        register_setting('woo2shopify_settings', 'woo2shopify_include_currencies');
        register_setting('woo2shopify_settings', 'woo2shopify_primary_language');
        register_setting('woo2shopify_settings', 'woo2shopify_primary_currency');
        register_setting('woo2shopify_settings', 'woo2shopify_debug_mode');
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'woo2shopify') {
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'woo2shopify') . '</p></div>';
            }
        }
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=woo2shopify&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'woo2shopify'); ?>
                </a>
                <a href="?page=woo2shopify&tab=selective" class="nav-tab <?php echo $active_tab === 'selective' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Selective Migration', 'woo2shopify'); ?>
                </a>
                <a href="?page=woo2shopify&tab=pages" class="nav-tab <?php echo $active_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Page Migration', 'woo2shopify'); ?>
                </a>
                <a href="?page=woo2shopify&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'woo2shopify'); ?>
                </a>
                <a href="?page=woo2shopify&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Logs', 'woo2shopify'); ?>
                </a>
                <a href="?page=woo2shopify&tab=help" class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Help', 'woo2shopify'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'selective':
                        $this->selective_migration_tab();
                        break;
                    case 'pages':
                        $this->page_migration_tab();
                        break;
                    case 'settings':
                        $this->settings_tab();
                        break;
                    case 'logs':
                        $this->logs_tab();
                        break;
                    case 'help':
                        $this->help_tab();
                        break;
                    default:
                        $this->dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Dashboard tab
     */
    private function dashboard_tab() {
        $wc_reader = new Woo2Shopify_WooCommerce_Reader();
        $product_count = $wc_reader->get_product_count();
        $shopify_api = new Woo2Shopify_Shopify_API();
        $available_languages = $wc_reader->get_available_languages();
        $available_currencies = $wc_reader->get_available_currencies();
        ?>
        <div class="woo2shopify-dashboard">
            <div class="woo2shopify-cards">
                <div class="woo2shopify-card">
                    <h3><?php _e('WooCommerce Products', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-stat">
                        <span class="number"><?php echo number_format($product_count); ?></span>
                        <span class="label"><?php _e('Products ready to migrate', 'woo2shopify'); ?></span>
                    </div>
                </div>
                
                <div class="woo2shopify-card">
                    <h3><?php _e('Connection Status', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-connection-status">
                        <button type="button" id="test-connection" class="button button-secondary">
                            <?php _e('Test Shopify Connection', 'woo2shopify'); ?>
                        </button>
                        <div id="connection-result"></div>
                    </div>
                </div>

                <?php if (!empty($available_languages)): ?>
                <div class="woo2shopify-card">
                    <h3><?php _e('Languages Detected', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-stat">
                        <span class="number"><?php echo count($available_languages); ?></span>
                        <span class="label"><?php _e('Languages available', 'woo2shopify'); ?></span>
                    </div>
                    <div class="language-list">
                        <?php foreach ($available_languages as $lang): ?>
                            <span class="language-tag"><?php echo esc_html($lang['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($available_currencies) > 1): ?>
                <div class="woo2shopify-card">
                    <h3><?php _e('Currencies Detected', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-stat">
                        <span class="number"><?php echo count($available_currencies); ?></span>
                        <span class="label"><?php _e('Currencies available', 'woo2shopify'); ?></span>
                    </div>
                    <div class="currency-list">
                        <?php foreach ($available_currencies as $currency): ?>
                            <span class="currency-tag"><?php echo esc_html($currency['code'] . ' (' . $currency['symbol'] . ')'); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Video statistics
                if (class_exists('Woo2Shopify_Video_Processor')) {
                    $video_processor = new Woo2Shopify_Video_Processor();
                    $video_stats = $video_processor->get_video_cache_stats();
                    if ($video_stats['total_videos'] > 0):
                ?>
                <div class="woo2shopify-card">
                    <h3><?php _e('Video Cache', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-stat">
                        <span class="number"><?php echo number_format($video_stats['total_videos']); ?></span>
                        <span class="label"><?php _e('Videos found in products', 'woo2shopify'); ?></span>
                    </div>
                    <div class="video-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $video_stats['migrated_videos']; ?></span>
                            <span class="stat-label"><?php _e('Migrated', 'woo2shopify'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $video_stats['pending_videos']; ?></span>
                            <span class="stat-label"><?php _e('Pending', 'woo2shopify'); ?></span>
                        </div>
                    </div>
                    <button type="button" id="clear-video-cache" class="button button-secondary button-small">
                        <?php _e('Clear Video Cache', 'woo2shopify'); ?>
                    </button>
                </div>
                <?php
                    endif;
                }
                ?>
            </div>
            
            <div class="woo2shopify-migration-section">
                <h2><?php _e('Start Migration', 'woo2shopify'); ?></h2>
                <p><?php _e('Before starting the migration, please ensure you have configured your Shopify settings and tested the connection.', 'woo2shopify'); ?></p>
                
                <div class="woo2shopify-migration-options">
                    <label>
                        <input type="checkbox" id="include-images" checked>
                        <?php _e('Include product images', 'woo2shopify'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="include-videos" checked>
                        <?php _e('Include product videos', 'woo2shopify'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="include-variations" checked>
                        <?php _e('Include product variations', 'woo2shopify'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="include-categories" checked>
                        <?php _e('Include categories as collections', 'woo2shopify'); ?>
                    </label>
                    <label>
                        <input type="checkbox" id="include-translations" checked>
                        <?php _e('Include translations (WPML/Polylang)', 'woo2shopify'); ?>
                    </label>
                </div>
                
                <div class="woo2shopify-migration-controls">
                    <button type="button" id="start-migration" class="button button-primary button-large">
                        <?php _e('Start Migration', 'woo2shopify'); ?>
                    </button>
                    <button type="button" id="stop-migration" class="button button-secondary" style="display: none;">
                        <?php _e('Stop Migration', 'woo2shopify'); ?>
                    </button>
                </div>
                
                <div id="migration-progress" style="display: none;">
                    <h3><?php _e('Migration Progress', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-progress-bar">
                        <div class="progress-fill"></div>
                        <span class="progress-text">0%</span>
                    </div>
                    <div class="woo2shopify-progress-details">
                        <div class="progress-stats">
                            <span id="processed-count">0</span> / <span id="total-count">0</span> <?php _e('products processed', 'woo2shopify'); ?>
                        </div>
                        <div class="progress-status" id="current-status">
                            <?php _e('Preparing migration...', 'woo2shopify'); ?>
                        </div>
                    </div>
                </div>
                
                <div id="migration-results" style="display: none;">
                    <h3><?php _e('Migration Results', 'woo2shopify'); ?></h3>
                    <div class="woo2shopify-results-summary">
                        <div class="result-item success">
                            <span class="count" id="success-count">0</span>
                            <span class="label"><?php _e('Successful', 'woo2shopify'); ?></span>
                        </div>
                        <div class="result-item failed">
                            <span class="count" id="failed-count">0</span>
                            <span class="label"><?php _e('Failed', 'woo2shopify'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings tab
     */
    private function settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('woo2shopify_settings');
            do_settings_sections('woo2shopify_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Shopify Store URL', 'woo2shopify'); ?></th>
                    <td>
                        <input type="url" name="woo2shopify_shopify_store_url" 
                               value="<?php echo esc_attr(woo2shopify_get_option('shopify_store_url')); ?>" 
                               class="regular-text" placeholder="https://your-store.myshopify.com" />
                        <p class="description"><?php _e('Your Shopify store URL (e.g., https://your-store.myshopify.com)', 'woo2shopify'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Authentication Method', 'woo2shopify'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Choose authentication method', 'woo2shopify'); ?></legend>

                            <h4><?php _e('Option 1: Custom App (Recommended)', 'woo2shopify'); ?></h4>
                            <label for="shopify_access_token"><?php _e('Admin API Access Token', 'woo2shopify'); ?></label>
                            <input type="password" id="shopify_access_token" name="woo2shopify_shopify_access_token"
                                   value="<?php echo esc_attr(woo2shopify_get_option('shopify_access_token')); ?>"
                                   class="regular-text" placeholder="shpat_..." />
                            <p class="description">
                                <?php _e('Create a Custom App in Shopify Admin → Settings → Apps and sales channels → Develop apps', 'woo2shopify'); ?>
                            </p>

                            <h4 style="margin-top: 20px;"><?php _e('Option 2: Private App (Legacy)', 'woo2shopify'); ?></h4>
                            <label for="shopify_api_key"><?php _e('API Key', 'woo2shopify'); ?></label>
                            <input type="text" id="shopify_api_key" name="woo2shopify_shopify_api_key"
                                   value="<?php echo esc_attr(woo2shopify_get_option('shopify_api_key')); ?>"
                                   class="regular-text" placeholder="API Key" />
                            <br><br>
                            <label for="shopify_api_secret"><?php _e('API Secret', 'woo2shopify'); ?></label>
                            <input type="password" id="shopify_api_secret" name="woo2shopify_shopify_api_secret"
                                   value="<?php echo esc_attr(woo2shopify_get_option('shopify_api_secret')); ?>"
                                   class="regular-text" placeholder="API Secret" />
                            <p class="description">
                                <?php _e('Only use if you have an existing Private App. Custom Apps are recommended for new setups.', 'woo2shopify'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Batch size is now automatically optimized -->
                <tr style="display: none;">
                    <th scope="row"><?php _e('Batch Processing', 'woo2shopify'); ?></th>
                    <td>
                        <p class="description"><?php _e('Batch size is automatically optimized for best performance (3-5 products per batch)', 'woo2shopify'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Image Quality', 'woo2shopify'); ?></th>
                    <td>
                        <input type="range" name="woo2shopify_image_quality" 
                               value="<?php echo esc_attr(woo2shopify_get_option('image_quality', 80)); ?>" 
                               min="10" max="100" class="woo2shopify-range" />
                        <span class="range-value"><?php echo woo2shopify_get_option('image_quality', 80); ?>%</span>
                        <p class="description"><?php _e('Image compression quality (10-100%)', 'woo2shopify'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Migration Options', 'woo2shopify'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="woo2shopify_include_variations" value="1" 
                                       <?php checked(woo2shopify_get_option('include_variations', true)); ?> />
                                <?php _e('Include product variations', 'woo2shopify'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="woo2shopify_include_categories" value="1" 
                                       <?php checked(woo2shopify_get_option('include_categories', true)); ?> />
                                <?php _e('Include categories as collections', 'woo2shopify'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="woo2shopify_include_tags" value="1" 
                                       <?php checked(woo2shopify_get_option('include_tags', true)); ?> />
                                <?php _e('Include product tags', 'woo2shopify'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="woo2shopify_include_meta" value="1"
                                       <?php checked(woo2shopify_get_option('include_meta', true)); ?> />
                                <?php _e('Include custom meta fields', 'woo2shopify'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="woo2shopify_include_videos" value="1"
                                       <?php checked(woo2shopify_get_option('include_videos', true)); ?> />
                                <?php _e('Include product videos', 'woo2shopify'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="woo2shopify_video_as_metafield" value="1"
                                       <?php checked(woo2shopify_get_option('video_as_metafield', true)); ?> />
                                <?php _e('Store videos as metafields (recommended)', 'woo2shopify'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="woo2shopify_include_translations" value="1"
                                       <?php checked(woo2shopify_get_option('include_translations', true)); ?> />
                                <?php _e('Include product translations (WPML, Polylang)', 'woo2shopify'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="woo2shopify_include_currencies" value="1"
                                       <?php checked(woo2shopify_get_option('include_currencies', true)); ?> />
                                <?php _e('Include multi-currency prices', 'woo2shopify'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="woo2shopify_debug_mode" value="1"
                                       <?php checked(woo2shopify_get_option('debug_mode', false)); ?> />
                                <?php _e('Enable debug mode (detailed logging)', 'woo2shopify'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Logs tab
     */
    private function logs_tab() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'woo2shopify_logs';
        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        ?>
        <div class="woo2shopify-logs">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" class="button" id="clear-logs">
                        <?php _e('Clear All Logs', 'woo2shopify'); ?>
                    </button>
                </div>
                <?php
                if ($total_logs > $per_page) {
                    $total_pages = ceil($total_logs / $per_page);
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                }
                ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'woo2shopify'); ?></th>
                        <th><?php _e('Migration ID', 'woo2shopify'); ?></th>
                        <th><?php _e('Product ID', 'woo2shopify'); ?></th>
                        <th><?php _e('Action', 'woo2shopify'); ?></th>
                        <th><?php _e('Status', 'woo2shopify'); ?></th>
                        <th><?php _e('Message', 'woo2shopify'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No logs found.', 'woo2shopify'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td><?php echo esc_html($log->migration_id); ?></td>
                                <td><?php echo esc_html($log->product_id ?: '-'); ?></td>
                                <td><?php echo esc_html($log->action); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->message); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Help tab
     */
    private function help_tab() {
        ?>
        <div class="woo2shopify-help">
            <h2><?php _e('Getting Started', 'woo2shopify'); ?></h2>

            <h3><?php _e('Method 1: Custom App (Recommended)', 'woo2shopify'); ?></h3>
            <ol>
                <li><strong><?php _e('Shopify Admin', 'woo2shopify'); ?></strong> → Settings → Apps and sales channels</li>
                <li><?php _e('Click "Develop apps" → "Create an app"', 'woo2shopify'); ?></li>
                <li><?php _e('Give your app a name: "WooCommerce Migration"', 'woo2shopify'); ?></li>
                <li><?php _e('Click "Configure Admin API scopes"', 'woo2shopify'); ?></li>
                <li><?php _e('Enable these permissions:', 'woo2shopify'); ?>
                    <ul>
                        <li>✅ write_products</li>
                        <li>✅ write_product_listings</li>
                        <li>✅ write_inventory</li>
                        <li>✅ write_orders (optional)</li>
                    </ul>
                </li>
                <li><?php _e('Click "Save" → "Install app"', 'woo2shopify'); ?></li>
                <li><?php _e('Copy the "Admin API access token" (starts with shpat_)', 'woo2shopify'); ?></li>
                <li><?php _e('Paste it in Settings tab → Admin API Access Token field', 'woo2shopify'); ?></li>
            </ol>

            <h3><?php _e('Method 2: Private App (Legacy)', 'woo2shopify'); ?></h3>
            <ol>
                <li><strong><?php _e('Shopify Admin', 'woo2shopify'); ?></strong> → Apps → Manage private apps</li>
                <li><?php _e('Click "Create private app"', 'woo2shopify'); ?></li>
                <li><?php _e('Fill in app details and enable API permissions', 'woo2shopify'); ?></li>
                <li><?php _e('Copy API Key and API Secret', 'woo2shopify'); ?></li>
                <li><?php _e('Paste them in Settings tab → API Key and API Secret fields', 'woo2shopify'); ?></li>
            </ol>

            <h3><?php _e('Final Steps', 'woo2shopify'); ?></h3>
            <ol>
                <li><?php _e('Enter your Shopify store URL (e.g., https://your-store.myshopify.com)', 'woo2shopify'); ?></li>
                <li><?php _e('Test your connection in Dashboard tab', 'woo2shopify'); ?></li>
                <li><?php _e('Configure migration options', 'woo2shopify'); ?></li>
                <li><?php _e('Start the migration!', 'woo2shopify'); ?></li>
            </ol>
            
            <h2><?php _e('Frequently Asked Questions', 'woo2shopify'); ?></h2>
            <div class="woo2shopify-faq">
                <h3><?php _e('What data gets migrated?', 'woo2shopify'); ?></h3>
                <p><?php _e('The plugin migrates products, variations, images, categories (as collections), tags, and custom meta fields.', 'woo2shopify'); ?></p>
                
                <h3><?php _e('Will my WooCommerce data be modified?', 'woo2shopify'); ?></h3>
                <p><?php _e('No, the plugin only reads data from WooCommerce. Your original store remains unchanged.', 'woo2shopify'); ?></p>
                
                <h3><?php _e('Can I run the migration multiple times?', 'woo2shopify'); ?></h3>
                <p><?php _e('Yes, but be aware that this may create duplicate products in Shopify. Consider using product SKUs to avoid duplicates.', 'woo2shopify'); ?></p>
            </div>
            
            <h2><?php _e('Support', 'woo2shopify'); ?></h2>
            <p><?php _e('If you need help or encounter issues, please check the logs tab for detailed error information.', 'woo2shopify'); ?></p>

            <h3><?php _e('Contact Information', 'woo2shopify'); ?></h3>
            <ul>
                <li><strong><?php _e('Developer:', 'woo2shopify'); ?></strong> Tufan Taş</li>
                <li><strong><?php _e('Email:', 'woo2shopify'); ?></strong> <a href="mailto:tufantas@gmail.com">tufantas@gmail.com</a></li>
                <li><strong><?php _e('GitHub:', 'woo2shopify'); ?></strong> <a href="https://github.com/tufantas/woo2shopify" target="_blank">https://github.com/tufantas/woo2shopify</a></li>
                <li><strong><?php _e('Issues:', 'woo2shopify'); ?></strong> <a href="https://github.com/tufantas/woo2shopify/issues" target="_blank">Report a Bug</a></li>
            </ul>
        </div>
        <?php
    }

    /**
     * AJAX handler for clearing video cache
     */
    public function ajax_clear_video_cache() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'woo2shopify_admin_nonce')) {
            wp_die(__('Security check failed', 'woo2shopify'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'woo2shopify'));
        }

        // Clear video cache
        if (class_exists('Woo2Shopify_Video_Processor')) {
            $video_processor = new Woo2Shopify_Video_Processor();
            $result = $video_processor->clear_video_cache();

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Video cache cleared successfully', 'woo2shopify')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Failed to clear video cache', 'woo2shopify')
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => __('Video processor not available', 'woo2shopify')
            ));
        }
    }

    /**
     * Selective Migration tab
     */
    private function selective_migration_tab() {
        ?>
        <div class="woo2shopify-selective-migration">
            <h2><?php _e('Selective Product Migration', 'woo2shopify'); ?></h2>
            <p><?php _e('Select specific products to migrate to Shopify. This gives you better control over the migration process.', 'woo2shopify'); ?></p>

            <!-- Product Filters -->
            <div class="woo2shopify-filters">
                <div class="filter-row">
                    <input type="text" id="product-search" placeholder="<?php _e('Search products...', 'woo2shopify'); ?>" />
                    <select id="product-category">
                        <option value=""><?php _e('All Categories', 'woo2shopify'); ?></option>
                        <?php
                        $categories = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'hide_empty' => false
                        ));
                        foreach ($categories as $category) {
                            echo '<option value="' . $category->term_id . '">' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                    <select id="product-status">
                        <option value="any"><?php _e('Any Status', 'woo2shopify'); ?></option>
                        <option value="publish"><?php _e('Published', 'woo2shopify'); ?></option>
                        <option value="draft"><?php _e('Draft', 'woo2shopify'); ?></option>
                    </select>
                    <button type="button" id="filter-products" class="button"><?php _e('Filter', 'woo2shopify'); ?></button>
                </div>
            </div>

            <!-- Migration Options -->
            <div class="woo2shopify-migration-options">
                <h3><?php _e('Migration Options', 'woo2shopify'); ?></h3>
                <label><input type="checkbox" id="selective-include-images" checked> <?php _e('Include Images', 'woo2shopify'); ?></label>
                <label><input type="checkbox" id="selective-include-videos" checked> <?php _e('Include Videos', 'woo2shopify'); ?></label>
                <label><input type="checkbox" id="selective-include-variations" checked> <?php _e('Include Variations', 'woo2shopify'); ?></label>
                <label><input type="checkbox" id="selective-include-categories" checked> <?php _e('Include Categories', 'woo2shopify'); ?></label>
            </div>

            <!-- Product Selection -->
            <div class="woo2shopify-product-selection">
                <div class="selection-controls">
                    <button type="button" id="select-all-products" class="button"><?php _e('Select All', 'woo2shopify'); ?></button>
                    <button type="button" id="deselect-all-products" class="button"><?php _e('Deselect All', 'woo2shopify'); ?></button>
                    <button type="button" id="migrate-selected-products" class="button button-primary" disabled><?php _e('Migrate Selected', 'woo2shopify'); ?></button>
                    <span id="selected-count">0 <?php _e('products selected', 'woo2shopify'); ?></span>
                </div>

                <div id="products-list" class="products-grid">
                    <!-- Products will be loaded here via AJAX -->
                </div>

                <div class="pagination-controls">
                    <button type="button" id="load-more-products" class="button"><?php _e('Load More', 'woo2shopify'); ?></button>
                </div>
            </div>

            <!-- Migration Progress -->
            <div id="selective-migration-progress" class="woo2shopify-progress" style="display: none;">
                <h3><?php _e('Migration Progress', 'woo2shopify'); ?></h3>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-info">
                    <span class="progress-text">0%</span>
                    <span class="progress-details"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Page Migration tab
     */
    private function page_migration_tab() {
        ?>
        <div class="woo2shopify-page-migration">
            <h2><?php _e('Page Migration', 'woo2shopify'); ?></h2>
            <p><?php _e('Migrate WordPress pages to Shopify pages. Content will be cleaned and optimized for Shopify.', 'woo2shopify'); ?></p>

            <!-- Page Filters -->
            <div class="woo2shopify-filters">
                <div class="filter-row">
                    <input type="text" id="page-search" placeholder="<?php _e('Search pages...', 'woo2shopify'); ?>" />
                    <select id="page-status">
                        <option value="publish"><?php _e('Published', 'woo2shopify'); ?></option>
                        <option value="draft"><?php _e('Draft', 'woo2shopify'); ?></option>
                        <option value="private"><?php _e('Private', 'woo2shopify'); ?></option>
                    </select>
                    <button type="button" id="filter-pages" class="button"><?php _e('Filter', 'woo2shopify'); ?></button>
                </div>
            </div>

            <!-- Page Selection -->
            <div class="woo2shopify-page-selection">
                <div class="selection-controls">
                    <button type="button" id="select-all-pages" class="button"><?php _e('Select All', 'woo2shopify'); ?></button>
                    <button type="button" id="deselect-all-pages" class="button"><?php _e('Deselect All', 'woo2shopify'); ?></button>
                    <button type="button" id="migrate-selected-pages" class="button button-primary" disabled><?php _e('Migrate Selected', 'woo2shopify'); ?></button>
                    <span id="selected-pages-count">0 <?php _e('pages selected', 'woo2shopify'); ?></span>
                </div>

                <div id="pages-list" class="pages-grid">
                    <!-- Pages will be loaded here via AJAX -->
                </div>

                <div class="pagination-controls">
                    <button type="button" id="load-more-pages" class="button"><?php _e('Load More', 'woo2shopify'); ?></button>
                </div>
            </div>

            <!-- Migration Results -->
            <div id="page-migration-results" class="woo2shopify-results" style="display: none;">
                <h3><?php _e('Migration Results', 'woo2shopify'); ?></h3>
                <div class="results-summary"></div>
                <div class="results-details"></div>
            </div>
        </div>
        <?php
    }
}
