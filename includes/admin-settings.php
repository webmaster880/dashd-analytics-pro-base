<?php
/**
 * Admin Settings Module v10.1.0
 * ДОБАВЛЕНО: Интернационализация (i18n) строк админки и JS алертов.
 */

if (!defined('ABSPATH')) exit;

function dashd_admin_settings_page() {
    global $wpdb;
    $sources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dashd_settings");
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'sources';
    $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    ?>
    <div class="wrap">
        <div class="dashd-admin-header">
            <h1><?php esc_html_e('Analytics Pro Settings', 'dashd-analytics-pro'); ?> <span class="dashd-badge">v<?php echo esc_html((string) DASHD_VERSION); ?></span></h1>
        </div>
        
        <?php if (isset($_GET['imported'])): ?>
            <div class="notice notice-success is-dismissible"><p>✅ <?php printf(esc_html__('Successfully imported %s dictionary records.', 'dashd-analytics-pro'), '<strong>' . intval($_GET['imported']) . '</strong>'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['imported_raw'])): ?>
            <div class="notice notice-success is-dismissible"><p>📊 <?php printf(esc_html__('Successfully processed %s Raw Data records.', 'dashd-analytics-pro'), '<strong>' . intval($_GET['imported_raw']) . '</strong>'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'source_invalid'): ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Source not added: invalid or unsafe URL/headers configuration.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'source_exists'): ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Source not added: source key already exists.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'source_updated'): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Source updated successfully.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'source_update_invalid'): ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Source update failed: invalid or unsafe URL/headers configuration.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'source_not_found'): ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Source update failed: source not found.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'synced'): ?>
            <div class="notice notice-success is-dismissible"><p>✅ <strong><?php esc_html_e('Synchronization complete.', 'dashd-analytics-pro'); ?></strong> <?php esc_html_e('All data is up to date.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'cron_saved'): ?>
            <div class="notice notice-success is-dismissible"><p>⏱️ <strong><?php esc_html_e('Schedule updated.', 'dashd-analytics-pro'); ?></strong></p></div>
        <?php endif; ?>
        <?php if ($status === 'wiped'): ?>
            <div class="notice notice-error is-dismissible"><p>🗑️ <strong><?php esc_html_e('Data wiped.', 'dashd-analytics-pro'); ?></strong> <?php esc_html_e('All records, snapshots, leads, and sync logs have been permanently deleted.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>
        <?php if ($status === 'logs_cleared'): ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Sync logs cleared.', 'dashd-analytics-pro'); ?></p></div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=dashd-settings&tab=sources" class="nav-tab <?php echo $active_tab == 'sources' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Data Sources & Raw Data', 'dashd-analytics-pro'); ?></a>
            <a href="?page=dashd-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Logs', 'dashd-analytics-pro'); ?></a>
            <a href="?page=dashd-settings&tab=countries" class="nav-tab <?php echo $active_tab == 'countries' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Countries Translation', 'dashd-analytics-pro'); ?></a>
            <a href="?page=dashd-settings&tab=indicators" class="nav-tab <?php echo $active_tab == 'indicators' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Indicators Translation', 'dashd-analytics-pro'); ?></a>
            <a href="?page=dashd-settings&tab=branding" class="nav-tab <?php echo $active_tab == 'branding' ? 'nav-tab-active' : ''; ?>" style="color:#1e87f0;"><?php esc_html_e('PDF Branding 🎨', 'dashd-analytics-pro'); ?></a>
            <a href="?page=dashd-settings&tab=leads" class="nav-tab <?php echo $active_tab == 'leads' ? 'nav-tab-active' : ''; ?>" style="color:#10b981;"><?php esc_html_e('Leads (Emails) 📩', 'dashd-analytics-pro'); ?></a>
        </h2>

        <?php if ($active_tab !== 'branding' && $active_tab !== 'leads' && $active_tab !== 'logs'): ?>
        <div class="dashd-toolbar">
            <div class="dashd-toolbar-group">
                <p class="dashd-toolbar-title"><?php esc_html_e('Dictionaries Actions:', 'dashd-analytics-pro'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0;">
                    <input type="hidden" name="action" value="dashd_export_csv">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">
                    <?php wp_nonce_field('dashd_export_csv', 'dashd_export_csv_nonce'); ?>
                    <button type="submit" class="button button-secondary" style="display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Tab to CSV', 'dashd-analytics-pro'); ?>
                    </button>
                </form>
            </div>
            <div class="dashd-toolbar-divider" style="display: none;"></div>
            <div class="dashd-toolbar-group">
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; margin:0;">
                    <input type="hidden" name="action" value="dashd_import_csv">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">
                    <?php wp_nonce_field('dashd_import_csv', 'dashd_import_csv_nonce'); ?>
                    <input type="file" name="csv_file" accept=".csv" required style="max-width: 250px;">
                    <button type="submit" class="button button-primary" style="display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import Tab CSV', 'dashd-analytics-pro'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <?php 
            if ($active_tab === 'countries') {
                dashd_render_lang_table('dashd_countries', __('Manage Country Names', 'dashd-analytics-pro'));
            } elseif ($active_tab === 'indicators') {
                dashd_render_lang_table('dashd_indicators', __('Manage Indicator Names', 'dashd-analytics-pro'));
            } elseif ($active_tab === 'branding') {
                dashd_render_branding_tab();
            } elseif ($active_tab === 'leads') {
                dashd_render_leads_tab();
            } elseif ($active_tab === 'logs') {
                dashd_render_logs_tab();
            } else {
                dashd_render_sources_tab($sources);
            }
            ?>
        </div>
    </div>
    <?php
}

function dashd_render_logs_tab() {
    ?>
    <div class="dashd-card" style="margin-top: 12px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Synchronization Logs', 'dashd-analytics-pro'); ?></h3>
        <p style="color:#646970; margin-bottom:10px;">
            <?php esc_html_e('This section contains sync history, anomaly details, and maintenance actions for logs.', 'dashd-analytics-pro'); ?>
        </p>
    </div>
    <?php
    if (function_exists('dashd_render_admin_sync_logs_table')) {
        dashd_render_admin_sync_logs_table(true);
    }
}

function dashd_render_branding_tab() {
    // 1. СОХРАНЕНИЕ НАСТРОЕК (Добавлена ширина логотипа)
    if (isset($_POST['save_branding'])) {
        if (function_exists('dashd_enforce_http_method')) {
            dashd_enforce_http_method('POST');
        }
        if (!current_user_can('manage_options')) {
            if (function_exists('dashd_forbidden_response')) {
                dashd_forbidden_response(false);
            }
            wp_die(__('Access denied', 'dashd-analytics-pro'));
        }
        check_admin_referer('dashd_save_branding', 'dashd_save_branding_nonce');

        $logo_raw = isset($_POST['dashd_pdf_logo']) ? wp_unslash((string) $_POST['dashd_pdf_logo']) : '';
        $logo_width_raw = isset($_POST['pdf_logo_width']) ? wp_unslash((string) $_POST['pdf_logo_width']) : '150';
        $signature_raw = isset($_POST['dashd_pdf_signature']) ? wp_unslash((string) $_POST['dashd_pdf_signature']) : '';
        $watermark_raw = isset($_POST['dashd_pdf_watermark']) ? wp_unslash((string) $_POST['dashd_pdf_watermark']) : '';

        $logo_url = esc_url_raw($logo_raw, ['http', 'https']);
        $logo_width = is_numeric($logo_width_raw) ? (int) $logo_width_raw : 150;
        if ($logo_width < 20) {
            $logo_width = 20;
        } elseif ($logo_width > 1200) {
            $logo_width = 1200;
        }

        $signature = sanitize_textarea_field($signature_raw);
        if (function_exists('mb_substr')) {
            $signature = mb_substr($signature, 0, 2000);
        } else {
            $signature = substr($signature, 0, 2000);
        }

        $watermark = sanitize_text_field($watermark_raw);
        if (function_exists('mb_substr')) {
            $watermark = mb_substr($watermark, 0, 120);
        } else {
            $watermark = substr($watermark, 0, 120);
        }

        update_option('dashd_pdf_logo', $logo_url);
        update_option('dashd_pdf_logo_width', $logo_width); // <-- НОВОЕ ПОЛЕ
        update_option('dashd_pdf_signature', $signature);
        update_option('dashd_pdf_watermark', $watermark);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('PDF Branding settings saved successfully.', 'dashd-analytics-pro') . '</p></div>';
    }
    
    // 2. ПОЛУЧЕНИЕ НАСТРОЕК (Добавлена ширина логотипа)
    $logo = get_option('dashd_pdf_logo', '');
    $logo_width = get_option('dashd_pdf_logo_width', 150); // <-- НОВОЕ ПОЛЕ (150 по умолчанию)
    $sig = get_option('dashd_pdf_signature', '');
    $wm = get_option('dashd_pdf_watermark', '');
    ?>
    <div class="dashd-card" style="margin-top:20px; max-width: 600px;">
        <h3 style="margin-top:0;"><?php esc_html_e('PDF Export Branding', 'dashd-analytics-pro'); ?></h3>
        <p style="color:#646970; margin-bottom: 25px;"><?php esc_html_e('These elements will be automatically injected into the PDF report when users download it from the frontend.', 'dashd-analytics-pro'); ?></p>
        
        <form method="post">
            <?php wp_nonce_field('dashd_save_branding', 'dashd_save_branding_nonce'); ?>
            <div class="uk-margin" style="margin-bottom: 25px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;"><?php esc_html_e('Company Logo (Top Right):', 'dashd-analytics-pro'); ?></label>
                <div style="display:flex; gap:10px;">
                    <input type="url" id="logo_url" name="dashd_pdf_logo" value="<?php echo esc_attr($logo); ?>" class="regular-text" style="flex:1;">
                    <button type="button" class="button button-secondary" id="upload_logo_btn"><?php esc_html_e('Choose from Media Library', 'dashd-analytics-pro'); ?></button>
                </div>
                <?php if($logo): ?>
                    <div style="margin-top: 10px; background: #f6f7f7; padding: 10px; border-radius: 4px; display: inline-block;">
                        <img src="<?php echo esc_url($logo); ?>" style="height: 60px; width: auto; max-width: 250px; display: block; object-fit: contain;">
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <label style="font-weight:600; display:block; margin-bottom:5px;">Logo Max Width (px):</label>
                    <input type="number" name="pdf_logo_width" value="<?php echo esc_attr($logo_width); ?>" style="width: 100px;">
                    <p class="description" style="margin-top: 5px;">Set the maximum width for the logo in the generated PDF reports (default: 150).</p>
                </div>
            </div>

            <div class="uk-margin" style="margin-bottom: 25px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;"><?php esc_html_e('Watermark Text:', 'dashd-analytics-pro'); ?></label>
                <input type="text" name="dashd_pdf_watermark" value="<?php echo esc_attr($wm); ?>" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('e.g. CONFIDENTIAL', 'dashd-analytics-pro'); ?>">
                <p style="font-size: 11px; color: #666; margin-top: 5px;"><?php esc_html_e('A light, diagonal text spanning across the report page.', 'dashd-analytics-pro'); ?></p>
            </div>

            <div class="uk-margin" style="margin-bottom: 25px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;"><?php esc_html_e('Footer Signature:', 'dashd-analytics-pro'); ?></label>
                <textarea name="dashd_pdf_signature" rows="3" class="large-text" style="width:100%;" placeholder="<?php esc_attr_e('e.g. Generated by Analytics Pro. All rights reserved.', 'dashd-analytics-pro'); ?>"><?php echo esc_textarea($sig); ?></textarea>
                <p style="font-size: 11px; color: #666; margin-top: 5px;"><?php esc_html_e('Text displayed at the very bottom of the PDF page.', 'dashd-analytics-pro'); ?></p>
            </div>

            <button type="submit" name="save_branding" class="button button-primary" style="display:flex; align-items:center; gap:5px;">
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save Branding', 'dashd-analytics-pro'); ?>
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var mediaUploader;
        document.getElementById('upload_logo_btn').addEventListener('click', function(e) {
            e.preventDefault();
            if (mediaUploader) { mediaUploader.open(); return; }
            mediaUploader = wp.media({
                title: '<?php echo esc_js(__('Choose Company Logo', 'dashd-analytics-pro')); ?>',
                button: { text: '<?php echo esc_js(__('Use this logo', 'dashd-analytics-pro')); ?>' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                document.getElementById('logo_url').value = attachment.url;
            });
            mediaUploader.open();
        });
    });
    </script>
    <?php
}

