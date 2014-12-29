<?php

namespace devgroup\JsTreeWidget;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\web\View;

class TreeWidget extends Widget {

    /**
     * @var array Enabled jsTree plugins
     * @see http://www.jstree.com/plugins/
     */
    public $plugins = [
        'wholerow',
        'contextmenu',
        'dnd',
        'types',
        'state',
    ];

    /**
     * @var array Configuration for types plugin
     * @see http://www.jstree.com/api/#/?f=$.jstree.defaults.types
     */
    public $types = [
        'show' => [
            'icon' => 'fa fa-file-o',
        ],
        'list' => [
            'icon' => 'fa fa-list',
        ],
    ];

    /**
     * Context menu actions configuration.
     * @var array
     */
    public $contextMenuItems = [];

    /**
     * Various options for jsTree plugin. Will be merged with default options.
     * @var array
     */
    public $options = [];

    /**
     * Route to action which returns json data for tree
     * @var array
     */
    public $treeDataRoute = null;

    /**
     * Translation category for Yii::t() which will be applied to labels.
     * If translation is not needed - use false.
     */
    public $menuLabelsTranslationCategory = 'app';

    /**
     * JsExpression for action(callback function) on double click. You can use JsExpression or make custom expression.
     * Warning! Callback function differs from native jsTree function - it consumes only one attribute - node(similar to contextmenu action).
     * Use false if no action needed.
     * @var bool|JsExpression
     */
    public $doubleClickAction = false;

    public function run()
    {
        if (!is_array($this->treeDataRoute)) {
            throw new InvalidConfigException("Attribute treeDataRoute is required to use TreeWidget.");
        }

        $id = $this->getId();

        $options = [
            'plugins' => $this->plugins,
            'core' => [
                'check_callback' => true,
                'data' => [
                    'url' => new JsExpression(
                        "function (node) {
                            return " . Json::encode(Url::to($this->treeDataRoute)) . ";
                        }"
                    ),
                    'data' => new JsExpression(
                        "function (node) {
                        return { 'id' : node.id };
                        }"
                    ),
                ]
            ]
        ];

        // merge with contextmenu configuration
        $options = ArrayHelper::merge($options, $this->contextMenuOptions());

        // merge with attribute-provided options
        $options = ArrayHelper::merge($options, $this->options);

        $options = Json::encode($options);

        $this->getView()->registerAssetBundle('devgroup\JsTreeWidget\JsTreeAssetBundle');

        $doubleClick = '';
        if ($this->doubleClickAction !== false) {
            $doubleClick = "
            jsTree_$id.on('dblclick.jstree', function (e) {
                var node = $(e.target).closest('.jstree-node').children('.jstree-anchor');
                var callback = " . $this->doubleClickAction . ";
                callback(node);
                return false;
            });\n";
        }

        $this->getView()->registerJs("
        var jsTree_$id = \$('#$id').jstree($options);
        $doubleClick", View::POS_READY);
        return Html::tag('div', '', ['id' => $id]);
    }

    private function contextMenuOptions()
    {
        $options = [];
        if (count($this->contextMenuItems) > 0) {
            if (!in_array('contextmenu', $this->plugins)) {
                // add missing contextmenu plugin
                $options['plugins'] = ['contextmenu'];
            }

            $options['contextmenu']['items'] = [];
            foreach ($this->contextMenuItems as $index => $item) {
                if ($this->menuLabelsTranslationCategory !== false) {
                    $item['label'] = Yii::t($this->menuLabelsTranslationCategory, $item['label']);
                }
                $options['contextmenu']['items'][$index] = $item;
            }
        }
        return $options;
    }
} 