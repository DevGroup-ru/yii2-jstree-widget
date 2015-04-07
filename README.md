yii2-jstree-widget
==================
[![Code Climate](https://codeclimate.com/github/DevGroup-ru/yii2-jstree-widget/badges/gpa.svg)](https://codeclimate.com/github/DevGroup-ru/yii2-jstree-widget)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/551833bd-1951-493d-9a8f-9f676cf58506/mini.png)](https://insight.sensiolabs.com/projects/551833bd-1951-493d-9a8f-9f676cf58506)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/badges/build.png?b=master)](https://scrutinizer-ci.com/g/DevGroup-ru/yii2-jstree-widget/build-status/master)


jsTree tree widget for yii2.

Current state: **unstable**.

Created for use in [DotPlant2 E-Commerce CMS](http://dotplant.ru/).



Usage example
-------------

_Note:_ This package uses `devgroup\JsTreeWidget` namespace.

In your controller(assuming it's `backend/category`) add special action for retrieving Adjacency Tree:

``` php
public function actions()
{
    return [
        'getTree' => [
            'class' => AdjacencyFullTreeDataAction::className(),
            'class_name' => Category::className(),
            'model_label_attribute' => 'name',

        ],
    ];
}
```

In your view file call the widget in the right place:

``` php
    <?=
        TreeWidget::widget([
                'treeDataRoute' => ['/backend/category/getTree', 'selected_id' => $parent_id],
                'contextMenuItems' => [
                    'open' => [
                        'label' => 'Open',
                        'action' => ContextMenuHelper::actionUrl(
                            ['/backend/category/index'],
                            [
                                'parent_id',
                            ]
                        ),
                    ],
                    'edit' => [
                        'label' => 'Edit',
                        'action' => ContextMenuHelper::actionUrl(
                            ['/backend/category/edit']
                        ),
                    ]
                ],
            ]);
    ?>

```

`TreeWidget` will register bundle `JsTreeAssetBundle`, but you may want to include it as dependency in your main bundle(ie. for minification purpose).

`ContextMenuHelper` creates `JsExpression` for handling context menu option click. It automatically adds all `data` attributes from item link(`<a>` tag) if it is not specified exactly(as in 'open' menu item).