function dashd_render_leads_tab() {
    global $wpdb;
    
    // 1. Обработка удаления лида
    if (isset($_POST['delete_lead'])) {
        if (function_exists('dashd_enforce_http_method')) {
            dashd_enforce_http_method('POST');
        }
        if (!current_user_can('manage_options')) {
            if (function_exists('dashd_forbidden_response')) {
                dashd_forbidden_response(false);
            }
            wp_die(__('Access denied', 'dashd-analytics-pro'));
        }

        $lead_id = intval($_POST['lead_id'] ?? 0);
        check_admin_referer('dashd_delete_lead_' . $lead_id, 'dashd_delete_lead_nonce');
        $wpdb->delete("{$wpdb->prefix}dashd_leads", ['id' => $lead_id]);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Lead deleted.', 'dashd-analytics-pro') . '</p></div>';
    }

    // 2. НОВЫЙ КОД: Обработка сохранения вебхуков
    if (isset($_POST['save_webhooks'])) {
        if (function_exists('dashd_enforce_http_method')) {
            dashd_enforce_http_method('POST');
        }
        if (!current_user_can('manage_options')) {
            if (function_exists('dashd_forbidden_response')) {
                dashd_forbidden_response(false);
            }
            wp_die(__('Access denied', 'dashd-analytics-pro'));
        }

        check_admin_referer('dashd_save_webhooks', 'dashd_save_webhooks_nonce');
        $telegram_token_const = defined('DASHD_TELEGRAM_BOT_TOKEN');
        $telegram_chat_const = defined('DASHD_TELEGRAM_CHAT_ID');
        $crm_webhook_const = defined('DASHD_CRM_WEBHOOK');
        $slack_webhook_const = defined('DASHD_SLACK_WEBHOOK');

        $telegram_token = isset($_POST['dashd_telegram_bot_token']) ? wp_unslash((string) $_POST['dashd_telegram_bot_token']) : '';
        $telegram_chat = isset($_POST['dashd_telegram_chat_id']) ? wp_unslash((string) $_POST['dashd_telegram_chat_id']) : '';
        $crm_webhook_raw = isset($_POST['dashd_crm_webhook']) ? wp_unslash((string) $_POST['dashd_crm_webhook']) : '';
        $slack_webhook_raw = isset($_POST['dashd_slack_webhook']) ? wp_unslash((string) $_POST['dashd_slack_webhook']) : '';

        $crm_webhook = function_exists('dashd_sanitize_webhook_url') ? dashd_sanitize_webhook_url($crm_webhook_raw) : esc_url_raw($crm_webhook_raw);
        $slack_webhook = function_exists('dashd_sanitize_webhook_url') ? dashd_sanitize_webhook_url($slack_webhook_raw) : esc_url_raw($slack_webhook_raw);

        if (!$telegram_token_const) {
            if (function_exists('dashd_update_sensitive_setting')) {
                dashd_update_sensitive_setting('dashd_telegram_bot_token', sanitize_text_field($telegram_token));
            } else {
                update_option('dashd_telegram_bot_token', sanitize_text_field($telegram_token));
            }
        }
        if (!$telegram_chat_const) {
            if (function_exists('dashd_update_sensitive_setting')) {
                dashd_update_sensitive_setting('dashd_telegram_chat_id', sanitize_text_field($telegram_chat));
            } else {
                update_option('dashd_telegram_chat_id', sanitize_text_field($telegram_chat));
            }
        }
        if (!$crm_webhook_const) {
            if (function_exists('dashd_update_sensitive_setting')) {
                dashd_update_sensitive_setting('dashd_crm_webhook', $crm_webhook);
            } else {
                update_option('dashd_crm_webhook', $crm_webhook);
            }
        }
        if (!$slack_webhook_const) {
            if (function_exists('dashd_update_sensitive_setting')) {
                dashd_update_sensitive_setting('dashd_slack_webhook', $slack_webhook);
            } else {
                update_option('dashd_slack_webhook', $slack_webhook);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Integrations saved.', 'dashd-analytics-pro') . '</p></div>';

        if (trim($crm_webhook_raw) !== '' && $crm_webhook === '') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('CRM webhook was not saved: use a valid public HTTP(S) URL (local/private addresses are blocked).', 'dashd-analytics-pro') . '</p></div>';
        }
        if (trim($slack_webhook_raw) !== '' && $slack_webhook === '') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Slack/Discord webhook was not saved: use a valid public HTTP(S) URL (local/private addresses are blocked).', 'dashd-analytics-pro') . '</p></div>';
        }
    }

    $telegram_token_const = defined('DASHD_TELEGRAM_BOT_TOKEN');
    $telegram_chat_const = defined('DASHD_TELEGRAM_CHAT_ID');
    $crm_webhook_const = defined('DASHD_CRM_WEBHOOK');
    $slack_webhook_const = defined('DASHD_SLACK_WEBHOOK');

    $telegram_token = $telegram_token_const ? '' : (string) get_option('dashd_telegram_bot_token', '');
    $telegram_chat_id = $telegram_chat_const ? '' : (string) get_option('dashd_telegram_chat_id', '');
    $crm_hook = $crm_webhook_const ? '' : (string) get_option('dashd_crm_webhook', '');
    $slack_hook = $slack_webhook_const ? '' : (string) get_option('dashd_slack_webhook', '');
    $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dashd_leads ORDER BY created_at DESC");
    ?>

    <div class="dashd-card" style="margin-top:20px; margin-bottom:30px; max-width: 800px;">
        <h3 style="margin-top:0;"><?php esc_html_e('API Integrations & Webhooks', 'dashd-analytics-pro'); ?></h3>
        <p style="color:#646970; margin-bottom: 20px;"><?php esc_html_e('Automate your workflow by sending data to external services (Zapier, HubSpot, Slack, etc.).', 'dashd-analytics-pro'); ?></p>
        
        <form method="post">
            <?php wp_nonce_field('dashd_save_webhooks', 'dashd_save_webhooks_nonce'); ?>
            <div class="uk-margin" style="margin-bottom: 15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Telegram Bot Token (Sync Notifications):</label>
                <input type="password" name="dashd_telegram_bot_token" value="<?php echo esc_attr($telegram_token); ?>" class="regular-text" style="width:100%;" placeholder="123456789:AA..." <?php echo $telegram_token_const ? 'disabled' : ''; ?>>
                <p style="font-size: 11px; color: #666; margin-top: 5px;">Can be overridden via <code>DASHD_TELEGRAM_BOT_TOKEN</code> and <code>DASHD_TELEGRAM_CHAT_ID</code> in <code>wp-config.php</code>.</p>
                <?php if ($telegram_token_const): ?>
                    <p style="font-size: 11px; color: #2271b1; margin-top: 5px;"><?php esc_html_e('Value is currently managed by wp-config.php constant.', 'dashd-analytics-pro'); ?></p>
                <?php endif; ?>
            </div>

            <div class="uk-margin" style="margin-bottom: 15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Telegram Chat ID:</label>
                <input type="text" name="dashd_telegram_chat_id" value="<?php echo esc_attr($telegram_chat_id); ?>" class="regular-text" style="width:100%;" placeholder="418295385" <?php echo $telegram_chat_const ? 'disabled' : ''; ?>>
                <?php if ($telegram_chat_const): ?>
                    <p style="font-size: 11px; color: #2271b1; margin-top: 5px;"><?php esc_html_e('Value is currently managed by wp-config.php constant.', 'dashd-analytics-pro'); ?></p>
                <?php endif; ?>
            </div>

            <div class="uk-margin" style="margin-bottom: 15px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">CRM Webhook URL (For Leads):</label>
                <input type="url" name="dashd_crm_webhook" value="<?php echo esc_attr($crm_hook); ?>" class="regular-text" style="width:100%;" placeholder="https://hooks.zapier.com/hooks/catch/..." <?php echo $crm_webhook_const ? 'disabled' : ''; ?>>
                <p style="font-size: 11px; color: #666; margin-top: 5px;">Sends a JSON payload <code>{email, download_type, widget_source, timestamp}</code> when a user unlocks gated content.</p>
                <?php if ($crm_webhook_const): ?>
                    <p style="font-size: 11px; color: #2271b1; margin-top: 5px;"><?php esc_html_e('Value is currently managed by wp-config.php constant DASHD_CRM_WEBHOOK.', 'dashd-analytics-pro'); ?></p>
                <?php endif; ?>
            </div>

            <div class="uk-margin" style="margin-bottom: 25px;">
                <label style="font-weight:600; display:block; margin-bottom:5px;">Slack/Discord Webhook URL (For Anomalies):</label>
                <input type="url" name="dashd_slack_webhook" value="<?php echo esc_attr($slack_hook); ?>" class="regular-text" style="width:100%;" placeholder="https://hooks.slack.com/services/..." <?php echo $slack_webhook_const ? 'disabled' : ''; ?>>
                <p style="font-size: 11px; color: #666; margin-top: 5px;">Sends an instant alert if data spikes >300% during synchronization.</p>
                <?php if ($slack_webhook_const): ?>
                    <p style="font-size: 11px; color: #2271b1; margin-top: 5px;"><?php esc_html_e('Value is currently managed by wp-config.php constant DASHD_SLACK_WEBHOOK.', 'dashd-analytics-pro'); ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" name="save_webhooks" class="button button-primary" style="display:flex; align-items:center; gap:5px;">
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save Integrations', 'dashd-analytics-pro'); ?>
            </button>
        </form>
    </div>

    <div class="dashd-toolbar">
        <div class="dashd-toolbar-group">
            <p class="dashd-toolbar-title"><?php esc_html_e('Export Leads:', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0;">
                <input type="hidden" name="action" value="dashd_export_leads">
                <?php wp_nonce_field('dashd_export_leads', 'dashd_export_leads_nonce'); ?>
                <button type="submit" class="button button-primary" style="display:flex; align-items:center; gap:5px;">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e('Download as CSV', 'dashd-analytics-pro'); ?>
                </button>
            </form>
        </div>
    </div>

    <div class="dashd-table-container">
        <table class="dashd-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="text-align:left;"><?php esc_html_e('Date & Time', 'dashd-analytics-pro'); ?></th>
                    <th style="text-align:left;"><?php esc_html_e('Email Address', 'dashd-analytics-pro'); ?></th>
                    <th><?php esc_html_e('Download Type', 'dashd-analytics-pro'); ?></th>
                    <th><?php esc_html_e('Source Widget', 'dashd-analytics-pro'); ?></th>
                    <th><?php esc_html_e('Action', 'dashd-analytics-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if($leads): foreach($leads as $l): ?>
                <tr>
                    <td style="text-align:left;"><?php echo esc_html(function_exists('mysql2date') ? mysql2date('d.m.Y H:i', (string) ($l->created_at ?? ''), true) : wp_date('d.m.Y H:i', strtotime((string) ($l->created_at ?? '')))); ?></td>
                    <td style="text-align:left; font-weight:600;"><a href="mailto:<?php echo esc_attr($l->email); ?>"><?php echo esc_html($l->email); ?></a></td>
                    <td><span class="dashd-badge"><?php echo esc_html($l->download_type); ?></span></td>
                    <td><code><?php echo esc_html($l->widget_source); ?></code></td>
                    <td>
                        <form method="post" onsubmit="return confirm('<?php esc_attr_e('Delete this lead?', 'dashd-analytics-pro'); ?>');" style="margin:0;">
                            <input type="hidden" name="delete_lead" value="1">
                            <input type="hidden" name="lead_id" value="<?php echo (int) $l->id; ?>">
                            <?php wp_nonce_field('dashd_delete_lead_' . (int) $l->id, 'dashd_delete_lead_nonce'); ?>
                            <button type="submit" style="background:none; border:none; color:#d63638; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="padding:20px; text-align:center; color:#999;"><?php esc_html_e('No leads collected yet.', 'dashd-analytics-pro'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function dashd_render_sources_tab($sources) {
    global $wpdb;
    
    $src_filter = isset($_GET['source_filter'])
        ? (function_exists('dashd_normalize_source_key') ? dashd_normalize_source_key($_GET['source_filter']) : sanitize_key($_GET['source_filter']))
        : ((function_exists('dashd_normalize_source_key') ? dashd_normalize_source_key(($sources[0]->source_key ?? '')) : ($sources[0]->source_key ?? '')));
    $orderby    = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'data_year';
    $order      = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
    $paged      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page_requested = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 50;
    $max_per_page = (int) apply_filters('dashd_admin_max_per_page', 200);
    if ($max_per_page < 1) {
        $max_per_page = 200;
    }
    $per_page   = min($per_page_requested, $max_per_page);
    $edit_source_id = isset($_GET['edit_source']) ? max(0, (int) $_GET['edit_source']) : 0;
    $edit_source = null;

    if ($edit_source_id > 0 && !empty($sources)) {
        foreach ($sources as $src_item) {
            if ((int) ($src_item->id ?? 0) === $edit_source_id) {
                $edit_source = $src_item;
                break;
            }
        }
    }
    
    $offset = ($paged - 1) * $per_page;
    $order_sql = "r.data_year $order, r.data_quarter $order";
    if ($orderby === 'ind') $order_sql = "i.name_en $order";
    elseif ($orderby === 'cty') $order_sql = "c.name_en $order";

    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(r.id) FROM {$wpdb->prefix}dashd_data_records r WHERE r.source_key=%s", $src_filter));
    $total_pages = ceil($total_items / $per_page);

    $records = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, i.name_en as ind, c.name_en as cty FROM {$wpdb->prefix}dashd_data_records r 
        JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id=i.id 
        JOIN {$wpdb->prefix}dashd_countries c ON r.country_id=c.id 
        WHERE r.source_key=%s ORDER BY $order_sql LIMIT %d, %d", $src_filter, $offset, $per_page));

    $indicator_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT i.id, i.name_en
         FROM {$wpdb->prefix}dashd_data_records r
         JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
         WHERE r.source_key = %s
         ORDER BY COALESCE(i.sort_order, 0) ASC, i.name_en ASC",
        $src_filter
    ));
    if (empty($indicator_rows)) {
        $indicator_rows = $wpdb->get_results(
            "SELECT id, name_en FROM {$wpdb->prefix}dashd_indicators ORDER BY COALESCE(sort_order, 0) ASC, name_en ASC"
        );
    }

    $country_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT c.id, c.name_en
         FROM {$wpdb->prefix}dashd_data_records r
         JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
         WHERE r.source_key = %s
         ORDER BY COALESCE(c.sort_order, 0) ASC, c.name_en ASC",
        $src_filter
    ));
    if (empty($country_rows)) {
        $country_rows = $wpdb->get_results(
            "SELECT id, name_en FROM {$wpdb->prefix}dashd_countries ORDER BY COALESCE(sort_order, 0) ASC, name_en ASC"
        );
    }

    $build_sort_url = function($col) use ($orderby, $order, $src_filter, $per_page) {
        $new_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
        return "?page=dashd-settings&tab=sources&source_filter={$src_filter}&per_page={$per_page}&orderby={$col}&order={$new_order}";
    };
    
    $get_sort_icon = function($col) use ($orderby, $order) {
        if ($orderby !== $col) return '<span class="dashicons dashicons-sort" style="color:#ccc; font-size:14px; line-height:1.5;"></span>';
        return $order === 'ASC' ? '<span class="dashicons dashicons-arrow-up-alt2" style="font-size:14px; line-height:1.5; color:#2271b1;"></span>' : '<span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px; line-height:1.5; color:#2271b1;"></span>';
    };

    $build_sources_base_args = function() use ($src_filter, $per_page, $orderby, $order, $paged) {
        return [
            'page' => 'dashd-settings',
            'tab' => 'sources',
            'source_filter' => $src_filter,
            'per_page' => $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'paged' => $paged,
        ];
    };

    $build_edit_url = function($id) use ($build_sources_base_args) {
        $id = (int) $id;
        if ($id <= 0) {
            return add_query_arg($build_sources_base_args(), admin_url('admin.php'));
        }

        $args = $build_sources_base_args();
        $args['edit_source'] = $id;
        return add_query_arg($args, admin_url('admin.php'));
    };

    ?>
    <?php if (function_exists('dashd_render_admin_sync_controls')): ?>
    <div style="margin-top: 4px;">
        <?php dashd_render_admin_sync_controls(); ?>
    </div>
    <?php endif; ?>

    <div class="dashd-card" style="margin-top:20px;">
        <h3 style="margin-top:0;"><?php esc_html_e('Connected Sources', 'dashd-analytics-pro'); ?></h3>
        <div class="dashd-table-container">
            <table class="dashd-table" style="width:100%; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 10%;"><?php esc_html_e('Key', 'dashd-analytics-pro'); ?></th>
                        <th style="width: 5%;"><?php esc_html_e('Type', 'dashd-analytics-pro'); ?></th>
                        <th style="width: 5%;">API</th>
                        <th style="text-align:left; width: 20%;"><?php esc_html_e('Label', 'dashd-analytics-pro'); ?></th>
                        <th style="text-align:left; width: 50%;">URL & Headers</th>
                        <th style="width: 8%;"><?php esc_html_e('Action', 'dashd-analytics-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sources as $s): ?>
                    <tr>
                        <td style="overflow: hidden; text-overflow: ellipsis;"><code><?php echo esc_html($s->source_key); ?></code></td>
                        <td><span class="dashd-badge"><?php echo esc_html(strtoupper($s->source_type ?? 'CSV')); ?></span></td>
                        <td><strong><?php echo esc_html($s->api_method ?? 'GET'); ?></strong></td>
                        <td style="text-align:left; white-space: normal; word-wrap: break-word;"><?php echo esc_html($s->source_label); ?></td>
                        
                        <td style="text-align:left; font-size:11px; color:#646970; white-space: normal; word-break: break-all;">
                            <div style="color:#2271b1; font-weight:600; margin-bottom:3px;"><?php echo esc_html($s->source_url); ?></div>
                            <?php if(!empty($s->api_headers)): ?>
                                <code style="background:#f0f6fb; color:#646970; padding:2px 4px; border-radius:3px;">Headers: <?php echo esc_html($s->api_headers); ?></code>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <a href="<?php echo esc_url($build_edit_url((int) $s->id)); ?>" class="button button-small" title="<?php esc_attr_e('Edit source', 'dashd-analytics-pro'); ?>" style="padding: 0 6px; min-height: 28px;">
                                    <span class="dashicons dashicons-edit" style="line-height: 26px;"></span>
                                </a>
                                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return confirm('<?php esc_attr_e('Delete this source?', 'dashd-analytics-pro'); ?>');" style="margin:0;">
                                    <input type="hidden" name="action" value="dashd_delete_source">
                                    <input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
                                    <?php wp_nonce_field('dashd_delete_source_' . (int) $s->id, 'dashd_delete_source_nonce'); ?>
                                    <button type="submit" style="background:none; border:none; color:#d63638; cursor:pointer;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($edit_source): ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin-top: 15px; display: grid; gap: 10px; padding: 15px; border: 1px solid #dcdcde; border-radius: 6px; background: #f6f7f7;">
            <input type="hidden" name="action" value="dashd_update_source">
            <input type="hidden" name="id" value="<?php echo (int) $edit_source->id; ?>">
            <input type="hidden" name="return_source_filter" value="<?php echo esc_attr($src_filter); ?>">
            <input type="hidden" name="return_per_page" value="<?php echo (int) $per_page; ?>">
            <input type="hidden" name="return_orderby" value="<?php echo esc_attr($orderby); ?>">
            <input type="hidden" name="return_order" value="<?php echo esc_attr($order); ?>">
            <input type="hidden" name="return_paged" value="<?php echo (int) $paged; ?>">
            <?php wp_nonce_field('dashd_update_source_' . (int) $edit_source->id, 'dashd_update_source_nonce'); ?>

            <h4 style="margin: 0;"><?php esc_html_e('Edit Source', 'dashd-analytics-pro'); ?>: <code><?php echo esc_html((string) $edit_source->source_key); ?></code></h4>
            <p style="margin:0; color:#646970; font-size:12px;"><?php esc_html_e('Source key is immutable to preserve data integrity and shortcode compatibility.', 'dashd-analytics-pro'); ?></p>

            <div style="display:flex; flex-wrap: wrap; gap: 10px;">
                <input type="text" value="<?php echo esc_attr((string) $edit_source->source_key); ?>" disabled style="width: 120px; background:#f0f0f1;">
                <select name="s_type" required style="width: 100px;">
                    <option value="csv" <?php selected((string) ($edit_source->source_type ?? 'csv'), 'csv'); ?>>CSV</option>
                    <option value="json" <?php selected((string) ($edit_source->source_type ?? ''), 'json'); ?>>JSON</option>
                </select>
                <select name="s_method" required style="width: 90px;">
                    <option value="GET" <?php selected(strtoupper((string) ($edit_source->api_method ?? 'GET')), 'GET'); ?>>GET</option>
                    <option value="POST" <?php selected(strtoupper((string) ($edit_source->api_method ?? 'GET')), 'POST'); ?>>POST</option>
                </select>
                <input type="text" name="s_label" value="<?php echo esc_attr((string) ($edit_source->source_label ?? '')); ?>" required style="width: 220px;" placeholder="<?php esc_attr_e('Label', 'dashd-analytics-pro'); ?>">
            </div>

            <div style="display:flex; flex-wrap: wrap; gap: 10px;">
                <input type="url" name="s_url" value="<?php echo esc_attr((string) ($edit_source->source_url ?? '')); ?>" required style="flex: 1; min-width: 260px;" placeholder="<?php esc_attr_e('Data URL (API Endpoint)', 'dashd-analytics-pro'); ?>">
                <input type="text" name="s_headers" value="<?php echo esc_attr((string) ($edit_source->api_headers ?? '')); ?>" style="width: 260px;" placeholder='{"Auth": "Bearer..."}' title="Optional JSON format for Headers">
            </div>

            <div style="display:flex; gap: 8px; align-items:center;">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Source', 'dashd-analytics-pro'); ?></button>
                <a href="<?php echo esc_url($build_edit_url(0)); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'dashd-analytics-pro'); ?></a>
            </div>
        </form>
        <?php endif; ?>
        
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
            <input type="hidden" name="action" value="dashd_add_source">
            <?php wp_nonce_field('dashd_add_source', 'dashd_add_source_nonce'); ?>
            <input type="text" name="s_key" placeholder="<?php esc_attr_e('Source Key', 'dashd-analytics-pro'); ?>" required style="width: 100px;">
            <select name="s_type" required style="width: 90px;">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
            </select>
            <select name="s_method" required style="width: 80px;">
                <option value="GET">GET</option>
                <option value="POST">POST</option>
            </select>
            <input type="text" name="s_label" placeholder="<?php esc_attr_e('Label', 'dashd-analytics-pro'); ?>" required style="width: 150px;">
            <input type="url" name="s_url" placeholder="<?php esc_attr_e('Data URL (API Endpoint)', 'dashd-analytics-pro'); ?>" required style="flex: 1; min-width:200px;">
            <input type="text" name="s_headers" placeholder='{"Auth": "Bearer..."}' style="width: 180px;" title="Optional JSON format for Headers">
            <button type="submit" class="button button-primary"><?php esc_html_e('Add', 'dashd-analytics-pro'); ?></button>
        </form>
    </div>

    <div class="dashd-toolbar" style="margin-top: 40px;">
        <div class="dashd-toolbar-group">
            <p class="dashd-toolbar-title"><?php esc_html_e('Raw Data Management:', 'dashd-analytics-pro'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="margin:0;">
                <input type="hidden" name="action" value="dashd_export_raw_data">
                <?php wp_nonce_field('dashd_export_raw_data', 'dashd_export_raw_data_nonce'); ?>
                <button type="submit" class="button button-secondary" style="display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export All Raw Data', 'dashd-analytics-pro'); ?></button>
            </form>
        </div>
        <div class="dashd-toolbar-divider"></div>
        <div class="dashd-toolbar-group">
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; margin:0;">
                <input type="hidden" name="action" value="dashd_import_raw_data">
                <?php wp_nonce_field('dashd_import_raw_data', 'dashd_import_raw_data_nonce'); ?>
                <input type="file" name="csv_file" accept=".csv" required style="max-width: 200px;">
                <button type="submit" class="button button-primary" style="display:flex; align-items:center; gap:5px;"><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import Raw Data', 'dashd-analytics-pro'); ?></button>
            </form>
        </div>
    </div>

    <div class="tablenav top" style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
        <div class="alignleft actions">
            <form method="get" action="" style="display:flex; gap:20px; align-items:center;">
                <input type="hidden" name="page" value="dashd-settings">
                <input type="hidden" name="tab" value="sources">
                <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
                
                <div style="display:flex; align-items:center; gap:8px;">
                    <label for="source_filter_select" style="font-weight:600; margin:0;"><?php esc_html_e('Data Source:', 'dashd-analytics-pro'); ?></label>
                    <select name="source_filter" id="source_filter_select" onchange="this.form.submit()" style="max-width:200px; margin:0;">
                        <?php if(empty($sources)): ?>
                            <option value=""><?php esc_html_e('No sources connected', 'dashd-analytics-pro'); ?></option>
                        <?php else: foreach($sources as $src): ?>
                            <option value="<?php echo esc_attr($src->source_key); ?>" <?php selected($src_filter, $src->source_key); ?>>
                                <?php echo esc_html($src->source_label . ' (' . $src->source_key . ')'); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <div style="display:flex; align-items:center; gap:8px; border-left: 1px solid #dcdde1; padding-left: 20px;">
                    <label for="per_page_select" style="font-weight:600; margin:0;"><?php esc_html_e('Records per page:', 'dashd-analytics-pro'); ?></label>
                    <select name="per_page" id="per_page_select" onchange="this.form.submit()" style="margin:0;">
                        <option value="20" <?php selected($per_page, 20); ?>>20</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100</option>
                        <option value="500" <?php selected($per_page, 500); ?>>500</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="tablenav-pages">
            <span id="dashd-raw-items-count" class="displaying-num" data-count="<?php echo (int) $total_items; ?>" data-items-label="<?php echo esc_attr__('items', 'dashd-analytics-pro'); ?>" style="margin-right:10px; font-weight:600;"><?php echo number_format((int) $total_items, 0, '', ' '); ?> <?php esc_html_e('items', 'dashd-analytics-pro'); ?></span>
            <?php
            if ($total_pages > 1) {
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo; ' . __('Prev', 'dashd-analytics-pro'),
                    'next_text' => __('Next', 'dashd-analytics-pro') . ' &raquo;',
                    'total'     => $total_pages,
                    'current'   => $paged
                ]);
            }
            ?>
        </div>
    </div>

    <div class="dashd-card" style="margin-top:15px; border:1px solid #e5e7eb; background:#fafbfc;">
        <h4 style="margin:0 0 10px;"><?php esc_html_e('Manual Raw Data Entry', 'dashd-analytics-pro'); ?></h4>
        <p style="margin:0 0 12px; color:#646970; font-size:12px;">
            <?php esc_html_e('Add or update a single raw data point for the selected source.', 'dashd-analytics-pro'); ?>
        </p>
        <div style="display:grid; grid-template-columns: minmax(160px, 1.4fr) minmax(160px, 1.1fr) 90px 90px 140px auto; gap:10px; align-items:end;">
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12px; font-weight:600;"><?php esc_html_e('Indicator', 'dashd-analytics-pro'); ?></span>
                <select id="dashd-add-raw-indicator" class="regular-text">
                    <?php foreach ((array) $indicator_rows as $indicator_row): ?>
                        <option value="<?php echo (int) $indicator_row->id; ?>"><?php echo esc_html((string) $indicator_row->name_en); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12px; font-weight:600;"><?php esc_html_e('Country', 'dashd-analytics-pro'); ?></span>
                <select id="dashd-add-raw-country" class="regular-text">
                    <?php foreach ((array) $country_rows as $country_row): ?>
                        <option value="<?php echo (int) $country_row->id; ?>"><?php echo esc_html((string) $country_row->name_en); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12px; font-weight:600;"><?php esc_html_e('Year', 'dashd-analytics-pro'); ?></span>
                <input type="number" id="dashd-add-raw-year" min="1900" max="2100" value="<?php echo esc_attr((string) current_time('Y')); ?>">
            </label>
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12px; font-weight:600;"><?php esc_html_e('Quarter', 'dashd-analytics-pro'); ?></span>
                <select id="dashd-add-raw-quarter">
                    <option value="Q1">Q1</option>
                    <option value="Q2">Q2</option>
                    <option value="Q3">Q3</option>
                    <option value="Q4" selected>Q4</option>
                </select>
            </label>
            <label style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12px; font-weight:600;"><?php esc_html_e('Value', 'dashd-analytics-pro'); ?></span>
                <input type="number" step="any" id="dashd-add-raw-value" placeholder="0.00">
            </label>
            <button type="button" id="dashd-add-raw-submit" class="button button-primary" style="height:32px;">
                <?php esc_html_e('Save Data Point', 'dashd-analytics-pro'); ?>
            </button>
        </div>
    </div>

    <div class="dashd-table-container">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px; padding:10px 12px; border:1px solid #f1c6c8; background:#fff7f7; border-radius:6px;">
            <div>
                <div style="font-size:13px; font-weight:600; color:#8a1f2d; margin-bottom:3px;">
                    <?php esc_html_e('Bulk Delete Raw Data', 'dashd-analytics-pro'); ?>
                </div>
                <div style="font-size:12px; color:#7a1d2b;">
                    <?php esc_html_e('Select one or more rows and delete them. This operation is irreversible.', 'dashd-analytics-pro'); ?>
                </div>
            </div>
            <button type="button" id="dashd-delete-selected-raw" class="button" disabled style="display:flex; align-items:center; gap:6px; border-color:#d63638; color:#d63638; background:#fff;">
                <span class="dashicons dashicons-trash" style="font-size:16px; line-height:1;"></span>
                <?php esc_html_e('Delete Selected', 'dashd-analytics-pro'); ?>
            </button>
        </div>
        <table id="dashd-raw-table" class="dashd-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:4%; text-align:center;">
                        <input type="checkbox" id="dashd-raw-select-all" title="<?php esc_attr_e('Select all rows', 'dashd-analytics-pro'); ?>">
                    </th>
                    <th style="cursor:pointer; width:15%;"><a href="<?php echo esc_url($build_sort_url('data_year')); ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; justify-content:flex-start; gap:5px;"><?php esc_html_e('Period', 'dashd-analytics-pro'); ?> <?php echo $get_sort_icon('data_year'); ?></a></th>
                    <th style="cursor:pointer; width:40%;"><a href="<?php echo esc_url($build_sort_url('ind')); ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; justify-content:flex-start; gap:5px;"><?php esc_html_e('Indicator', 'dashd-analytics-pro'); ?> <?php echo $get_sort_icon('ind'); ?></a></th>
                    <th style="cursor:pointer; width:20%;"><a href="<?php echo esc_url($build_sort_url('cty')); ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; justify-content:flex-start; gap:5px;"><?php esc_html_e('Country', 'dashd-analytics-pro'); ?> <?php echo $get_sort_icon('cty'); ?></a></th>
                    <th style="width:25%;"><?php esc_html_e('Value', 'dashd-analytics-pro'); ?></th>
                </tr>
            </thead>
            <tbody id="dashd-raw-tbody">
                <?php if($records): foreach($records as $r): ?>
                <tr data-raw-record-id="<?php echo (int) $r->id; ?>">
                    <td style="text-align:center;">
                        <input type="checkbox" class="dashd-raw-select" value="<?php echo (int) $r->id; ?>" title="<?php esc_attr_e('Select row', 'dashd-analytics-pro'); ?>">
                    </td>
                    <td><?php echo esc_html($r->data_quarter.' '.$r->data_year); ?></td>
                    <td><?php echo esc_html($r->ind); ?></td>
                    <td><?php echo esc_html($r->cty); ?></td>
                    <td>
                        <div class="dashd-inline-edit" data-id="<?php echo (int) $r->id; ?>">
                            <div class="dashd-view-mode" style="display:flex; align-items:center; gap:8px;">
                                <strong class="dashd-current-val"><?php echo number_format($r->val, 2, '.', ' '); ?></strong>
                                <span class="dashicons dashicons-edit dashd-edit-trigger" title="<?php esc_attr_e('Edit Value', 'dashd-analytics-pro'); ?>"></span>
                            </div>
                            <div class="dashd-edit-mode" style="display:none; align-items:center; gap:5px;">
                                <input type="number" step="any" class="dashd-val-input" value="<?php echo esc_attr($r->val); ?>" style="width:110px; padding:0 5px; min-height:28px;">
                                <button type="button" class="button button-small dashd-save-trigger" style="min-width:0; padding:0 5px; height:28px;"><span class="dashicons dashicons-yes" style="color:#46b450; margin-top:2px;"></span></button>
                                <button type="button" class="button button-small dashd-cancel-trigger" style="min-width:0; padding:0 5px; height:28px;"><span class="dashicons dashicons-no" style="color:#d63638; margin-top:2px;"></span></button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr class="dashd-raw-empty-row"><td colspan="5" style="padding:20px; text-align:center; color:#999;"><?php esc_html_e('No records found for this source.', 'dashd-analytics-pro'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const i18n_settings = {
            errorUpdate: "<?php echo esc_js(__('Error updating value.', 'dashd-analytics-pro')); ?>",
            errorNet: "<?php echo esc_js(__('Network error during update.', 'dashd-analytics-pro')); ?>",
            deleteConfirm: "<?php echo esc_js(__('Delete selected records? This action cannot be undone.', 'dashd-analytics-pro')); ?>",
            errorDelete: "<?php echo esc_js(__('Error deleting selected records.', 'dashd-analytics-pro')); ?>",
            errorDeleteNet: "<?php echo esc_js(__('Network error during delete.', 'dashd-analytics-pro')); ?>",
            addMissing: "<?php echo esc_js(__('Please fill all fields for manual entry.', 'dashd-analytics-pro')); ?>",
            addFailed: "<?php echo esc_js(__('Failed to save raw data point.', 'dashd-analytics-pro')); ?>",
            addNet: "<?php echo esc_js(__('Network error during raw data save.', 'dashd-analytics-pro')); ?>",
            addSavedInserted: "<?php echo esc_js(__('Raw data point added.', 'dashd-analytics-pro')); ?>",
            addSavedUpdated: "<?php echo esc_js(__('Raw data point updated.', 'dashd-analytics-pro')); ?>",
            deleteSelected: "<?php echo esc_js(__('Delete Selected', 'dashd-analytics-pro')); ?>",
            itemsLabel: "<?php echo esc_js(__('items', 'dashd-analytics-pro')); ?>",
            emptyRows: "<?php echo esc_js(__('No records found for this source.', 'dashd-analytics-pro')); ?>"
        };

        const updateValueNonce = "<?php echo esc_js(wp_create_nonce('dashd_update_raw_value')); ?>";
        const deleteRawNonce = "<?php echo esc_js(wp_create_nonce('dashd_delete_raw_records')); ?>";
        const addRawNonce = "<?php echo esc_js(wp_create_nonce('dashd_add_raw_record')); ?>";
        const sourceKey = "<?php echo esc_js($src_filter); ?>";

        const addRawIndicator = document.getElementById('dashd-add-raw-indicator');
        const addRawCountry = document.getElementById('dashd-add-raw-country');
        const addRawYear = document.getElementById('dashd-add-raw-year');
        const addRawQuarter = document.getElementById('dashd-add-raw-quarter');
        const addRawValue = document.getElementById('dashd-add-raw-value');
        const addRawSubmit = document.getElementById('dashd-add-raw-submit');
        const bulkDeleteBtn = document.getElementById('dashd-delete-selected-raw');
        const selectAll = document.getElementById('dashd-raw-select-all');
        const rawItemsCountEl = document.getElementById('dashd-raw-items-count');
        const rawTbody = document.getElementById('dashd-raw-tbody');

        const escapeHtml = function(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const formatCount = function(value) {
            return String(Math.max(0, parseInt(value, 10) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        };

        const getRowCheckboxes = function() {
            return rawTbody ? rawTbody.querySelectorAll('.dashd-raw-select') : [];
        };

        const getSelectedRawIds = function() {
            return Array.from(rawTbody ? rawTbody.querySelectorAll('.dashd-raw-select:checked') : [])
                .map(function(el) { return parseInt(el.value, 10); })
                .filter(function(id) { return Number.isInteger(id) && id > 0; });
        };

        const getVisibleRecordRows = function() {
            return rawTbody ? rawTbody.querySelectorAll('tr[data-raw-record-id]') : [];
        };

        const setItemsCount = function(newCount) {
            if (!rawItemsCountEl) return;
            const count = Math.max(0, parseInt(newCount, 10) || 0);
            rawItemsCountEl.dataset.count = String(count);
            rawItemsCountEl.textContent = formatCount(count) + ' ' + i18n_settings.itemsLabel;
        };

        const adjustItemsCount = function(delta) {
            if (!rawItemsCountEl) return;
            const current = parseInt(rawItemsCountEl.dataset.count || '0', 10) || 0;
            setItemsCount(current + (parseInt(delta, 10) || 0));
        };

        const ensureEmptyRowState = function() {
            if (!rawTbody) return;
            const hasRows = getVisibleRecordRows().length > 0;
            const existingEmptyRow = rawTbody.querySelector('.dashd-raw-empty-row');
            if (hasRows && existingEmptyRow) {
                existingEmptyRow.remove();
                return;
            }
            if (!hasRows && !existingEmptyRow) {
                const tr = document.createElement('tr');
                tr.className = 'dashd-raw-empty-row';
                tr.innerHTML = '<td colspan="5" style="padding:20px; text-align:center; color:#999;">' + escapeHtml(i18n_settings.emptyRows) + '</td>';
                rawTbody.appendChild(tr);
            }
        };

        const renderBulkButtonLabel = function(selectedCount) {
            if (!bulkDeleteBtn) return;
            bulkDeleteBtn.textContent = '';
            const icon = document.createElement('span');
            icon.className = 'dashicons dashicons-trash';
            icon.style.fontSize = '16px';
            icon.style.lineHeight = '1';
            bulkDeleteBtn.appendChild(icon);
            const label = selectedCount > 0
                ? i18n_settings.deleteSelected + ' (' + selectedCount + ')'
                : i18n_settings.deleteSelected;
            bulkDeleteBtn.appendChild(document.createTextNode(' ' + label));
        };

        const updateBulkUiState = function() {
            if (!bulkDeleteBtn) return;
            const allRows = getRowCheckboxes();
            const selected = getSelectedRawIds();
            bulkDeleteBtn.disabled = selected.length === 0;
            renderBulkButtonLabel(selected.length);

            if (selectAll) {
                if (allRows.length === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = selected.length === allRows.length;
                    selectAll.indeterminate = selected.length > 0 && selected.length < allRows.length;
                }
            }
        };

        const attachInlineEdit = function(container) {
            if (!container) return;
            const viewMode = container.querySelector('.dashd-view-mode');
            const editMode = container.querySelector('.dashd-edit-mode');
            const input = container.querySelector('.dashd-val-input');
            const displayVal = container.querySelector('.dashd-current-val');
            const id = container.dataset.id;
            const editTrigger = container.querySelector('.dashd-edit-trigger');
            const cancelTrigger = container.querySelector('.dashd-cancel-trigger');
            const saveTrigger = container.querySelector('.dashd-save-trigger');
            if (!viewMode || !editMode || !input || !displayVal || !id || !editTrigger || !cancelTrigger || !saveTrigger) {
                return;
            }
            let originalVal = input.value;

            editTrigger.onclick = function() {
                viewMode.style.display = 'none';
                editMode.style.display = 'flex';
                input.focus();
            };

            cancelTrigger.onclick = function() {
                editMode.style.display = 'none';
                viewMode.style.display = 'flex';
                input.value = originalVal;
            };

            saveTrigger.onclick = async function() {
                const newVal = input.value;
                const fd = new FormData();
                fd.append('action', 'dashd_update_raw_value');
                fd.append('nonce', updateValueNonce);
                fd.append('id', id);
                fd.append('val', newVal);

                container.style.opacity = '0.5';
                container.style.pointerEvents = 'none';
                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json && json.success) {
                        displayVal.textContent = json.data.formatted;
                        originalVal = newVal;
                        editMode.style.display = 'none';
                        viewMode.style.display = 'flex';
                    } else {
                        alert(i18n_settings.errorUpdate);
                    }
                } catch (e) {
                    alert(i18n_settings.errorNet);
                }
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
            };

            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveTrigger.click();
                }
            });
        };

        const attachRowSelection = function(row) {
            if (!row) return;
            const checkbox = row.querySelector('.dashd-raw-select');
            if (checkbox) {
                checkbox.addEventListener('change', updateBulkUiState);
            }
        };

        const renderInlineEditCell = function(rowData) {
            return '' +
                '<div class="dashd-inline-edit" data-id="' + escapeHtml(rowData.id) + '">' +
                    '<div class="dashd-view-mode" style="display:flex; align-items:center; gap:8px;">' +
                        '<strong class="dashd-current-val">' + escapeHtml(rowData.val_formatted) + '</strong>' +
                        '<span class="dashicons dashicons-edit dashd-edit-trigger" title="<?php echo esc_attr__('Edit Value', 'dashd-analytics-pro'); ?>"></span>' +
                    '</div>' +
                    '<div class="dashd-edit-mode" style="display:none; align-items:center; gap:5px;">' +
                        '<input type="number" step="any" class="dashd-val-input" value="' + escapeHtml(rowData.val_raw) + '" style="width:110px; padding:0 5px; min-height:28px;">' +
                        '<button type="button" class="button button-small dashd-save-trigger" style="min-width:0; padding:0 5px; height:28px;"><span class="dashicons dashicons-yes" style="color:#46b450; margin-top:2px;"></span></button>' +
                        '<button type="button" class="button button-small dashd-cancel-trigger" style="min-width:0; padding:0 5px; height:28px;"><span class="dashicons dashicons-no" style="color:#d63638; margin-top:2px;"></span></button>' +
                    '</div>' +
                '</div>';
        };

        const upsertRawRow = function(rowData) {
            if (!rawTbody || !rowData || !rowData.id) return false;
            const rowId = parseInt(rowData.id, 10);
            if (!Number.isInteger(rowId) || rowId <= 0) return false;

            let row = rawTbody.querySelector('tr[data-raw-record-id="' + rowId + '"]');
            const isNew = !row;

            if (isNew) {
                row = document.createElement('tr');
                row.setAttribute('data-raw-record-id', String(rowId));
                row.innerHTML = '' +
                    '<td style="text-align:center;">' +
                        '<input type="checkbox" class="dashd-raw-select" value="' + escapeHtml(rowId) + '" title="<?php echo esc_attr__('Select row', 'dashd-analytics-pro'); ?>">' +
                    '</td>' +
                    '<td class="dashd-col-period"></td>' +
                    '<td class="dashd-col-indicator"></td>' +
                    '<td class="dashd-col-country"></td>' +
                    '<td class="dashd-col-value"></td>';
                rawTbody.prepend(row);
                attachRowSelection(row);
            }

            const periodCell = row.querySelector('.dashd-col-period') || row.children[1];
            const indicatorCell = row.querySelector('.dashd-col-indicator') || row.children[2];
            const countryCell = row.querySelector('.dashd-col-country') || row.children[3];
            const valueCell = row.querySelector('.dashd-col-value') || row.children[4];

            if (periodCell) periodCell.textContent = rowData.period || '';
            if (indicatorCell) indicatorCell.textContent = rowData.indicator || '';
            if (countryCell) countryCell.textContent = rowData.country || '';
            if (valueCell) valueCell.innerHTML = renderInlineEditCell(rowData);

            const inlineEditor = row.querySelector('.dashd-inline-edit');
            attachInlineEdit(inlineEditor);
            ensureEmptyRowState();
            return isNew;
        };

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                getRowCheckboxes().forEach(function(el) {
                    el.checked = !!selectAll.checked;
                });
                updateBulkUiState();
            });
        }

        getRowCheckboxes().forEach(function(el) {
            el.addEventListener('change', updateBulkUiState);
        });

        document.querySelectorAll('.dashd-inline-edit').forEach(function(container) {
            attachInlineEdit(container);
        });

        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', async function() {
                const selectedIds = getSelectedRawIds();
                if (!selectedIds.length) return;
                if (!window.confirm(i18n_settings.deleteConfirm)) return;

                bulkDeleteBtn.disabled = true;
                const fd = new FormData();
                fd.append('action', 'dashd_delete_raw_records');
                fd.append('nonce', deleteRawNonce);
                fd.append('ids', selectedIds.join(','));

                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json && json.success) {
                        selectedIds.forEach(function(id) {
                            const row = rawTbody ? rawTbody.querySelector('tr[data-raw-record-id="' + id + '"]') : null;
                            if (row) row.remove();
                        });
                        if (json.data && Number.isInteger(parseInt(json.data.deleted, 10))) {
                            adjustItemsCount(-parseInt(json.data.deleted, 10));
                        }
                        ensureEmptyRowState();
                    } else {
                        alert((json && json.data && json.data.msg) ? json.data.msg : i18n_settings.errorDelete);
                    }
                } catch (e) {
                    alert(i18n_settings.errorDeleteNet);
                } finally {
                    updateBulkUiState();
                }
            });
        }

        if (addRawSubmit) {
            addRawSubmit.addEventListener('click', async function() {
                const indicatorId = parseInt(addRawIndicator && addRawIndicator.value ? addRawIndicator.value : '0', 10);
                const countryId = parseInt(addRawCountry && addRawCountry.value ? addRawCountry.value : '0', 10);
                const year = parseInt(addRawYear && addRawYear.value ? addRawYear.value : '0', 10);
                const quarter = String(addRawQuarter && addRawQuarter.value ? addRawQuarter.value : '').toUpperCase();
                const value = String(addRawValue && addRawValue.value ? addRawValue.value : '').trim();

                if (!indicatorId || !countryId || !year || !quarter || value === '') {
                    alert(i18n_settings.addMissing);
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'dashd_add_raw_record');
                fd.append('nonce', addRawNonce);
                fd.append('source_key', sourceKey);
                fd.append('indicator_id', String(indicatorId));
                fd.append('country_id', String(countryId));
                fd.append('data_year', String(year));
                fd.append('data_quarter', quarter);
                fd.append('val', value);

                addRawSubmit.disabled = true;
                try {
                    const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json && json.success) {
                        const rowData = json.data && json.data.row ? json.data.row : null;
                        const mode = json.data && json.data.mode ? String(json.data.mode) : 'inserted';
                        const inserted = upsertRawRow(rowData);
                        if (mode === 'inserted' && inserted) {
                            adjustItemsCount(1);
                        }
                        if (addRawValue) {
                            addRawValue.value = '';
                            addRawValue.focus();
                        }
                        updateBulkUiState();
                        alert(mode === 'updated' ? i18n_settings.addSavedUpdated : i18n_settings.addSavedInserted);
                        return;
                    }
                    alert((json && json.data && json.data.msg) ? json.data.msg : i18n_settings.addFailed);
                } catch (e) {
                    alert(i18n_settings.addNet);
                } finally {
                    addRawSubmit.disabled = false;
                }
            });
        }

        ensureEmptyRowState();
        updateBulkUiState();
        if (rawItemsCountEl) {
            const initialCount = parseInt(rawItemsCountEl.dataset.count || '0', 10) || 0;
            setItemsCount(initialCount);
        }
    });
    </script>
    <?php
}

