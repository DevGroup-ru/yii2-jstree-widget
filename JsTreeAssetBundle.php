<?php

namespace devgroup\JsTreeWidget;

use Yii;
use yii\web\AssetBundle;

/**
 * Asset bundle for jsTree widget
 *
 * @package devgroup\JsTreeWidget
 */
class JsTreeAssetBundle extends AssetBundle {
    /**
     * @inheritdoc
     */
    public $depends = [
        'yii\web\JqueryAsset',
    ];

    /**
     * @inheritdoc
     */
    public $sourcePath = '@vendor/vakata/jstree/dist';

    /**
     * @inheritdoc
     */
    public $css = [
        'themes/default/style.min.css',
    ];

    public $js = [
        'jstree.min.js',
    ];
} 