<?php

namespace devgroup\JsTreeWidget\widgets;

use Yii;
use yii\web\AssetBundle;

class TreeInputAssetBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/tree-input-src/';
        $this->css = [
            'styles.css'
        ];
        parent::init();
    }
}
