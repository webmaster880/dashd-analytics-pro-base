<?php
/**
 * YOOtheme Pro Module Definition
 */
if (!defined('ABSPATH')) exit;

use YOOtheme\Builder;
use YOOtheme\Path;

return [
    'extend' => [
        Builder::class => static function (Builder $builder) {
            // Register DashD custom element type path (YOOtheme Pro modules convention).
            $rawPath = __DIR__ . '/dashd_widget/element.json';
            $typePath = class_exists(Path::class) ? Path::get($rawPath) : $rawPath;
            if (method_exists($builder, 'addTypePath') && is_string($typePath) && is_readable($typePath)) {
                $builder->addTypePath($typePath);
            }
        }
    ],
];
