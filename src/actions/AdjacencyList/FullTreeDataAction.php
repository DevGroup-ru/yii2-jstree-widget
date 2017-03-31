<?php

namespace devgroup\JsTreeWidget\actions\AdjacencyList;

use DevGroup\TagDependencyHelper\NamingHelper;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\Response;

/**
 * Helper action for retrieving tree data for jstree by ajax.
 * Example use in controller:
 *
 * ``` php
 * public function actions()
 * {
 *     return [
 *         'getTree' => [
 *             'class' => AdjacencyFullTreeDataAction::class,
 *             'className' => Category::class,
 *             'modelLabelAttribute' => 'defaultTranslation.name',
 *
 *         ],
 *     ...
 *     ];
 * }
 * ```
 */
class FullTreeDataAction extends Action
{

    public $className = null;

    public $modelIdAttribute = 'id';

    public $modelLabelAttribute = 'name';

    public $modelParentAttribute = 'parent_id';

    public $varyByTypeAttribute = null;

    public $queryParentAttribute = 'id';

    public $querySortOrder = 'sort_order';

    public $querySelectedAttribute = 'selected_id';
    /**
     * Additional conditions for retrieving tree(ie. don't display nodes marked as deleted)
     * @var array|\Closure
     */
    public $whereCondition = [];

    /**
     * Cache key prefix. Should be unique if you have multiple actions with different $whereCondition
     * @var string|\Closure
     */
    public $cacheKey = 'FullTree';

    /**
     * Cache lifetime for the full tree
     * @var int
     */
    public $cacheLifeTime = 86400;

    /**
     * @var bool|int If int - means cache life time for plain json cache files, false
     */
    public $cacheJson = false;

    public function init()
    {
        if (!isset($this->className)) {
            throw new InvalidConfigException("Model name should be set in controller actions");
        }
        if (!class_exists($this->className)) {
            throw new InvalidConfigException("Model class does not exists");
        }
    }

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        /** @var \yii\db\ActiveRecord $class */
        $class = $this->className;

        if (null === $current_selected_id = Yii::$app->request->get($this->querySelectedAttribute)) {
            $current_selected_id = Yii::$app->request->get($this->queryParentAttribute);
        }
        $cacheKey = $this->cacheKey instanceof \Closure ? call_user_func($this->cacheKey) : $this->cacheKey;
        $cacheKey = "AdjacencyFullTreeData:{$cacheKey}:{$class}:{$this->querySortOrder}";

        $filename = '';
        if ($this->cacheJson) {
            $cacheKey = md5($cacheKey);
            $filename = Yii::getAlias("@runtime/tree-cache/$cacheKey.json");
            if (file_exists($filename) && (time() - filemtime($filename) < $this->cacheJson)) {
                Yii::$app->response->format = Response::FORMAT_RAW;
                header('Content-Type: application/json');

                return file_get_contents($filename);
            }
        }


        Yii::beginProfile('Get tree');
        if (false === $result = Yii::$app->cache->get($cacheKey)) {
            Yii::beginProfile('Build tree');
            $query = $class::find()
                ->orderBy([$this->querySortOrder => SORT_ASC]);

            if ($this->whereCondition instanceof \Closure) {
                $query->where(call_user_func($this->whereCondition));
            } elseif (count($this->whereCondition) > 0) {
                $query->where($this->whereCondition);
            }

            if (null === $rows = $query->asArray()->all()) {
                return [];
            }

            $result = [];

            foreach ($rows as $row) {
                $parent = ArrayHelper::getValue($row, $this->modelParentAttribute, 0);
                $item = [
                    'id' => ArrayHelper::getValue($row, $this->modelIdAttribute, 0),
                    'parent' => ($parent) ? $parent : '#',
                    'text' => ArrayHelper::getValue($row, $this->modelLabelAttribute, 'item'),
                    'a_attr' => [
                        'data-id' => $row[$this->modelIdAttribute],
                        'data-parent_id' => $row[$this->modelParentAttribute]
                    ],
                ];

                if (null !== $this->varyByTypeAttribute) {
                    $item['type'] = $row[$this->varyByTypeAttribute];
                }

                $result[$row[$this->modelIdAttribute]] = $item;
            }

            if ($this->cacheJson) {
                $encoded = json_encode(array_values($result));
                FileHelper::createDirectory(basename($filename));

                file_put_contents($filename, $encoded);

                Yii::$app->response->format = Response::FORMAT_RAW;
                header('Content-Type: application/json');
                return $encoded;
            }
            Yii::$app->cache->set(
                $cacheKey,
                $result,
                86400,
                new TagDependency([
                    'tags' => [
                        NamingHelper::getCommonTag($class),
                    ],
                ])
            );

            Yii::endProfile('Build tree');
        }

        if (array_key_exists($current_selected_id, $result)) {
            $result[$current_selected_id] = array_merge(
                $result[$current_selected_id],
                ['state' => ['opened' => true, 'selected' => true]]
            );
        }
        Yii::endProfile('Get tree');

        header('Content-Type: application/json');
        echo json_encode(array_values($result));
        Yii::$app->end();
        return array_values($result);
    }
}
