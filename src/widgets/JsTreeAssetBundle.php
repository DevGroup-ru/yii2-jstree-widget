<?php

namespace devgroup\JsTreeWidget\widgets;

use Yii;
use yii\web\AssetBundle;

/**
 * Asset bundle for jsTree widget
 * Uses bower as jstree source.
 *
 * @package devgroup\JsTreeWidget
 */
class JsTreeAssetBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $depends = [
        'yii\web\JqueryAsset',
    ];
    /**
     * @inheritdoc
     */
    public $sourcePath = '@bower/jstree/dist';

    /**
     * @inheritdoc
     */
    public $css = [
        'themes/default/style.min.css',
    ];

    /**
     * @inheritdoc
     */
    public $js = [
        'jstree.min.js',
    ];
}