function dashd_render_lang_table($table_suffix, $title) {
    global $wpdb;
    $table = $wpdb->prefix . $table_suffix;
    $is_indicator = ($table_suffix === 'dashd_indicators');

    $all_sources = $wpdb->get_results("SELECT source_key, source_label FROM {$wpdb->prefix}dashd_settings");
    $allowed_target_sources = ['all' => true];
    if (is_array($all_sources)) {
        foreach ($all_sources as $src_item) {
            $src_key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) ($src_item->source_key ?? ''))
                : sanitize_key((string) ($src_item->source_key ?? ''));
            if ($src_key !== '') {
                $allowed_target_sources[$src_key] = true;
            }
        }
    }

    if (isset($_POST['delete_item'])) {
        check_admin_referer('dashd_lang_table_action', 'dashd_lang_table_nonce');
        $del_id = (int)$_POST['delete_item'];
        $wpdb->delete($table, ['id' => $del_id]);
        if ($is_indicator) $wpdb->delete("{$wpdb->prefix}dashd_data_records", ['indicator_id' => $del_id]);
        else $wpdb->delete("{$wpdb->prefix}dashd_data_records", ['country_id' => $del_id]);
        if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Item and ALL its data were permanently deleted.', 'dashd-analytics-pro') . '</p></div>';
    }

    if ($is_indicator && isset($_POST['add_calc_ind'])) {
        check_admin_referer('dashd_add_calc_ind', 'dashd_add_calc_ind_nonce');
        $new_ind_en_raw = isset($_POST['new_ind_en']) ? wp_unslash((string) $_POST['new_ind_en']) : '';
        $new_ind_formula_raw = isset($_POST['new_ind_formula']) ? wp_unslash((string) $_POST['new_ind_formula']) : '';
        $new_ind_source = isset($_POST['new_ind_source']) ? sanitize_key((string) $_POST['new_ind_source']) : 'all';

        $new_ind_name = function_exists('dashd_admin_limit_text')
            ? dashd_admin_limit_text($new_ind_en_raw, 255)
            : sanitize_text_field($new_ind_en_raw);
        $new_ind_formula = function_exists('dashd_normalize_calc_formula')
            ? dashd_normalize_calc_formula($new_ind_formula_raw)
            : sanitize_text_field($new_ind_formula_raw);

        if (!isset($allowed_target_sources[$new_ind_source])) {
            $new_ind_source = 'all';
        }

        if ($new_ind_name === '' || $new_ind_formula === '') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Calculated indicator was not added: invalid name or formula format.', 'dashd-analytics-pro') . '</p></div>';
        } else {
            $wpdb->insert($table, [
                'name_en' => $new_ind_name,
                'is_calculated' => 1,
                'formula' => $new_ind_formula,
                'target_source' => $new_ind_source,
                'sort_order' => 0
            ]);
            if (function_exists('dashd_process_calculated_indicators')) dashd_process_calculated_indicators(current_time('Y-m-d'));
            if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Calculated indicator added.', 'dashd-analytics-pro') . '</p></div>';
        }
    }

    if (isset($_POST['save_langs'])) {
        check_admin_referer('dashd_lang_table_action', 'dashd_lang_table_nonce');
        $invalid_formula_ids = [];
        foreach (($_POST['langs'] ?? []) as $id => $tr) {
            $update_data = [
                'name_uk' => sanitize_text_field($tr['uk']),
                'name_hy' => sanitize_text_field($tr['hy']),
                'name_ro' => sanitize_text_field($tr['ro']),
                'name_ka' => sanitize_text_field($tr['ka']),
                'sort_order' => isset($tr['sort_order']) ? (int)$tr['sort_order'] : 0 // Сохраняем сортировку
            ];
            
            if ($is_indicator) {
                $was_calc = (int)$wpdb->get_var($wpdb->prepare("SELECT is_calculated FROM $table WHERE id=%d", $id));
                $old_source = $wpdb->get_var($wpdb->prepare("SELECT target_source FROM $table WHERE id=%d", $id));
                
                $is_calc_now = isset($tr['is_calculated']) ? 1 : 0;
                $formula_raw = isset($tr['formula']) ? wp_unslash((string) $tr['formula']) : '';
                $formula_now = function_exists('dashd_normalize_calc_formula')
                    ? dashd_normalize_calc_formula($formula_raw)
                    : sanitize_text_field($formula_raw);
                $source_now = sanitize_key((string) ($tr['target_source'] ?? 'all'));
                if (!isset($allowed_target_sources[$source_now])) {
                    $source_now = 'all';
                }

                if ($is_calc_now === 1 && $formula_now === '') {
                    $is_calc_now = 0;
                    $invalid_formula_ids[] = (int) $id;
                }
                
                $update_data['is_calculated'] = $is_calc_now;
                $update_data['formula'] = $formula_now;
                $update_data['target_source'] = $source_now;

                if ($was_calc == 1) {
                    if ($is_calc_now == 0 || empty($formula_now)) {
                        $wpdb->delete("{$wpdb->prefix}dashd_data_records", ['indicator_id' => (int)$id]);
                    } elseif ($source_now !== 'all' && $source_now !== $old_source) {
                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}dashd_data_records WHERE indicator_id = %d AND source_key != %s", $id, $source_now));
                    }
                }
            }
            $wpdb->update($table, $update_data, ['id' => (int)$id]);
        }
        
        if ($is_indicator && function_exists('dashd_process_calculated_indicators')) dashd_process_calculated_indicators(current_time('Y-m-d'));
        if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches(); 
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Changes saved.', 'dashd-analytics-pro') . '</p></div>';
        if ($is_indicator && !empty($invalid_formula_ids)) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Some calculated indicators were disabled because their formula format is invalid.', 'dashd-analytics-pro') . '</p></div>';
        }
    }

    // Выводим с учетом сортировки
    $items = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, name_en ASC");
    ?>
    <form method="post">
        <?php wp_nonce_field('dashd_lang_table_action', 'dashd_lang_table_nonce'); ?>
        <div class="dashd-table-container">
            <table class="dashd-table" style="width:100%; table-layout: fixed; word-wrap: break-word;">
                <thead>
                    <tr>
                        <th style="width: <?php echo $is_indicator ? '15%' : '20%'; ?>; text-align:left;">
                            <?php esc_html_e('English (Key)', 'dashd-analytics-pro'); ?>
                        </th>
                        
                        <th style="width: 5%; text-align:center;">Order</th> <?php if ($is_indicator): ?>
                            <th style="width: 4%; text-align:center;" title="Calculated Indicator?">Calc?</th>
                            <th style="width: 10%; text-align:center;">Formula</th>
                            <th style="width: 10%; text-align:center;">Target Table</th>
                            <?php $lang_width = '12.5%'; ?>
                        <?php else: ?>
                            <?php $lang_width = '17.5%'; ?>
                        <?php endif; ?>

                        <th style="width: <?php echo $lang_width; ?>;"><?php esc_html_e('Ukrainian', 'dashd-analytics-pro'); ?></th>
                        <th style="width: <?php echo $lang_width; ?>;"><?php esc_html_e('Armenian', 'dashd-analytics-pro'); ?></th>
                        <th style="width: <?php echo $lang_width; ?>;"><?php esc_html_e('Romanian', 'dashd-analytics-pro'); ?></th>
                        <th style="width: <?php echo $lang_width; ?>;"><?php esc_html_e('Georgian', 'dashd-analytics-pro'); ?></th>
                        <th style="width: 6%; text-align:center;">Del</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $i): ?>
                    <tr <?php echo ($is_indicator && !empty($i->is_calculated)) ? 'style="background: #f0f6fb;"' : ''; ?>>
                        <td style="text-align:left; overflow: hidden; text-overflow: ellipsis;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <code style="color:#646970; font-size:10px; flex-shrink: 0;">ID:<?php echo (int) $i->id; ?></code>
                                <strong style="white-space: normal; line-height: 1.2;"><?php echo esc_html($i->name_en); ?></strong>
                            </div>
                        </td>
                        
                        <td style="text-align:center;">
                            <input type="number" name="langs[<?php echo $i->id; ?>][sort_order]" value="<?php echo esc_attr($i->sort_order ?? 0); ?>" style="width:100%; box-sizing: border-box; text-align:center; font-size:12px; padding: 2px;">
                        </td>

                        <?php if ($is_indicator): ?>
                            <td style="text-align:center;">
                                <input type="checkbox" name="langs[<?php echo $i->id; ?>][is_calculated]" value="1" <?php checked(!empty($i->is_calculated), true); ?>>
                            </td>
                            <td>
                                <input type="text" name="langs[<?php echo $i->id; ?>][formula]" class="regular-text" value="<?php echo esc_attr($i->formula ?? ''); ?>" style="width:100%; box-sizing: border-box; text-align:center; font-size: 11px;" placeholder="e.g. 5::-1Y" title="Format: IndID:CountryID:Offset">
                            </td>
                            <td>
                                <select name="langs[<?php echo $i->id; ?>][target_source]" style="width:100%; box-sizing:border-box; font-size:11px;">
                                    <option value="all" <?php selected(($i->target_source ?? 'all'), 'all'); ?>>All Tables</option>
                                    <?php foreach($all_sources as $src): ?>
                                        <option value="<?php echo esc_attr($src->source_key); ?>" <?php selected(($i->target_source ?? ''), $src->source_key); ?>><?php echo esc_html($src->source_key); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        <?php endif; ?>

                        <td><input type="text" name="langs[<?php echo $i->id; ?>][uk]" class="regular-text" value="<?php echo esc_attr($i->name_uk); ?>" style="width:100%; box-sizing: border-box;"></td>
                        <td><input type="text" name="langs[<?php echo $i->id; ?>][hy]" class="regular-text" value="<?php echo esc_attr($i->name_hy); ?>" style="width:100%; box-sizing: border-box;"></td>
                        <td><input type="text" name="langs[<?php echo $i->id; ?>][ro]" class="regular-text" value="<?php echo esc_attr($i->name_ro); ?>" style="width:100%; box-sizing: border-box;"></td>
                        <td><input type="text" name="langs[<?php echo $i->id; ?>][ka]" class="regular-text" value="<?php echo esc_attr($i->name_ka); ?>" style="width:100%; box-sizing: border-box;"></td>
                        
                        <td style="text-align:center;">
                            <button type="submit" name="delete_item" value="<?php echo (int) $i->id; ?>" style="background:none; border:none; color:#d63638; cursor:pointer; padding:5px;" onclick="return confirm('<?php esc_attr_e('Are you sure? This will delete the item AND ALL its data records from the database!', 'dashd-analytics-pro'); ?>');" title="Permanently Delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="submit"><input type="submit" name="save_langs" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'dashd-analytics-pro'); ?>"></p>
    </form>

    <?php if ($is_indicator): ?>
    <div class="dashd-card" style="margin-top: 30px; border-left: 4px solid #8b5cf6;">
        <h4 style="margin-top:0;">Create Calculated Indicator</h4>
        <form method="post" style="display:flex; flex-wrap: wrap; gap:10px; align-items:center;">
            <?php wp_nonce_field('dashd_add_calc_ind', 'dashd_add_calc_ind_nonce'); ?>
            <input type="text" name="new_ind_en" placeholder="New Indicator Name (English)" required style="width: 250px;">
            <input type="text" name="new_ind_formula" placeholder="Formula (e.g. 5::-1Y or 5:2:-1Q)" required style="width: 200px;">
            <select name="new_ind_source" required style="width: 150px;">
                <option value="all">-- All Tables --</option>
                <?php foreach($all_sources as $src): ?>
                    <option value="<?php echo esc_attr($src->source_key); ?>"><?php echo esc_html($src->source_key); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="add_calc_ind" class="button button-secondary">Add Calculated Metric</button>
        </form>
    </div>
    <?php endif; ?>
    <?php
}

