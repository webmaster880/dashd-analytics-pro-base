<?php
/**
 * DashD Analytics Widget element configuration for YOOtheme Pro.
 */
namespace YOOtheme;

if (!defined('ABSPATH')) {
    return;
}

$table_options = [];
if (\function_exists('dashd_integration_get_source_options')) {
    $source_options = \dashd_integration_get_source_options();
    foreach ($source_options as $source_key => $source_label) {
        $key = \function_exists('dashd_normalize_source_key')
            ? \dashd_normalize_source_key((string) $source_key)
            : \sanitize_key((string) $source_key);
        if ($key === '') {
            continue;
        }
        $label = \sanitize_text_field((string) $source_label);
        if ($label === '') {
            $label = $key;
        }
        $table_options[$label] = $key;
    }
}
if (empty($table_options)) {
    $table_options['Default Table (table1)'] = 'table1';
}

$indicator_options = [];
if (\function_exists('dashd_integration_get_indicator_options')) {
    foreach (\dashd_integration_get_indicator_options() as $token => $label) {
        $token = \sanitize_text_field((string) $token);
        $label = \sanitize_text_field((string) $label);
        if ($token === '' || $label === '') {
            continue;
        }
        $indicator_options[$label] = $token;
    }
}
if (empty($indicator_options)) {
    $indicator_options['No indicators available'] = '';
}

$color_presets = [
    'DashD Default' => '#336DFF, #AF9BE2, #3B82F6, #BEE00F, #7FD3F7',
    'EBRD Blue Theme' => '#03045E, #0077B6, #00B4D8, #90E0EF, #CAF0F8',
    'Sunset Fire' => '#FF7B00, #FF9500, #FFAA00, #FFC300, #FFDD00',
    'Eco Greens' => '#2D6A4F, #40916C, #52B788, #74C69D, #95D5B2',
    'Strict Monochrome' => '#212529, #343A40, #495057, #6C757D, #ADB5BD',
];

