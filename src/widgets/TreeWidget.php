<?php

namespace devgroup\JsTreeWidget\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\web\View;

/**
 * JsTree widget for Yii Framework 2
 */
class TreeWidget extends Widget
{

    const TREE_TYPE_ADJACENCY = 'adjacency';
    const TREE_TYPE_NESTED_SET = 'nested-set';

    public $treeType = self::TREE_TYPE_ADJACENCY;
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

    public $changeParentAction = false;

    public $reorderAction = false;

    public function run()
    {
        if (!is_array($this->treeDataRoute)) {
            throw new InvalidConfigException("Attribute treeDataRoute is required to use TreeWidget.");
        }

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

        $this->getView()->registerAssetBundle('devgroup\JsTreeWidget\widgets\JsTreeAssetBundle');

        $doubleClick = '';
        if ($this->doubleClickAction !== false) {
            $doubleClick = "
            jsTree_{$this->getId()}.on('dblclick.jstree', function (e) {
                var node = $(e.target).closest('.jstree-node').children('.jstree-anchor');
                var callback = " . $this->doubleClickAction . ";
                callback(node);
                return false;
            });\n";
        }
        $treeJs = $this->prepareJs();
        $this->getView()->registerJs("
        var jsTree_{$this->getId()} = \$('#{$this->getId()}').jstree($options);
        $doubleClick $treeJs", View::POS_READY);
        return Html::tag('div', '', ['id' => $this->getId()]);
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

    private function prepareJs()
    {
        switch ($this->treeType) {
            case self::TREE_TYPE_ADJACENCY :
                return $this->adjacecncyJs();
            case self::TREE_TYPE_NESTED_SET :
                return $this->nestedSetJs();
        }
    }

    private function adjacecncyJs()
    {
        $changeParent = '';
        if ($this->changeParentAction !== false) {
            $changeParentUrl = is_array($this->changeParentAction) ? Url::to($this->changeParentAction) : $this->changeParentAction;
            $changeParent = "
             jsTree_{$this->getId()}.bind('move_node.jstree', function(e, data) {
                jQuery.get(
                    '" . $changeParentUrl . "',
                    {
                        'id': data.node.id,
                        'parent_id': data.parent
                    }
                );
                return false;
            });\n";
        }
        $reorder = '';
        if ($this->reorderAction !== false) {
            $reorderUrl = is_array($this->reorderAction) ? Url::to($this->reorderAction) : $this->reorderAction;
            $reorder = "
             jsTree_{$this->getId()}.bind('move_node.jstree', function(e, data) {
             var params = [];
                 jQuery('.jstree-node').each(function(i, e) {
                    params[e.id] = i;
                 });
                  jQuery.post(
                    '" . $reorderUrl . "',
                    {'order':params }
                );
                return false;
            });\n";
        }
        return $changeParent . $reorder;
    }

    private function nestedSetJs()
    {
        $js = "";
        if (false !== $this->reorderAction || false !== $this->changeParentAction) {
            $action = $this->reorderAction ?: $this->changeParentAction;
            $url = is_array($action) ? Url::to($action) : $action;
            $js = new JsExpression(
                "jsTree_{$this->getId()}.on('move_node.jstree', function(e, data) {
                    \$parent = $(this).jstree(true).get_node(data.parent);
                    siblings = \$parent.children || {};
                    $.post(
                        '{$url}',
                         {
                            'node_id' : data.node.id,
                            'parent': data.parent,
                            'position': data.position,
                            'old_parent': data.old_parent,
                            'old_position': data.old_position,
                            'is_multi': data.is_multi,
                            'siblings': siblings
                         }
                    );
                    return false;
                });\n"
            );
        }
        return $js;
    }
}