// ---------------------------------------------------------
// ОБРАБОТЧИКИ IMPORT / EXPORT И AJAX
// ---------------------------------------------------------

if (!function_exists('dashd_csv_safe_value')) {
    /**
     * Prevent CSV/Excel formula injection for exported user/content data.
     */
    function dashd_csv_safe_value($value) {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            $string = (string) $value;
        } else {
            $string = wp_json_encode($value);
            if (!is_string($string)) {
                $string = '';
            }
        }

        $string = str_replace("\0", '', $string);
        $left_trimmed = ltrim($string);

        if ($left_trimmed === '') {
            return $string;
        }

        // Keep plain numbers as-is to avoid changing numeric semantics in CSV.
        if (is_numeric(str_replace(',', '.', $left_trimmed))) {
            return $string;
        }

        // If a cell can be interpreted as formula in spreadsheet apps, prefix apostrophe.
        if (preg_match('/^[=\+@]|^[\t\r\n]/', $left_trimmed) === 1 || preg_match('/^-/', $left_trimmed) === 1) {
            return "'" . $string;
        }

        return $string;
    }
}

if (!function_exists('dashd_csv_safe_row')) {
    function dashd_csv_safe_row(array $row) {
        return array_map('dashd_csv_safe_value', $row);
    }
}

