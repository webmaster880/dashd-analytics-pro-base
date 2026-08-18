<?php
/**
 * Admin Constructor Module v9.3.0
 * ДОБАВЛЕНО: Интернационализация (i18n) строк.
 */

if (!defined('ABSPATH')) exit;

function dashd_admin_constructor_page() {
    // Подключаем локальный Chart.js для превью в конструкторе
    if (!wp_script_is('dashd-chart-js', 'enqueued')) {
        wp_enqueue_script('dashd-chart-js');
    }

    $indicator_options = function_exists('dashd_integration_get_indicator_options')
        ? dashd_integration_get_indicator_options()
        : [];
    
    $palettes = [
        'blue'    => ['label' => __('Professional Blue', 'dashd-analytics-pro'), 'colors' => ['#1e87f0','#3e95cd','#7ebae6','#a5d2f3','#58595B']],
        'emerald' => ['label' => __('Emerald Nature', 'dashd-analytics-pro'),    'colors' => ['#10b981','#34d399','#6ee7b7','#a7f3d0','#064e3b']],
        'sunset'  => ['label' => __('Sunset Warmth', 'dashd-analytics-pro'),     'colors' => ['#f59e0b','#fbbf24','#fcd34d','#fde68a','#78350f']],
        'vibrant' => ['label' => __('Vibrant Mix', 'dashd-analytics-pro'),       'colors' => ['#ec4899','#8b5cf6','#3b82f6','#10b981','#f59e0b']],
        'dashd_default' => ['label' => __('DashD Default', 'dashd-analytics-pro'),'colors' => ['#336DFF','#AF9BE2','#3B82F6','#BEE00F','#7FD3F7']]
    ];
    $default_palette = ['#336DFF','#AF9BE2','#3B82F6','#BEE00F','#7FD3F7'];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Widget Constructor', 'dashd-analytics-pro'); ?> <span class="dashd-badge">v<?php echo esc_html((string) DASHD_VERSION); ?></span></h1>
        
        <div id="dashd-constructor" style="display:flex; gap:25px; margin-top:20px; align-items:flex-start;">
            <div class="con-sidebar" style="flex:0 0 380px; background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px;">
                <h3 style="margin-top:0;"><?php esc_html_e('Widget Settings', 'dashd-analytics-pro'); ?></h3>
                
                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Indicators (Data Source):', 'dashd-analytics-pro'); ?></label>
                    <select id="c_indicators" class="uk-select" style="width: 100%; min-height: 190px;" multiple size="8">
                        <?php if (!empty($indicator_options)): ?>
                            <?php $opt_index = 0; foreach ($indicator_options as $indicator_token => $indicator_label): ?>
                                <option value="<?php echo esc_attr((string) $indicator_token); ?>" <?php selected($opt_index < 3, true); ?>>
                                    <?php echo esc_html((string) $indicator_label); ?>
                                </option>
                            <?php $opt_index++; endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled><?php esc_html_e('No indicators available yet', 'dashd-analytics-pro'); ?></option>
                        <?php endif; ?>
                    </select>
                    <p style="font-size: 11px; color: #646970; margin-top: 5px;">
                        <?php esc_html_e('Hold Cmd/Ctrl to select multiple indicators for one chart.', 'dashd-analytics-pro'); ?>
                    </p>
                </div>

                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Default Mode & Scale:', 'dashd-analytics-pro'); ?></label>
                    <div style="display:flex; gap:10px;">
                        <select id="c_mode" class="uk-select" style="flex:1;">
                            <option value="bar"><?php esc_html_e('Bar', 'dashd-analytics-pro'); ?></option>
                            <option value="line"><?php esc_html_e('Line', 'dashd-analytics-pro'); ?></option>
                            <option value="donut"><?php esc_html_e('Donut', 'dashd-analytics-pro'); ?></option>
                        </select>
                        <select id="c_scale" class="uk-select" style="flex:1;">
                            <option value="linear"><?php esc_html_e('Linear', 'dashd-analytics-pro'); ?></option>
                            <option value="logarithmic"><?php esc_html_e('Log', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <select id="c_bar_orientation" class="uk-select" style="flex:1;">
                            <option value="horizontal"><?php esc_html_e('Bar: Horizontal', 'dashd-analytics-pro'); ?></option>
                            <option value="vertical"><?php esc_html_e('Bar: Vertical', 'dashd-analytics-pro'); ?></option>
                        </select>
                        <select id="c_bar_stacked" class="uk-select" style="flex:1;">
                            <option value="true"><?php esc_html_e('Bar Type: Stacked', 'dashd-analytics-pro'); ?></option>
                            <option value="false"><?php esc_html_e('Bar Type: Normal', 'dashd-analytics-pro'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Export Protection (Gated Content):', 'dashd-analytics-pro'); ?></label>
                    <select id="c_gated" class="uk-select" style="width: 100%;">
                        <option value="false"><?php esc_html_e('Disabled (Free Download)', 'dashd-analytics-pro'); ?></option>
                        <option value="true"><?php esc_html_e('Enabled (Require Email)', 'dashd-analytics-pro'); ?></option>
                    </select>
                    <p style="font-size: 11px; color: #646970; margin-top: 5px;"><?php esc_html_e('If enabled, users will be prompted to enter their email before downloading CSV or PDF reports.', 'dashd-analytics-pro'); ?></p>
                </div>

                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Country Display Order (optional):', 'dashd-analytics-pro'); ?></label>
                    <input id="c_country_order" class="uk-input" type="text" value="Ukraine, Moldova, Georgia, Armenia">
                    <p style="font-size: 11px; color: #646970; margin-top: 5px;">
                        <?php esc_html_e('Comma-separated country names. Example: Ukraine, Moldova, Georgia, Armenia. Unlisted countries will be shown after listed ones.', 'dashd-analytics-pro'); ?>
                    </p>
                </div>

                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Controls Visibility:', 'dashd-analytics-pro'); ?></label>
                    <label style="display:block; margin-bottom:6px;">
                        <input type="checkbox" id="c_show_view_toggle" checked>
                        <?php esc_html_e('Show Bar / Line / Donut switch', 'dashd-analytics-pro'); ?>
                    </label>
                    <label style="display:block; margin-bottom:6px;">
                        <input type="checkbox" id="c_show_scale_toggle" checked>
                        <?php esc_html_e('Show Lin / Log switch', 'dashd-analytics-pro'); ?>
                    </label>
                    <label style="display:block; margin-bottom:0;">
                        <input type="checkbox" id="c_show_periods" checked>
                        <?php esc_html_e('Show Year / Quarter controls', 'dashd-analytics-pro'); ?>
                    </label>
                    <p style="font-size: 11px; color: #646970; margin-top: 5px;"><?php esc_html_e('When hidden, selected defaults still work in background.', 'dashd-analytics-pro'); ?></p>
                </div>

                <div class="uk-margin" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Data Quality Warnings:', 'dashd-analytics-pro'); ?></label>
                    <label style="display:block; margin-bottom:0;">
                        <input type="checkbox" id="c_show_data_warnings" checked>
                        <?php esc_html_e('Show warnings for negative or incorrect values', 'dashd-analytics-pro'); ?>
                    </label>
                    <p style="font-size: 11px; color: #646970; margin-top: 5px;">
                        <?php esc_html_e('When disabled, the chart keeps rendering selected data without warning badges, warning borders, or fallback warning messages.', 'dashd-analytics-pro'); ?>
                    </p>
                </div>

                <div class="uk-margin" style="margin-top:20px; padding:15px; background:#f6f7f7; border-radius:8px; border:1px solid #e5e5e5;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e('Custom Color Palette:', 'dashd-analytics-pro'); ?></label>
                    
                    <select id="c_presets" class="uk-select" style="width: 100%; margin-bottom: 10px;">
                        <option value="">-- <?php esc_html_e('Apply Preset', 'dashd-analytics-pro'); ?> --</option>
                        <?php foreach($palettes as $p): ?>
                            <option value="<?php echo esc_attr(implode(',', (array) ($p['colors'] ?? []))); ?>"><?php echo esc_html((string) ($p['label'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div id="color_pickers" style="display:flex; justify-content:space-between; gap:5px; margin-top:10px;">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <div style="text-align:center;">
                                <input type="color" id="clr_<?php echo (int) $i; ?>" class="color-dot" value="<?php echo esc_attr($default_palette[$i - 1] ?? '#336DFF'); ?>" style="width:45px; height:45px; border:none; cursor:pointer; background:none;">
                                <div style="font-size:9px; color:#646970; margin-top:4px;">#<?php echo (int) $i; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="background:#f0f6fb; padding:15px; border-radius:6px; border:1px solid #d1e3ef; margin-top: 20px;">
                    <label style="font-weight: 600;"><?php esc_html_e('Shortcode:', 'dashd-analytics-pro'); ?></label>
                    <textarea id="res_sc" readonly style="width:100%; height:70px; font-family:monospace; font-size:11px; margin:10px 0; padding:8px; resize:none; border: 1px solid #8c8f94; border-radius: 4px;"></textarea>
                    <button class="button button-primary" onclick="copyAndRefresh()" style="width:100%; justify-content:center; height:40px; display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh & Preview', 'dashd-analytics-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="prev_area" style="flex:1; border:1px dashed #c3c4c7; background:#fff; border-radius:8px; min-height:550px; padding: 30px; box-sizing: border-box;">
                <div id="prev_content"></div>
            </div>
        </div>
    </div>

    <script>
        const i18n_cons = {
            loading: "<?php echo esc_js(__('Loading preview...', 'dashd-analytics-pro')); ?>"
        };
        const previewNonce = "<?php echo esc_js(wp_create_nonce('dashd_render_preview')); ?>";

        function getColorsString() {
            let clrs = [];
            for(let i=1; i<=5; i++) { clrs.push(document.getElementById('clr_'+i).value); }
            return clrs.join(',');
        }

        function applyPreset(csv) {
            if(!csv) return;
            const colors = csv.split(',');
            colors.forEach((c, idx) => {
                const el = document.getElementById('clr_' + (idx + 1));
                if(el) el.value = c;
            });
            upSC();
        }

        function getIndicatorsString() {
            const el = document.getElementById('c_indicators');
            if (!el) return '';
            return Array.from(el.selectedOptions || [])
                .map((opt) => String(opt.value || '').trim())
                .filter(Boolean)
                .join(',');
        }

        function upSC() { 
            const indicators = getIndicatorsString();
            const mode = document.getElementById('c_mode').value;
            const scale = document.getElementById('c_scale').value;
            const gated = document.getElementById('c_gated').value;
            const colors = getColorsString();
            const showViewToggle = document.getElementById('c_show_view_toggle').checked ? 'true' : 'false';
            const showScaleToggle = document.getElementById('c_show_scale_toggle').checked ? 'true' : 'false';
            const showPeriods = document.getElementById('c_show_periods').checked ? 'true' : 'false';
            const showDataWarnings = document.getElementById('c_show_data_warnings').checked ? 'true' : 'false';
            const barOrientation = document.getElementById('c_bar_orientation').value;
            const barStacked = document.getElementById('c_bar_stacked').value;
            const countryOrder = String(document.getElementById('c_country_order').value || '').trim().replace(/"/g, "'");
            
            let shortcode = `[dashd_widget indicators="${indicators}" mode="${mode}" scale="${scale}" colors="${colors}"`;
            if (gated === 'true') { shortcode += ` gated="true"`; }
            shortcode += ` show_view_toggle="${showViewToggle}"`;
            shortcode += ` show_scale_toggle="${showScaleToggle}"`;
            shortcode += ` show_periods="${showPeriods}"`;
            shortcode += ` show_data_warnings="${showDataWarnings}"`;
            shortcode += ` bar_orientation="${barOrientation}"`;
            shortcode += ` bar_stacked="${barStacked}"`;
            if (countryOrder !== '') {
                shortcode += ` country_order="${countryOrder}"`;
            }
            shortcode += `]`;
            
            document.getElementById('res_sc').value = shortcode; 
        }

        async function loadPrev() {
            upSC();
            const content = document.getElementById('prev_content');
            content.innerHTML = `<p style="text-align:center; padding-top:100px; color:#646970;">${i18n_cons.loading}</p>`;
            
            const fd = new FormData();
            fd.append('action', 'dashd_render_preview');
            fd.append('nonce', previewNonce);
            fd.append('shortcode', document.getElementById('res_sc').value);
            
            try {
                const res = await fetch(ajaxurl, { method: 'POST', body: fd });
                content.innerHTML = await res.text();

                const dynamicScripts = content.querySelectorAll('script[data-dashd-widget-boot="1"]');
                for (let i = 0; i < dynamicScripts.length; i++) {
                    const newScript = document.createElement('script');
                    newScript.textContent = dynamicScripts[i].textContent;
                    document.head.appendChild(newScript);
                    document.head.removeChild(newScript);
                }
                
                setTimeout(() => {
                    const widget = document.querySelector('#prev_content .dashd-widget-container');
                    if (widget) widget.dispatchEvent(new CustomEvent('dashd-reinit'));
                }, 300);
            } catch (e) { console.error(e); }
        }

        function copyAndRefresh() {
            const copyText = document.getElementById('res_sc');
            copyText.select();
            document.execCommand('copy');
            loadPrev();
        }

        document.getElementById('c_presets').onchange = (e) => applyPreset(e.target.value);
        ['c_indicators', 'c_mode', 'c_scale', 'c_gated', 'c_show_view_toggle', 'c_show_scale_toggle', 'c_show_periods', 'c_show_data_warnings', 'c_bar_orientation', 'c_bar_stacked', 'c_country_order'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.onchange = upSC;
        });
        for(let i=1; i<=5; i++) { document.getElementById('clr_'+i).oninput = upSC; }

        window.addEventListener('load', upSC);
    </script>
    <style>
        .color-dot::-webkit-color-swatch-wrapper { padding: 0; }
        .color-dot::-webkit-color-swatch { border: 2px solid #cbd5e1; border-radius: 50%; }
    </style>
    <?php
}
