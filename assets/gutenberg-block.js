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

            if (!attributes.table && sourceOptions.length > 0) {
                setAttributes({ table: sourceOptions[0].value });
            }

            return el('div', null,
                el(InspectorControls, null,
                    el(PanelBody, { title: 'Dashboard Settings', initialOpen: true },
                        el(SelectControl, {
                            label: 'Data Source',
                            value: attributes.table,
                            options: sourceOptions,
                            onChange: (val) => setAttributes({ table: val })
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
                        el(ToggleControl, {
                            label: 'Gated Content (Require Email)',
                            checked: attributes.gated === 'true',
                            onChange: (val) => setAttributes({ gated: val ? 'true' : 'false' })
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