if (!function_exists('dashd_admin_csv_upload_error_message')) {
    function dashd_admin_csv_upload_error_message($error_code) {
        $error_code = (int) $error_code;
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('Uploaded file is too large.', 'dashd-analytics-pro');
            case UPLOAD_ERR_PARTIAL:
                return __('File upload was interrupted. Please try again.', 'dashd-analytics-pro');
            case UPLOAD_ERR_NO_FILE:
                return __('No file uploaded.', 'dashd-analytics-pro');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return __('Server could not process uploaded file.', 'dashd-analytics-pro');
            case UPLOAD_ERR_OK:
            default:
                return __('Unable to upload file.', 'dashd-analytics-pro');
        }
    }
}

if (!function_exists('dashd_admin_validate_uploaded_csv')) {
    /**
     * Validate uploaded CSV file and return tmp path.
     */
    function dashd_admin_validate_uploaded_csv($field_name, $max_bytes = 0) {
        $field_name = (string) $field_name;
        if ($field_name === '' || empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            wp_die(__('No file uploaded.', 'dashd-analytics-pro'));
        }

        $upload = $_FILES[$field_name];
        $error_code = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
        if ($error_code !== UPLOAD_ERR_OK) {
            wp_die(dashd_admin_csv_upload_error_message($error_code));
        }

        $tmp_path = isset($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
        if ($tmp_path === '' || !is_uploaded_file($tmp_path) || !is_readable($tmp_path)) {
            wp_die(__('Uploaded file is not readable.', 'dashd-analytics-pro'));
        }

        $file_name = isset($upload['name']) ? (string) $upload['name'] : '';
        $extension = strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            wp_die(__('Only CSV files are allowed.', 'dashd-analytics-pro'));
        }

        $size = isset($upload['size']) ? (int) $upload['size'] : 0;
        if ($size <= 0) {
            wp_die(__('Uploaded CSV is empty.', 'dashd-analytics-pro'));
        }

        $max_bytes = (int) $max_bytes;
        if ($max_bytes > 0 && $size > $max_bytes) {
            wp_die(__('CSV file is too large.', 'dashd-analytics-pro'));
        }

        // Lightweight MIME checks (allow common CSV MIME variants).
        $allowed_mimes = [
            'text/csv',
            'text/plain',
            'text/x-csv',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];

        $client_mime = isset($upload['type']) ? strtolower(trim((string) $upload['type'])) : '';
        if ($client_mime !== '' && !in_array($client_mime, $allowed_mimes, true) && strpos($client_mime, 'text/') !== 0) {
            wp_die(__('Invalid file type. Please upload a valid CSV.', 'dashd-analytics-pro'));
        }

        if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected_mime = @finfo_file($finfo, $tmp_path);
                @finfo_close($finfo);

                if (is_string($detected_mime) && $detected_mime !== '') {
                    $detected_mime = strtolower(trim($detected_mime));
                    if (!in_array($detected_mime, $allowed_mimes, true) && strpos($detected_mime, 'text/') !== 0) {
                        wp_die(__('Invalid file type. Please upload a valid CSV.', 'dashd-analytics-pro'));
                    }
                }
            }
        }

        return $tmp_path;
    }
}

