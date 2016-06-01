<?php

namespace devgroup\JsTreeWidget\actions\AdjacencyList;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\web\NotFoundHttpException;

/**
 * Helper action to change parent_id attribute via JsTree Drag&Drop
 * Example usage in controller:
 * ``` php
 * public function actions()
 * {
 *     return [
 *         'move' => [
 *             'class' => TreeNodeMoveAction::className(),
 *             'class_name' => Category::className(),
 *         ],
 *         ...
 *     ];
 * }
 * ```
 */

class TreeNodeMoveAction extends Action
{
    public $className = null;
    public $modelParentIdField = 'parent_id';
    public $parentId = null;
    public $saveAttributes = [];

    public function init()
    {
        if (!isset($this->className)) {
            throw new InvalidConfigException("Model name should be set in controller actions");
        }
        if (!class_exists($this->className)) {
            throw new InvalidConfigException("Model class does not exists");
        }
        if (!in_array($this->modelParentIdField, $this->saveAttributes)) {
            $this->saveAttributes[] = $this->modelParentIdField;
        }
    }

    public function run($id = null)
    {
        $this->parentId = Yii::$app->request->get('parent_id');
        $class = $this->className;
        if (null === $id
            || null === $this->parentId
            || (null === $model = $class::findById($id))
            || (null === $parent = $class::findById($this->parentId))) {
            throw new NotFoundHttpException;
        }
        /** @var ActiveRecord $model */
        $model->{$this->modelParentIdField} = $parent->id;
        $model->save(true, $this->saveAttributes);
    }
}
