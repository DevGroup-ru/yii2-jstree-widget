yii2-jstree-widget
==================
[![Code Climate](https://codeclimate.com/github/DevGroup-ru/yii2-jstree-widget/badges/gpa.svg)](https://codeclimate.com/github/DevGroup-ru/yii2-jstree-widget)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/551833bd-1951-493d-9a8f-9f676cf58506/mini.png)](https://insight.sensiolabs.com/projects/551833bd-1951-493d-9a8f-9f676cf58506)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/badges/build.png?b=master)](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/build-status/master)


jsTree tree widget for yii2.

Current state: **unstable**.

Description
-----------

This extension allows you to display and manage hierarchical data structures from your 
database using [jsTree](https://www.jstree.com/).

For now following data structure types are supported:
- [adjacency list](https://en.wikipedia.org/wiki/Adjacency_list);
- [nested set](https://en.wikipedia.org/wiki/Nested_set_model).


Usage example
-------------
For example, we have model Menu that represents our structured data. And MenuController for management purposes.

Adjacency List
--------------
In the MenuController:

``` php
use devgroup\JsTreeWidget\actions\AdjacencyList\FullTreeDataAction;
use devgroup\JsTreeWidget\actions\AdjacencyList\TreeNodesReorderAction;
use devgroup\JsTreeWidget\actions\AdjacencyList\TreeNodeMoveAction;
...
public function actions()
    {
        return [
            'getTree' => [
                'class' => FullTreeDataAction::class,
                'className' => Menu::class,
            ],
            'menuReorder' => [
                'class' => TreeNodesReorderAction::class,
                'className' => Menu::class,
            ],
            'menuChangeParent' => [
                'class' => TreeNodeMoveAction::class,
                'className' => Menu::class,
            ],
        ];
    }
```

In your view file call the widget in the right place:

``` php
    <?= TreeWidget::widget([
            'treeDataRoute' => ['/menu/getTree', 'selected_id' => $parent_id],
            'reorderAction' => ['/menu/menuReorder'],
            'changeParentAction' => ['/menu/menuChangeParent'],
            'treeType' => TreeWidget::TREE_TYPE_ADJACENCY,
            'contextMenuItems' => [
                'open' => [
                    'label' => 'Open',
                    'action' => ContextMenuHelper::actionUrl(
                        ['/menu/list'],
                        ['parent_id']
                    ),
                ],
                'edit' => [
                    'label' => 'Edit',
                    'action' => ContextMenuHelper::actionUrl(
                        ['/menu/edit']
                    ),
                ]
            ],
        ]) ?>
```
Getting Data, Reordering and Change Parent actions has default implementations, but you can implement and use your own ones, just by changing a routes `'treeDataRoute', 'reorderAction', 'changeParentAction'`.

Nested Set
----------
Nested set can work in single or multy root modes. Single root mode by default.
For using multi root mode you have to have `tree` (or other name you like) column in your database table to store root id. And define this name in all necessary config places (see below).

In the MenuController:

``` php
use devgroup\JsTreeWidget\actions\nestedset\FullTreeDataAction;
use devgroup\JsTreeWidget\actions\nestedset\NodeMoveAction;
...
public function actions()
    {
        return [
            'getTree' => [
                'class' => FullTreeDataAction::class,
                'className' => Menu::class,
                'rootAttribute' => 'tree', //omit for single root mode
            ],
            'treeReorder' => [
                'class' => NodeMoveAction::class,
                'className' => Menu::class,
                'rootAttribute' => 'tree', //omit for single root mode
            ],
        ];
    }
```
In the view file:
```php
    <?= TreeWidget::widget([
        'treeDataRoute' => ['/menu/getTree'],
        'reorderAction' => ['/menu/treeReorder'],
        'treeType' => TreeWidget::TREE_TYPE_NESTED_SET, //important config option
        'contextMenuItems' => [
            'edit' => [
                'label' => 'Edit',
                'action' => ContextMenuHelper::actionUrl(
                    ['/menu/edit']
                ),
            ]
        ],
    ]) ?>
```
Getting Data and Node Movements actions has the default implementations and are independent from side `NestedSet behaviors`. But you also can use your own implementation.

`TreeWidget` will register bundle `JsTreeAssetBundle`, but you may want to include it as dependency in your main bundle(ie. for minification purpose).

`ContextMenuHelper` creates `JsExpression` for handling context menu option click. It automatically adds all `data` attributes from item link(`<a>` tag) if it is not specified exactly(as in 'open' menu item).