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
    public $menuLabelsTranslationCategory = false;

    /**
     * JsExpression for action(callback function) on double click. You can use JsExpression or make custom expression.
     * Warning! Callback function differs from native jsTree function - it consumes only one attribute - node(similar to contextmenu action).
     * Use false if no action needed.
     * @var bool|JsExpression
     */
    public $doubleClickAction = false;

    /** @var bool|array route to change parent action (applicable to Adjacency List only) */
    public $changeParentAction = false;

    /** @var bool|array route to reorder action */
    public $reorderAction = false;

    /** @var bool plugin config option for allow multiple nodes selections or not */
    public $multiSelect = false;

    /** @var string Default labels translation category */
    private $defaultTranslationCategory = 'jstw.defaults';

    /** @var array selected nodes */
    public $selectedNodes = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (false === $this->menuLabelsTranslationCategory) {
            $this->menuLabelsTranslationCategory = $this->defaultTranslationCategory;
        }
        self::registerTranslations();
        parent::init();
    }

    public static function registerTranslations()
    {
        Yii::$app->i18n->translations['jstw*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'basePath' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'messages',
        ];
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        if (!is_array($this->treeDataRoute)) {
            throw new InvalidConfigException("Attribute treeDataRoute is required to use TreeWidget.");
        }

        if (count($this->selectedNodes) > 0) {
            $this->treeDataRoute['selected'] = $this->selectedNodes;
        }

        $options = [
            'plugins' => $this->plugins,
            'core' => [
                'check_callback' => true,
                'multiple' => $this->multiSelect,
                'data' => [
                    'url' => new JsExpression(
                        "function (node) {
                            return " . Json::encode(Url::to($this->treeDataRoute)) . ";
                        }"
                    ),
                    'success' => new JsExpression(
                        "function (node) {
                            return { 'id' : node.id };
                        }"
                    ),
                    'data' => new JsExpression(
                        "function (node) {
                        return { 'id' : node.id };
                        }"
                    ),
                    'error' => new JsExpression(
                        "function ( o, textStatus, errorThrown ) {
                            alert(o.responseText);
                        }"
                    )
                ]
            ]
        ];

        // merge with attribute-provided options
        $options = ArrayHelper::merge($options, $this->options);
        if (false === empty($this->contextMenuItems)) {
            if (!in_array('contextmenu', $this->plugins)) {
                // add missing contextmenu plugin
                $options['plugins'] = ['contextmenu'];
            }
            $functionName = $this->getId() . 'ContextMenu';
            $options['contextmenu'] = ['items' => new JsExpression($functionName)];
            $this->contextMenuOptions($functionName);
        }
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

    /**
     * @param $functionName
     * @return array
     */
    private function contextMenuOptions($functionName)
    {
        $items = [];
        $conditionItems = "";
        foreach ($this->contextMenuItems as $index => $item) {
            $item['label'] = Yii::t($this->menuLabelsTranslationCategory, $item['label']);
            if (false === empty($item['showWhen'])) {
                if (true === is_array($item['showWhen'])) {
                    $condition = [];
                    foreach ($item['showWhen'] as $key => $value) {
                        $key = (false !== strpos($key, 'data-')) ? $key : 'data-' . $key;
                        $condition[] = "node.hasOwnProperty('a_attr') && node.a_attr['$key'] == {$value}";
                    }
                    $condition = implode(' && ', $condition);
                } else {
                    $condition = $item['showWhen'];
                }
                unset($item['showWhen']);
                $item = Json::encode($item);
                $conditionItems .= new JsExpression("
                if ({$condition}) {
                    items.{$index} = $item;
                }
                ");
            } else {
                $items[$index] = $item;
            }
        }
        $items = Json::encode($items);
        $js = <<<JS
            function $functionName(node) {
                var items = $items;
                $conditionItems
                return items;
            }
JS;
        $this->view->registerJs($js, View::POS_HEAD);
    }

    /**
     * Prepares js according to given tree type
     *
     * @return string
     */
    private function prepareJs()
    {
        switch ($this->treeType) {
            case self::TREE_TYPE_ADJACENCY :
                return $this->adjacencyJs();
            case self::TREE_TYPE_NESTED_SET :
                return $this->nestedSetJs();
        }
    }

    /**
     * @return string
     */
    private function adjacencyJs()
    {
        $changeParentJs = '';
        if ($this->changeParentAction !== false) {
            $changeParentUrl = is_array($this->changeParentAction) ? Url::to($this->changeParentAction) : $this->changeParentAction;
            $changeParentJs = <<<JS
             jsTree_{$this->getId()}.on('move_node.jstree', function(e, data) {
                var \$this = $(this);
                $.get('$changeParentUrl', {
                        'id': data.node.id,
                        'parent_id': data.parent
                    }, "json")
                    .done(function (data) {
                        if ('undefined' !== typeof(data.error)) {
                            alert(data.error);
                        }
                        \$this.jstree('refresh');
                    })
                    .fail(function ( o, textStatus, errorThrown ) {
                        alert(o.responseText);
                    });
                return false;
            });
JS;
        }

        $reorderJs = '';
        if ($this->reorderAction !== false) {
            $reorderUrl = is_array($this->reorderAction) ? Url::to($this->reorderAction) : $this->reorderAction;
            $reorderJs = <<<JS
            jsTree_{$this->getId()}.on('move_node.jstree', function(e, data) {
                var params = [];
                var \$this = $(this);
                $('.jstree-node').each(function(i, e) {
                    params[e.id] = i;
                });
                $.post('$reorderUrl', {
                    'order':params,
                    'id': data.node.id
                     },
                      "json")
                    .done(function (data) {
                        if ('undefined' !== typeof(data.error)) {
                            alert(data.error);
                        }
                        \$this.jstree('refresh');
                    })
                    .fail(function ( o, textStatus, errorThrown ) {
                        alert(o.responseText);
                    });
                return false;
            });
JS;
        }
        return $changeParentJs . "\n" . $reorderJs . "\n";
    }

    /**
     * @return string
     */
    private function nestedSetJs()
    {
        $js = "";
        if (false !== $this->reorderAction || false !== $this->changeParentAction) {
            $action = $this->reorderAction ?: $this->changeParentAction;
            $url = is_array($action) ? Url::to($action) : $action;
            $js = <<<JS
                jsTree_{$this->getId()}.on('move_node.jstree', function(e, data) {
                    var \$this = $(this),
                        \$parent = \$this.jstree(true).get_node(data.parent),
                        \$oldParent = \$this.jstree(true).get_node(data.old_parent),
                        siblings = \$parent.children || {};
                    $.post('$url', {
                            'node_id' : data.node.id,
                            'parent': data.parent,
                            'position': data.position,
                            'old_parent': data.old_parent,
                            'old_position': data.old_position,
                            'is_multi': data.is_multi,
                            'siblings': siblings
                         }, "json")
                         .done(function (data) {
                            if ('undefined' !== typeof(data.error)) {
                                alert(data.error);
                            }
                            \$this.jstree('refresh');
                         })
                         .fail(function ( o, textStatus, errorThrown ) {
                            alert(o.responseText);
                         });
                    return false;
                });
JS;
        }
        return $js . "\n";
    }
}