if (!function_exists('dashd_admin_limit_text')) {
    /**
     * Trim and limit text length for DB varchar safety.
     */
    function dashd_admin_limit_text($value, $max_len = 255) {
        $value = sanitize_text_field((string) $value);
        $max_len = max(1, (int) $max_len);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max_len);
        }

        return substr($value, 0, $max_len);
    }
}

add_action('admin_post_dashd_export_leads', 'dashd_handle_export_leads');
function dashd_handle_export_leads() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_export_leads', 'dashd_export_leads_nonce');
    global $wpdb;

    $results = $wpdb->get_results("SELECT email, download_type, widget_source, created_at FROM {$wpdb->prefix}dashd_leads ORDER BY created_at DESC", ARRAY_A);
    if (empty($results)) wp_die(__('No leads found.', 'dashd-analytics-pro'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashd_leads_' . current_time('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($output, dashd_csv_safe_row(array_keys($results[0]))); 
    foreach ($results as $row) {
        fputcsv($output, dashd_csv_safe_row($row));
    }
    fclose($output);
    exit;
}

add_action('admin_post_dashd_add_source', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die();
    }
    check_admin_referer('dashd_add_source', 'dashd_add_source_nonce');
    global $wpdb;
    
    // Подготавливаем Headers (убираем экранирование слешей от WP)
    $source_key_raw = isset($_POST['s_key']) ? wp_unslash((string) $_POST['s_key']) : '';
    $source_type_raw = isset($_POST['s_type']) ? wp_unslash((string) $_POST['s_type']) : 'csv';
    $source_label_raw = isset($_POST['s_label']) ? wp_unslash((string) $_POST['s_label']) : '';
    $source_url_raw = isset($_POST['s_url']) ? wp_unslash((string) $_POST['s_url']) : '';
    $api_method_raw = isset($_POST['s_method']) ? wp_unslash((string) $_POST['s_method']) : 'GET';
    $headers_raw = isset($_POST['s_headers']) ? wp_unslash((string) $_POST['s_headers']) : '';

    $source_key = function_exists('dashd_normalize_source_key')
        ? dashd_normalize_source_key($source_key_raw)
        : sanitize_key($source_key_raw);
    $source_type = function_exists('dashd_normalize_source_type') ? dashd_normalize_source_type($source_type_raw) : sanitize_key($source_type_raw);
    $source_label = function_exists('dashd_admin_limit_text')
        ? dashd_admin_limit_text($source_label_raw, 255)
        : sanitize_text_field($source_label_raw);
    $source_url = function_exists('dashd_sanitize_source_url') ? dashd_sanitize_source_url($source_url_raw) : esc_url_raw($source_url_raw);
    $api_method = function_exists('dashd_normalize_http_method') ? dashd_normalize_http_method($api_method_raw) : sanitize_text_field($api_method_raw);
    $headers = function_exists('dashd_sanitize_source_headers') ? dashd_sanitize_source_headers($headers_raw) : '';

    if ($source_key === '' || $source_label === '' || $source_url === '') {
        wp_redirect(admin_url("admin.php?page=dashd-settings&tab=sources&status=source_invalid"));
        exit;
    }

    $inserted = $wpdb->insert("{$wpdb->prefix}dashd_settings", [
        'source_key'   => $source_key,
        'source_type'  => $source_type,
        'source_label' => $source_label,
        'source_url'   => $source_url,
        'api_method'   => $api_method,
        'api_headers'  => $headers
    ]);
    if ($inserted === false) {
        wp_redirect(admin_url("admin.php?page=dashd-settings&tab=sources&status=source_exists"));
        exit;
    }
    if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
    wp_redirect(admin_url("admin.php?page=dashd-settings&tab=sources"));
    exit;
});

add_action('admin_post_dashd_delete_source', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die();
    }
    $source_id = intval($_POST['id'] ?? 0);
    check_admin_referer('dashd_delete_source_' . $source_id, 'dashd_delete_source_nonce');
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}dashd_settings", ['id' => intval($_POST['id'])]);
    if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
    wp_redirect(admin_url("admin.php?page=dashd-settings&tab=sources"));
    exit;
});

