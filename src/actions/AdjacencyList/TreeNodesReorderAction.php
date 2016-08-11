<?php

namespace devgroup\JsTreeWidget\actions\AdjacencyList;

use DevGroup\TagDependencyHelper\NamingHelper;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\web\BadRequestHttpException;

/**
 * Helper action to change sort_order attribute via JsTree Drag&Drop
 * Example usage in controller:
 * ``` php
 * public function actions()
 * {
 *     return [
 *         'reorder' => [
 *             'class' => TreeNodesReorderAction::class,
 *             'class_name' => Category::class,
 *         ],
 *         ...
 *     ];
 * }
 * ```
 */
class TreeNodesReorderAction extends Action
{
    public $className = null;
    public $modelSortOrderField = 'sort_order';
    public $sortOrder = [];

    public function init()
    {
        if (!isset($this->className)) {
            throw new InvalidConfigException("Model name should be set in controller actions");
        }
        if (!class_exists($this->className)) {
            throw new InvalidConfigException("Model class does not exists");
        }
        $this->sortOrder = Yii::$app->request->post('order');
        if (empty($this->sortOrder)) {
            throw new BadRequestHttpException;
        }
    }

    public function run()
    {
        /** @var ActiveRecord $class */
        $class = $this->className;
        $sortOrderField = Yii::$app->db->quoteColumnName($this->modelSortOrderField);
        $case = 'CASE `id`';
        $newSortOrders = [];
        foreach ($this->sortOrder as $id => $sort_order) {
            if ($sort_order === '' || $sort_order === null) {
                continue;
            }
            $case .= ' when "' . $id . '" then "' . $sort_order . '"';
            $newSortOrders[$id] = $sort_order;
        }
        $case .= ' END';
        $sql = "UPDATE "
            . $class::tableName()
            . " SET " . $sortOrderField . " = "
            . $case
            . " WHERE `id` IN(" . implode(', ', array_keys($newSortOrders))
            . ")";
        TagDependency::invalidate(
            Yii::$app->cache,
            NamingHelper::getCommonTag($class)
        );
        return 0 !== Yii::$app->db->createCommand($sql)->execute();
    }
}
