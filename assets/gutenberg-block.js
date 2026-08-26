/**
 * Gutenberg Block UI for Analytics Pro
 */
(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, TextControl, ToggleControl } = wp.components;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('dashd/analytics-widget', {
        title: 'Analytics Pro Dashboard',
        icon: 'chart-bar',
        category: 'widgets',
        edit: function(props) {
            const { attributes, setAttributes } = props;

            const sourceOptions = dashdBlocksInfo.sources.map(s => ({
                label: `${s.source_label} (${s.source_key})`,
                value: s.source_key
            }));
            const indicatorOptions = Array.isArray(dashdBlocksInfo.indicators) ? dashdBlocksInfo.indicators : [];
            const periodOptions = Array.isArray(dashdBlocksInfo.periods) ? dashdBlocksInfo.periods : [];
            const periodSelectOptions = [{ label: 'All', value: '' }].concat(periodOptions);

            if (!attributes.table && sourceOptions.length > 0) {
                setAttributes({ table: sourceOptions[0].value });
            }
            if ((!Array.isArray(attributes.indicators) || attributes.indicators.length === 0) && indicatorOptions.length > 0) {
                setAttributes({ indicators: [indicatorOptions[0].value] });
            }

            return el('div', null,
                el(InspectorControls, null,
                    el(PanelBody, { title: 'Dashboard Settings', initialOpen: true },
                        el(SelectControl, {
                            label: 'Indicators (Data Source)',
                            multiple: true,
                            value: Array.isArray(attributes.indicators) ? attributes.indicators : [],
                            options: indicatorOptions,
                            help: 'Select one or more indicators for this chart.',
                            onChange: (val) => {
                                const next = Array.isArray(val) ? val : (val ? [val] : []);
                                setAttributes({ indicators: next });
                            }
                        }),
                        el(SelectControl, {
                            label: 'Default Mode',
                            value: attributes.mode,
                            options: [
                                { label: 'Bar', value: 'bar' },
                                { label: 'Line', value: 'line' },
                                { label: 'Donut', value: 'donut' }
                            ],
                            onChange: (val) => setAttributes({ mode: val })
                        }),
                        el(SelectControl, {
                            label: 'Scale',
                            value: attributes.scale,
                            options: [
                                { label: 'Linear', value: 'linear' },
                                { label: 'Logarithmic', value: 'logarithmic' }
                            ],
                            onChange: (val) => setAttributes({ scale: val })
                        }),
                        el(SelectControl, {
                            label: 'Bar Orientation',
                            value: attributes.bar_orientation || 'horizontal',
                            options: [
                                { label: 'Horizontal', value: 'horizontal' },
                                { label: 'Vertical', value: 'vertical' }
                            ],
                            onChange: (val) => setAttributes({ bar_orientation: val })
                        }),
                        el(SelectControl, {
                            label: 'Bar Type',
                            value: attributes.bar_stacked || 'true',
                            options: [
                                { label: 'Stacked', value: 'true' },
                                { label: 'Normal', value: 'false' }
                            ],
                            onChange: (val) => setAttributes({ bar_stacked: val })
                        }),
                        el(SelectControl, {
                            label: 'Period Start',
                            value: attributes.period_start || '',
                            options: periodSelectOptions,
                            help: 'Optional lower bound for chart periods.',
                            onChange: (val) => setAttributes({ period_start: val })
                        }),
                        el(SelectControl, {
                            label: 'Period End',
                            value: attributes.period_end || '',
                            options: periodSelectOptions,
                            help: 'Optional upper bound for chart periods.',
                            onChange: (val) => setAttributes({ period_end: val })
                        }),
                        el(ToggleControl, {
                            label: 'Gated Content (Require Email)',
                            checked: attributes.gated === 'true',
                            onChange: (val) => setAttributes({ gated: val ? 'true' : 'false' })
                        }),
                        el(ToggleControl, {
                            label: 'Show Bar/Line/Donut Switch',
                            checked: attributes.show_view_toggle !== 'false',
                            onChange: (val) => setAttributes({ show_view_toggle: val ? 'true' : 'false' })
                        }),
                        el(ToggleControl, {
                            label: 'Show Lin/Log Switch',
                            checked: attributes.show_scale_toggle !== 'false',
                            onChange: (val) => setAttributes({ show_scale_toggle: val ? 'true' : 'false' })
                        }),
                        el(ToggleControl, {
                            label: 'Show Year/Quarter Controls',
                            checked: attributes.show_periods !== 'false',
                            onChange: (val) => setAttributes({ show_periods: val ? 'true' : 'false' })
                        }),
                        el(ToggleControl, {
                            label: 'Show Data Quality Warnings',
                            checked: attributes.show_data_warnings !== 'false',
                            help: 'Show warnings for negative or incorrect values.',
                            onChange: (val) => setAttributes({ show_data_warnings: val ? 'true' : 'false' })
                        }),
                        el(TextControl, {
                            label: 'Country Display Order',
                            value: attributes.country_order || '',
                            help: 'Optional comma-separated names (e.g. Ukraine, Moldova, Georgia, Armenia).',
                            onChange: (val) => setAttributes({ country_order: val })
                        }),
                        el(TextControl, {
                            label: 'Colors (comma separated HEX)',
                            value: attributes.colors,
                            onChange: (val) => setAttributes({ colors: val })
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'dashd/analytics-widget',
                    attributes: attributes
                })
            );
        },
        save: function() { return null; }
    });
})(window.wp);