add_action('admin_post_dashd_update_source', function() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }

    global $wpdb;
    $source_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($source_id <= 0) {
        wp_redirect(admin_url('admin.php?page=dashd-settings&tab=sources&status=source_not_found'));
        exit;
    }

    check_admin_referer('dashd_update_source_' . $source_id, 'dashd_update_source_nonce');

    $return_source_filter = isset($_POST['return_source_filter'])
        ? (function_exists('dashd_normalize_source_key')
            ? dashd_normalize_source_key((string) $_POST['return_source_filter'])
            : sanitize_key((string) $_POST['return_source_filter']))
        : '';
    $return_per_page = isset($_POST['return_per_page']) ? max(1, (int) $_POST['return_per_page']) : 50;
    $return_orderby = isset($_POST['return_orderby']) ? sanitize_key((string) $_POST['return_orderby']) : 'data_year';
    if (!in_array($return_orderby, ['data_year', 'ind', 'cty'], true)) {
        $return_orderby = 'data_year';
    }
    $return_order = (isset($_POST['return_order']) && strtoupper((string) $_POST['return_order']) === 'ASC') ? 'ASC' : 'DESC';
    $return_paged = isset($_POST['return_paged']) ? max(1, (int) $_POST['return_paged']) : 1;

    $build_return_url = static function($status) use ($return_source_filter, $return_per_page, $return_orderby, $return_order, $return_paged, $source_id) {
        $args = [
            'page' => 'dashd-settings',
            'tab' => 'sources',
            'status' => sanitize_key((string) $status),
            'source_filter' => $return_source_filter,
            'per_page' => $return_per_page,
            'orderby' => $return_orderby,
            'order' => $return_order,
            'paged' => $return_paged,
            'edit_source' => $source_id,
        ];
        return add_query_arg($args, admin_url('admin.php'));
    };

    $existing = $wpdb->get_row($wpdb->prepare("SELECT id, source_key FROM {$wpdb->prefix}dashd_settings WHERE id = %d", $source_id));
    if (!$existing || empty($existing->id)) {
        wp_redirect($build_return_url('source_not_found'));
        exit;
    }

    $source_type_raw = isset($_POST['s_type']) ? wp_unslash((string) $_POST['s_type']) : 'csv';
    $source_label_raw = isset($_POST['s_label']) ? wp_unslash((string) $_POST['s_label']) : '';
    $source_url_raw = isset($_POST['s_url']) ? wp_unslash((string) $_POST['s_url']) : '';
    $api_method_raw = isset($_POST['s_method']) ? wp_unslash((string) $_POST['s_method']) : 'GET';
    $headers_raw = isset($_POST['s_headers']) ? wp_unslash((string) $_POST['s_headers']) : '';

    $source_type = function_exists('dashd_normalize_source_type') ? dashd_normalize_source_type($source_type_raw) : sanitize_key($source_type_raw);
    $source_label = function_exists('dashd_admin_limit_text') ? dashd_admin_limit_text($source_label_raw, 255) : sanitize_text_field($source_label_raw);
    $source_url = function_exists('dashd_sanitize_source_url') ? dashd_sanitize_source_url($source_url_raw) : esc_url_raw($source_url_raw);
    $api_method = function_exists('dashd_normalize_http_method') ? dashd_normalize_http_method($api_method_raw) : sanitize_text_field($api_method_raw);
    $headers = function_exists('dashd_sanitize_source_headers') ? dashd_sanitize_source_headers($headers_raw) : '';

    if ($source_label === '' || $source_url === '') {
        wp_redirect($build_return_url('source_update_invalid'));
        exit;
    }

    $updated = $wpdb->update(
        "{$wpdb->prefix}dashd_settings",
        [
            'source_type' => $source_type,
            'source_label' => $source_label,
            'source_url' => $source_url,
            'api_method' => $api_method,
            'api_headers' => $headers,
        ],
        ['id' => $source_id]
    );

    if ($updated === false) {
        wp_redirect($build_return_url('source_update_invalid'));
        exit;
    }

    if (function_exists('dashd_clear_all_caches')) {
        dashd_clear_all_caches();
    }

    $success_url = add_query_arg(
        [
            'page' => 'dashd-settings',
            'tab' => 'sources',
            'status' => 'source_updated',
            'source_filter' => $return_source_filter,
            'per_page' => $return_per_page,
            'orderby' => $return_orderby,
            'order' => $return_order,
            'paged' => $return_paged,
        ],
        admin_url('admin.php')
    );
    wp_redirect($success_url);
    exit;
});

add_action('wp_ajax_dashd_update_raw_value', 'dashd_handle_update_raw_value');
function dashd_handle_update_raw_value() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST', true);
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(true);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_ajax_referer('dashd_update_raw_value', 'nonce');
    global $wpdb;
    
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(['msg' => __('Invalid record ID.', 'dashd-analytics-pro')]);
    }

    $raw_val = isset($_POST['val']) ? (string) wp_unslash($_POST['val']) : '0';
    $normalized_val = str_replace([' ', ','], ['', '.'], $raw_val);
    $normalized_val = trim((string) $normalized_val);
    if ($normalized_val === '' || preg_match('/^-?(?:\d+|\d*\.\d+)$/', $normalized_val) !== 1) {
        wp_send_json_error(['msg' => __('Invalid numeric value.', 'dashd-analytics-pro')]);
    }

    $val = (float) $normalized_val;
    if (!is_finite($val)) {
        wp_send_json_error(['msg' => __('Numeric value is out of allowed range.', 'dashd-analytics-pro')]);
    }

    $max_abs_value = (float) apply_filters('dashd_max_raw_value_abs', 1000000000000.0);
    if (!is_finite($max_abs_value) || $max_abs_value <= 0) {
        $max_abs_value = 1000000000000.0;
    }
    if (abs($val) > $max_abs_value) {
        wp_send_json_error(['msg' => __('Numeric value is out of allowed range.', 'dashd-analytics-pro')]);
    }
    
    $updated = $wpdb->update("{$wpdb->prefix}dashd_data_records", ['val' => $val], ['id' => $id]);
    
    if ($updated !== false) {
        if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
        wp_send_json_success(['formatted' => number_format($val, 2, '.', ' ')]);
    } else {
        wp_send_json_error();
    }
}

add_action('wp_ajax_dashd_delete_raw_records', 'dashd_handle_delete_raw_records');
function dashd_handle_delete_raw_records() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST', true);
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(true);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_ajax_referer('dashd_delete_raw_records', 'nonce');
    global $wpdb;

    $ids_raw = isset($_POST['ids']) ? (string) wp_unslash($_POST['ids']) : '';
    $ids = [];
    foreach (preg_split('/[,\s]+/', $ids_raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[$id] = $id;
        }
        if (count($ids) >= 1000) {
            break;
        }
    }
    $ids = array_values($ids);

    if (empty($ids)) {
        wp_send_json_error(['msg' => __('No valid record IDs provided.', 'dashd-analytics-pro')]);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = "DELETE FROM {$wpdb->prefix}dashd_data_records WHERE id IN ($placeholders)";
    $deleted = $wpdb->query($wpdb->prepare($sql, ...$ids));

    if ($deleted === false) {
        wp_send_json_error(['msg' => __('Failed to delete records.', 'dashd-analytics-pro')]);
    }

    if (function_exists('dashd_clear_all_caches')) {
        dashd_clear_all_caches();
    }

    wp_send_json_success([
        'deleted' => (int) $deleted,
        'ids' => $ids,
    ]);
}

add_action('wp_ajax_dashd_add_raw_record', 'dashd_handle_add_raw_record');
function dashd_handle_add_raw_record() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST', true);
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(true);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_ajax_referer('dashd_add_raw_record', 'nonce');
    global $wpdb;

    $source_key = isset($_POST['source_key'])
        ? (function_exists('dashd_normalize_source_key') ? dashd_normalize_source_key((string) wp_unslash($_POST['source_key'])) : sanitize_key((string) wp_unslash($_POST['source_key'])))
        : '';
    if ($source_key === '') {
        wp_send_json_error(['msg' => __('Invalid source key.', 'dashd-analytics-pro')]);
    }

    $source_exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dashd_settings WHERE source_key = %s",
        $source_key
    ));
    if ($source_exists < 1) {
        wp_send_json_error(['msg' => __('Source does not exist.', 'dashd-analytics-pro')]);
    }

    $indicator_id = isset($_POST['indicator_id']) ? (int) $_POST['indicator_id'] : 0;
    $country_id = isset($_POST['country_id']) ? (int) $_POST['country_id'] : 0;
    $data_year = isset($_POST['data_year']) ? (int) $_POST['data_year'] : 0;
    $data_quarter = isset($_POST['data_quarter']) ? strtoupper(trim((string) wp_unslash($_POST['data_quarter']))) : '';
    $raw_val = isset($_POST['val']) ? (string) wp_unslash($_POST['val']) : '';

    if ($indicator_id <= 0 || $country_id <= 0) {
        wp_send_json_error(['msg' => __('Invalid indicator or country.', 'dashd-analytics-pro')]);
    }
    if ($data_year < 1900 || $data_year > 2100) {
        wp_send_json_error(['msg' => __('Invalid year.', 'dashd-analytics-pro')]);
    }
    if (!in_array($data_quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
        wp_send_json_error(['msg' => __('Invalid quarter.', 'dashd-analytics-pro')]);
    }

    $normalized_val = trim((string) str_replace([' ', ','], ['', '.'], $raw_val));
    if ($normalized_val === '' || preg_match('/^-?(?:\d+|\d*\.\d+)$/', $normalized_val) !== 1) {
        wp_send_json_error(['msg' => __('Invalid numeric value.', 'dashd-analytics-pro')]);
    }
    $val = (float) $normalized_val;
    if (!is_finite($val)) {
        wp_send_json_error(['msg' => __('Numeric value is out of allowed range.', 'dashd-analytics-pro')]);
    }

    $max_abs_value = (float) apply_filters('dashd_max_raw_value_abs', 1000000000000.0);
    if (!is_finite($max_abs_value) || $max_abs_value <= 0) {
        $max_abs_value = 1000000000000.0;
    }
    if (abs($val) > $max_abs_value) {
        wp_send_json_error(['msg' => __('Numeric value is out of allowed range.', 'dashd-analytics-pro')]);
    }

    $indicator_exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dashd_indicators WHERE id = %d",
        $indicator_id
    ));
    $country_exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dashd_countries WHERE id = %d",
        $country_id
    ));
    if ($indicator_exists < 1 || $country_exists < 1) {
        wp_send_json_error(['msg' => __('Selected indicator or country does not exist.', 'dashd-analytics-pro')]);
    }

    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}dashd_data_records
         WHERE source_key=%s AND indicator_id=%d AND country_id=%d AND data_year=%d AND data_quarter=%s
         LIMIT 1",
        $source_key,
        $indicator_id,
        $country_id,
        $data_year,
        $data_quarter
    ));

    $result = false;
    $mode = 'inserted';
    if ($existing_id > 0) {
        $result = $wpdb->update(
            "{$wpdb->prefix}dashd_data_records",
            ['val' => $val, 'record_date' => current_time('mysql')],
            ['id' => $existing_id]
        );
        $mode = 'updated';
    } else {
        $result = $wpdb->insert(
            "{$wpdb->prefix}dashd_data_records",
            [
                'source_key' => $source_key,
                'indicator_id' => $indicator_id,
                'country_id' => $country_id,
                'data_year' => $data_year,
                'data_quarter' => $data_quarter,
                'val' => $val,
                'record_date' => current_time('mysql'),
            ],
            ['%s', '%d', '%d', '%d', '%s', '%f', '%s']
        );
    }

    if ($result === false) {
        wp_send_json_error(['msg' => __('Failed to save raw data point.', 'dashd-analytics-pro')]);
    }

    if (function_exists('dashd_clear_all_caches')) {
        dashd_clear_all_caches();
    }

    $saved_id = $existing_id > 0 ? $existing_id : (int) $wpdb->insert_id;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT r.id, r.data_year, r.data_quarter, r.val, i.name_en AS ind, c.name_en AS cty
         FROM {$wpdb->prefix}dashd_data_records r
         JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
         JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
         WHERE r.id = %d
         LIMIT 1",
        $saved_id
    ));

    $row_payload = null;
    if ($row) {
        $row_payload = [
            'id' => (int) $row->id,
            'period' => (string) $row->data_quarter . ' ' . (string) $row->data_year,
            'indicator' => (string) $row->ind,
            'country' => (string) $row->cty,
            'val_raw' => (string) $row->val,
            'val_formatted' => number_format((float) $row->val, 2, '.', ' '),
        ];
    }

    wp_send_json_success([
        'mode' => $mode,
        'id' => $saved_id,
        'row' => $row_payload,
    ]);
}

add_action('admin_post_dashd_export_csv', 'dashd_handle_export_csv');
function dashd_handle_export_csv() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_export_csv', 'dashd_export_csv_nonce');
    global $wpdb;
    
    $tab = sanitize_key($_POST['tab'] ?? 'sources');
    if ($tab === 'countries') $table = "{$wpdb->prefix}dashd_countries";
    elseif ($tab === 'indicators') $table = "{$wpdb->prefix}dashd_indicators";
    else $table = "{$wpdb->prefix}dashd_settings";

    $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    if (empty($results)) wp_die(__('No data to export in this tab.', 'dashd-analytics-pro'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashd_' . $tab . '_export_' . current_time('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) )); 
    fputcsv($output, dashd_csv_safe_row(array_keys($results[0]))); 
    foreach ($results as $row) {
        fputcsv($output, dashd_csv_safe_row($row));
    }
    fclose($output);
    exit;
}