return [
    'fields' => [
        'indicators' => [
            'label' => 'Indicators (Data Source)',
            'type' => 'select',
            'options' => $indicator_options,
            'attrs' => [
                'multiple' => true,
                'size' => 8,
            ],
        ],
        'table' => [
            'label' => 'Legacy Source (fallback)',
            'type' => 'select',
            'options' => $table_options,
        ],
        'mode' => [
            'label' => 'Mode',
            'type' => 'select',
            'options' => [
                'Bar' => 'bar',
                'Line' => 'line',
                'Donut' => 'donut',
            ],
        ],
        'scale' => [
            'label' => 'Scale',
            'type' => 'select',
            'options' => [
                'Linear' => 'linear',
                'Logarithmic' => 'logarithmic',
            ],
        ],
        'bar_orientation' => [
            'label' => 'Bar Orientation',
            'type' => 'select',
            'options' => [
                'Horizontal' => 'horizontal',
                'Vertical' => 'vertical',
            ],
            'default' => 'horizontal',
        ],
        'bar_stacked' => [
            'label' => 'Bar Type',
            'type' => 'checkbox',
            'text' => 'Stacked',
            'default' => true,
        ],
        'gated' => [
            'label' => 'Gated Content',
            'type' => 'checkbox',
            'text' => 'Require Email',
        ],
        'show_view_toggle' => [
            'label' => 'Show View Switch',
            'type' => 'checkbox',
            'text' => 'Show Bar / Line / Donut',
            'default' => true,
        ],
        'show_scale_toggle' => [
            'label' => 'Show Scale Switch',
            'type' => 'checkbox',
            'text' => 'Show Lin / Log',
            'default' => true,
        ],
        'show_periods' => [
            'label' => 'Show Period Controls',
            'type' => 'checkbox',
            'text' => 'Show Year / Quarter',
            'default' => true,
        ],
        'country_order' => [
            'label' => 'Country Display Order',
            'type' => 'text',
            'description' => 'Optional comma-separated names (e.g. Ukraine, Moldova, Georgia, Armenia).',
            'source' => true,
        ],
        'colors' => [
            'label' => 'Color Palette',
            'type' => 'select',
            'options' => $color_presets,
        ],
        'custom_color_1' => [
            'label' => '#1',
            'type' => 'color',
            'source' => true,
        ],
        'custom_color_2' => [
            'label' => '#2',
            'type' => 'color',
            'source' => true,
        ],
        'custom_color_3' => [
            'label' => '#3',
            'type' => 'color',
            'source' => true,
        ],
        'custom_color_4' => [
            'label' => '#4',
            'type' => 'color',
            'source' => true,
        ],
        'custom_color_5' => [
            'label' => '#5',
            'type' => 'color',
            'source' => true,
        ],
        'colors_custom' => [
            // Legacy fallback for backward compatibility with already saved nodes.
            'type' => 'text',
            'source' => true,
        ],
        'name' => [
            'label' => 'Name',
            'description' => 'Define a name to easily identify this element inside the builder.',
            'source' => true,
        ],
        'status' => [
            'label' => 'Status',
            'description' => 'Disable the element and publish it later.',
            'type' => 'checkbox',
            'text' => 'Disable element',
            'attrs' => [
                'true-value' => 'disabled',
                'false-value' => '',
            ],
        ],
        'source' => [
            'type' => 'fields',
            'fields' => [
                '_source' => [
                    'label' => 'Dynamic Content',
                    'type' => 'source-select',
                    'description' => 'Select a content source to make its fields available for mapping. Choose between sources of the current page or query a custom source.',
                ],
                '_sourceArgs' => [
                    'type' => 'source-query-args',
                ],
                '_sourceField' => [
                    'label' => 'Multiple Items Source',
                    'type' => 'source-field-select',
                    'description' => 'By default, fields of related sources with single items are available for mapping. Select a related source which has multiple items to map its fields.',
                    'show' => 'yootheme.builder.helpers.Source.showMultipleSelectField(this.builder.path(this.node))',
                ],
                '_sourceFieldArgs' => [
                    'type' => 'source-field-args',
                ],
                '_sourceFieldDirectives' => [
                    'type' => 'source-field-directives',
                ],
                '_sourceCondition' => [
                    'type' => 'fields',
                    'fields' => [
                        '_sourceConditionProp' => [
                            'label' => 'Display Condition',
                            'prop' => '_condition',
                            'type' => 'source-prop-select',
                            'description' => 'Show or hide the item depending on the content of a field.',
                            'options' => [
                                [
                                    'evaluate' => 'yootheme.builder.helpers.Source.getSourceField(this.builder.path(this.node)).type.kind === \'LIST\' ? yootheme.builder.sources.conditionMultipleOptions : []',
                                ],
                            ],
                        ],
                        '_sourceConditionArgs' => [
                            'type' => 'source-prop-filters',
                            'prop' => '_condition',
                            'fields' => [
                                '_grid' => [
                                    'type' => 'grid',
                                    'width' => '1-2',
                                    'fields' => [
                                        'condition' => [
                                            'label' => 'Condition',
                                            'type' => 'select',
                                            'default' => '!!',
                                            'options' => [
                                                'Is empty' => '!',
                                                'Is not empty' => '!!',
                                                'Is equal to' => '=',
                                                'Is not equal to' => '!=',
                                                'Contains' => '~=',
                                                'Does not contain' => '!~=',
                                                'Less than' => '<',
                                                'Greater than' => '>',
                                                'Starts with' => '^=',
                                                'Does not start with' => '!^=',
                                                'Ends with' => '$=',
                                                'Does not end with' => '!$=',
                                                'Matches a RegExp' => 'regex',
                                            ],
                                            'enable' => '!show_empty',
                                        ],
                                        'condition_value' => [
                                            'label' => 'Value',
                                            'enable' => '!show_empty && $match(condition, \'=|<|>|regex\')',
                                        ],
                                    ],
                                ],
                                'show_empty' => [
                                    'type' => 'checkbox',
                                    'text' => 'Show element only if dynamic content is empty',
                                ],
                            ],
                            'show' => 'yootheme.builder.helpers.Source.getProp(this.node, \'_condition\').name',
                        ],
                    ],
                    'show' => 'yootheme.builder.helpers.Source.getSourceField(this.builder.path(this.node))',
                ],
            ],
        ],
        'id' => [
            'label' => 'ID',
            'description' => 'Define a unique identifier for the element.',
            'source' => true,
        ],
        'class' => [
            'label' => 'Classes',
            'description' => 'Define one or more class names for the element. Separate multiple classes with spaces.',
            'source' => true,
        ],
        'attributes' => [
            'label' => 'Attributes',
            'description' => 'Define one or more attributes for the element. Separate attribute name and value by <code>=</code> character. One attribute per line.',
            'type' => 'editor',
            'editor' => 'code',
            'attrs' => [
                'debounce' => 500,
            ],
            'source' => true,
        ],
        'css' => [
            'label' => 'CSS',
            'description' => 'Enter your own custom CSS. The following selectors will be prefixed automatically for this element: <code>.el-element</code>',
            'type' => 'editor',
            'editor' => 'code',
            'mode' => 'css',
            'attrs' => [
                'debounce' => 500,
                'hints' => ['.el-element'],
            ],
            'source' => true,
        ],
        'transform' => [
            'label' => 'Transform',
            'description' => 'Transform the element into another element while keeping its content and settings. Unused content and settings are removed. Transforming into a preset only keeps the content but adopts all preset settings.',
            'type' => 'button',
            'text' => 'Select Element',
            'event' => 'transformBuilderElement',
        ],
    ],
    'fieldset' => [
        'default' => [
            'type' => 'tabs',
            'fields' => [
                [
                    'title' => 'Settings',
                    'fields' => [
                        'indicators',
                        'mode',
                        'scale',
                        'bar_orientation',
                        'bar_stacked',
                        'gated',
                        'show_view_toggle',
                        'show_scale_toggle',
                        'show_periods',
                        'country_order',
                        'colors',
                        [
                            'label' => 'Custom Color Palette',
                            'name' => '_custom_palette_grid',
                            'description' => 'Optional. Pick one or more custom colors. If at least one color is set, it overrides the preset palette.',
                            'type' => 'grid',
                            'width' => '1-5',
                            'fields' => ['custom_color_1', 'custom_color_2', 'custom_color_3', 'custom_color_4', 'custom_color_5'],
                        ],
                    ],
                ],
                [
                    'title' => 'Advanced',
                    'fields' => ['name', 'status', 'source', 'id', 'class', 'attributes', 'css', 'transform'],
                ],
            ],
        ],
    ],
];