add_action('admin_post_dashd_import_csv', 'dashd_handle_import_csv');
function dashd_handle_import_csv() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_import_csv', 'dashd_import_csv_nonce');
    global $wpdb;

    $tab = sanitize_key($_POST['tab'] ?? 'sources');
    if ($tab === 'countries') {
        $table = "{$wpdb->prefix}dashd_countries";
        $unique_key = 'name_en';
    } elseif ($tab === 'indicators') {
        $table = "{$wpdb->prefix}dashd_indicators";
        $unique_key = 'name_en';
    } else {
        $table = "{$wpdb->prefix}dashd_settings";
        $unique_key = 'source_key';
    }

    $known_sources = ['all' => true];
    $source_rows = $wpdb->get_col("SELECT source_key FROM {$wpdb->prefix}dashd_settings");
    if (is_array($source_rows)) {
        foreach ($source_rows as $src_key) {
            $normalized_key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) $src_key)
                : sanitize_key((string) $src_key);
            if ($normalized_key !== '') {
                $known_sources[$normalized_key] = true;
            }
        }
    }

    $default_csv_max_bytes = (defined('MB_IN_BYTES') ? MB_IN_BYTES : 1048576) * 2;
    $csv_max_bytes = (int) apply_filters('dashd_import_csv_max_bytes', $default_csv_max_bytes, $tab);
    $csv_max_rows = (int) apply_filters('dashd_import_csv_max_rows', 50000, $tab);
    $csv_max_columns = (int) apply_filters('dashd_import_csv_max_columns', 80, $tab);
    if ($csv_max_rows < 1) {
        $csv_max_rows = 1;
    }
    if ($csv_max_columns < 2) {
        $csv_max_columns = 2;
    }

    $tmp_path = dashd_admin_validate_uploaded_csv('csv_file', $csv_max_bytes);
    $file = fopen($tmp_path, 'rb');
    if (!is_resource($file)) {
        wp_die(__('Unable to read uploaded CSV file.', 'dashd-analytics-pro'));
    }
    $bom = fread($file, 3);
    if ($bom !== b"\xEF\xBB\xBF") rewind($file);

    $headers = fgetcsv($file);
    if (!$headers) wp_die(__('Invalid or empty CSV file.', 'dashd-analytics-pro'));
    $headers = array_map('trim', $headers); 
    if (count($headers) > $csv_max_columns) {
        fclose($file);
        wp_die(__('CSV has too many columns.', 'dashd-analytics-pro'));
    }
    
    $imported_count = 0;
    $row_count = 0;
    while (($data = fgetcsv($file)) !== false) {
        $row_count++;
        if ($row_count > $csv_max_rows) {
            break;
        }
        if (count($headers) !== count($data)) continue;
        $row = array_combine($headers, $data);
        if (!is_array($row)) continue;
        if (empty($row[$unique_key])) continue; 

        $key_value = sanitize_text_field((string) $row[$unique_key]);
        $update_data = [];

        // Harden imported source settings.
        if ($table === "{$wpdb->prefix}dashd_settings") {
            $source_key = function_exists('dashd_normalize_source_key')
                ? dashd_normalize_source_key((string) ($row['source_key'] ?? $key_value))
                : sanitize_key((string) ($row['source_key'] ?? $key_value));
            $source_type = function_exists('dashd_normalize_source_type') ? dashd_normalize_source_type((string) ($row['source_type'] ?? 'csv')) : 'csv';
            $source_label = dashd_admin_limit_text((string) ($row['source_label'] ?? $source_key), 255);
            $source_url = function_exists('dashd_sanitize_source_url') ? dashd_sanitize_source_url((string) ($row['source_url'] ?? '')) : esc_url_raw((string) ($row['source_url'] ?? ''));
            $api_method = function_exists('dashd_normalize_http_method') ? dashd_normalize_http_method((string) ($row['api_method'] ?? 'GET')) : 'GET';
            $api_headers = function_exists('dashd_sanitize_source_headers') ? dashd_sanitize_source_headers((string) ($row['api_headers'] ?? '')) : '';

            if ($source_key === '' || $source_label === '' || $source_url === '') {
                continue;
            }

            $key_value = $source_key;
            $update_data = [
                'source_key'   => $source_key,
                'source_type'  => $source_type,
                'source_label' => $source_label,
                'source_url'   => $source_url,
                'api_method'   => $api_method,
                'api_headers'  => $api_headers
            ];
        } elseif ($table === "{$wpdb->prefix}dashd_indicators") {
            $name_en = dashd_admin_limit_text((string) ($row['name_en'] ?? $key_value), 255);
            if ($name_en === '') {
                continue;
            }

            $target_source = sanitize_key((string) ($row['target_source'] ?? 'all'));
            if ($target_source === '' || !isset($known_sources[$target_source])) {
                $target_source = 'all';
            }

            $is_calculated = !empty($row['is_calculated']) ? 1 : 0;
            $formula_raw = (string) ($row['formula'] ?? '');
            $formula = $is_calculated
                ? (function_exists('dashd_normalize_calc_formula')
                    ? dashd_normalize_calc_formula($formula_raw)
                    : dashd_admin_limit_text($formula_raw, 255))
                : '';
            if ($is_calculated === 1 && $formula === '') {
                $is_calculated = 0;
            }
            $sort_order = isset($row['sort_order']) ? (int) $row['sort_order'] : 0;

            $key_value = $name_en;
            $update_data = [
                'name_en'       => $name_en,
                'name_uk'       => dashd_admin_limit_text((string) ($row['name_uk'] ?? ''), 255),
                'name_hy'       => dashd_admin_limit_text((string) ($row['name_hy'] ?? ''), 255),
                'name_ro'       => dashd_admin_limit_text((string) ($row['name_ro'] ?? ''), 255),
                'name_ka'       => dashd_admin_limit_text((string) ($row['name_ka'] ?? ''), 255),
                'is_calculated' => $is_calculated,
                'formula'       => $formula,
                'target_source' => $target_source,
                'sort_order'    => $sort_order,
            ];
        } else {
            // Countries dictionary: import only known fields (ignore unknown CSV columns).
            $name_en = dashd_admin_limit_text((string) ($row['name_en'] ?? $key_value), 255);
            if ($name_en === '') {
                continue;
            }

            $sort_order = isset($row['sort_order']) ? (int) $row['sort_order'] : 0;
            $key_value = $name_en;
            $update_data = [
                'name_en'    => $name_en,
                'name_uk'    => dashd_admin_limit_text((string) ($row['name_uk'] ?? ''), 255),
                'name_hy'    => dashd_admin_limit_text((string) ($row['name_hy'] ?? ''), 255),
                'name_ro'    => dashd_admin_limit_text((string) ($row['name_ro'] ?? ''), 255),
                'name_ka'    => dashd_admin_limit_text((string) ($row['name_ka'] ?? ''), 255),
                'sort_order' => $sort_order,
            ];
        }

        if (empty($update_data)) {
            continue;
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE $unique_key = %s", $key_value));
        if ($existing_id > 0) {
            $updated = $wpdb->update($table, $update_data, ['id' => $existing_id]);
            if ($updated !== false) {
                $imported_count++;
            }
        } else {
            $inserted = $wpdb->insert($table, $update_data);
            if ($inserted !== false) {
                $imported_count++;
            }
        }
    }
    fclose($file);
    if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
    wp_redirect(admin_url("admin.php?page=dashd-settings&tab={$tab}&imported={$imported_count}"));
    exit;
}

add_action('admin_post_dashd_export_raw_data', 'dashd_handle_export_raw_data');
function dashd_handle_export_raw_data() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_export_raw_data', 'dashd_export_raw_data_nonce');
    global $wpdb;

    $results = $wpdb->get_results("
        SELECT r.source_key, i.name_en as indicator, c.name_en as country, r.val, r.data_year, r.data_quarter, r.record_date
        FROM {$wpdb->prefix}dashd_data_records r
        JOIN {$wpdb->prefix}dashd_indicators i ON r.indicator_id = i.id
        JOIN {$wpdb->prefix}dashd_countries c ON r.country_id = c.id
        ORDER BY r.data_year DESC, r.data_quarter DESC, i.name_en ASC
    ", ARRAY_A);

    if (empty($results)) wp_die(__('No raw data found.', 'dashd-analytics-pro'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dashd_raw_data_' . current_time('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($output, dashd_csv_safe_row(array_keys($results[0]))); 
    foreach ($results as $row) {
        fputcsv($output, dashd_csv_safe_row($row));
    }
    fclose($output);
    exit;
}

add_action('admin_post_dashd_import_raw_data', 'dashd_handle_import_raw_data');
function dashd_handle_import_raw_data() {
    if (function_exists('dashd_enforce_http_method')) {
        dashd_enforce_http_method('POST');
    }

    if (!current_user_can('manage_options')) {
        if (function_exists('dashd_forbidden_response')) {
            dashd_forbidden_response(false);
        }
        wp_die(__('Access denied', 'dashd-analytics-pro'));
    }
    check_admin_referer('dashd_import_raw_data', 'dashd_import_raw_data_nonce');
    global $wpdb;

    $default_raw_max_bytes = (defined('MB_IN_BYTES') ? MB_IN_BYTES : 1048576) * 8;
    $raw_max_bytes = (int) apply_filters('dashd_import_raw_max_bytes', $default_raw_max_bytes);
    $raw_max_rows = (int) apply_filters('dashd_import_raw_max_rows', 200000);
    $raw_max_columns = (int) apply_filters('dashd_import_raw_max_columns', 40);
    if ($raw_max_rows < 1) {
        $raw_max_rows = 1;
    }
    if ($raw_max_columns < 2) {
        $raw_max_columns = 2;
    }

    $tmp_path = dashd_admin_validate_uploaded_csv('csv_file', $raw_max_bytes);
    $file = fopen($tmp_path, 'rb');
    if (!is_resource($file)) {
        wp_die(__('Unable to read uploaded CSV file.', 'dashd-analytics-pro'));
    }
    $bom = fread($file, 3);
    if ($bom !== b"\xEF\xBB\xBF") rewind($file);

    $headers = fgetcsv($file);
    if (!$headers) wp_die(__('Invalid CSV', 'dashd-analytics-pro'));
    $headers = array_map('trim', $headers);
    if (count($headers) > $raw_max_columns) {
        fclose($file);
        wp_die(__('CSV has too many columns.', 'dashd-analytics-pro'));
    }

    $imported_count = 0;
    $sync_date = current_time('Y-m-d');
    $row_count = 0;
    $dictionary_service = class_exists('DashD_Sync_Dictionary_Service')
        ? new DashD_Sync_Dictionary_Service()
        : null;
    $record_store_map = [];

    while (($data = fgetcsv($file)) !== false) {
        $row_count++;
        if ($row_count > $raw_max_rows) {
            break;
        }
        if (count($headers) !== count($data)) continue;
        $row = array_combine($headers, $data);
        if (!is_array($row)) continue;

        $source_key = function_exists('dashd_normalize_source_key')
            ? dashd_normalize_source_key((string) ($row['source_key'] ?? ''))
            : sanitize_key((string) ($row['source_key'] ?? ''));
        $ind_name   = sanitize_text_field($row['indicator'] ?? '');
        $cty_name   = sanitize_text_field($row['country'] ?? '');
        $val_raw = trim((string) ($row['val'] ?? ''));
        if ($val_raw === '') {
            continue;
        }
        $val_normalized = str_replace([' ', ','], ['', '.'], $val_raw);
        if (!preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:[eE][+-]?\d+)?$/', $val_normalized)) {
            continue;
        }
        $val = (float) $val_normalized;
        if (!is_finite($val)) {
            continue;
        }
        $max_abs = (float) apply_filters('dashd_max_raw_value_abs', 1e12);
        if ($max_abs <= 0 || !is_finite($max_abs)) {
            $max_abs = 1e12;
        }
        if (abs($val) > $max_abs) {
            continue;
        }
        $year       = (int)($row['data_year'] ?? 0);
        $quarter_raw = strtoupper((string) sanitize_text_field($row['data_quarter'] ?? ''));
        $quarter = in_array($quarter_raw, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $quarter_raw : '';

        if (!$source_key || !$ind_name || !$cty_name || !$year || !$quarter) continue;
        if ($year < 1900 || $year > 2200) continue;

        if ($dictionary_service instanceof DashD_Sync_Dictionary_Service) {
            $iid = (int) $dictionary_service->get_indicator_id($ind_name);
            $cid = (int) $dictionary_service->get_country_id($cty_name);
        } else {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}dashd_indicators (name_en) VALUES (%s)", $ind_name));
            $iid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dashd_indicators WHERE name_en = %s", $ind_name));

            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}dashd_countries (name_en) VALUES (%s)", $cty_name));
            $cid = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}dashd_countries WHERE name_en = %s", $cty_name));
        }

        if ($iid <= 0 || $cid <= 0) {
            continue;
        }

        if (!isset($record_store_map[$source_key]) && class_exists('DashD_Sync_Source_Record_Store')) {
            $record_store_map[$source_key] = new DashD_Sync_Source_Record_Store($source_key, $sync_date);
        }

        if (isset($record_store_map[$source_key]) && $record_store_map[$source_key] instanceof DashD_Sync_Source_Record_Store) {
            $op = $record_store_map[$source_key]->upsert_record($iid, $cid, $year, $quarter, $val);
            if ($op === 'inserted' || $op === 'updated') {
                $imported_count++;
            }
            continue;
        }

        $exist_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dashd_data_records WHERE source_key=%s AND indicator_id=%d AND country_id=%d AND data_year=%d AND data_quarter=%s",
            $source_key, $iid, $cid, $year, $quarter
        ));

        if ($exist_id > 0) {
            $updated = $wpdb->update(
                "{$wpdb->prefix}dashd_data_records",
                ['val' => $val, 'record_date' => $sync_date],
                ['id' => $exist_id]
            );
            if ($updated !== false) {
                $imported_count++;
            }
        } else {
            $inserted = $wpdb->insert("{$wpdb->prefix}dashd_data_records", [
                'source_key' => $source_key, 'indicator_id' => $iid, 'country_id' => $cid,
                'val' => $val, 'data_year' => $year, 'data_quarter' => $quarter, 'record_date' => $sync_date
            ]);
            if ($inserted !== false) {
                $imported_count++;
            }
        }
    }
    
    fclose($file);
    if (function_exists('dashd_clear_all_caches')) dashd_clear_all_caches();
    wp_redirect(admin_url("admin.php?page=dashd-settings&tab=sources&imported_raw={$imported_count}"));
    exit;
}
