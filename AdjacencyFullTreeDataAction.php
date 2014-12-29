<?php

namespace devgroup\JsTreeWidget;

use devgroup\TagDependencyHelper\ActiveRecordHelper;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;
use yii\web\Response;

class AdjacencyFullTreeDataAction extends Action {

    public $class_name = null;

    public $model_id_attribute = 'id';

    public $model_label_attribute = 'name';

    public $model_parent_attribute = 'parent_id';

    public $vary_by_type_attribute = null;

    public $query_parent_attribute = 'id';
    public $query_selected_attribute = 'selected_id';
    /**
     * Additional conditions for retrieving tree(ie. don't display nodes marked as deleted)
     * @var array
     */
    public $whereCondition = [];

    /**
     * Cache key prefix. Should be unique if you have multiple actions with different $whereCondition
     * @var string
     */
    public $cacheKey = 'FullTree';

    /**
     * Cache lifetime for the full tree
     * @var int
     */
    public $cacheLifeTime = 86400;

    public function init()
    {
        if (!isset($this->class_name)) {
            throw new InvalidConfigException("Model name should be set in controller actions");
        }
        if (!class_exists($this->class_name)) {
            throw new InvalidConfigException("Model class does not exists");
        }
    }

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $class = $this->class_name;

        if (null === $current_selected_id = Yii::$app->request->get($this->query_selected_attribute)) {
            $current_selected_id = Yii::$app->request->get($this->query_parent_attribute);
        }

        $cacheKey = "AdjacencyFullTreeData:".$this->cacheKey.":$class";

        if (false === $result = Yii::$app->cache->get($cacheKey)) {

            $query = $class::find()
                ->orderBy($this->model_id_attribute . ' ASC');

            if (count($this->whereCondition) > 0) {
                $query = $query->where($this->whereCondition);
            }

            if (null === $rows = $query->asArray()->all()) {
                return [];
            }

            $result = [];

            foreach ($rows as $row) {
                $item = [
                    'id' => $row[$this->model_id_attribute],
                    'parent' => ($row[$this->model_parent_attribute] > 0) ? $row[$this->model_parent_attribute] : '#',
                    'text' => $row[$this->model_label_attribute],
                    'a_attr' => ['data-id'=>$row[$this->model_id_attribute], 'data-parent_id'=>$row[$this->model_parent_attribute]],
                ];

                if (null !== $this->vary_by_type_attribute) {
                    $item['type'] = $row[$this->vary_by_type_attribute];
                }

                $result[$row[$this->model_id_attribute]] = $item;
            }

            Yii::$app->cache->set(
                $cacheKey,
                $result,
                86400,
                new TagDependency(
                    [
                        'tags' => [
                            ActiveRecordHelper::getCommonTag($class),
                        ],
                    ]
                )
            );
        }

        if (array_key_exists($current_selected_id, $result)) {
            $result[$current_selected_id] = array_merge($result[$current_selected_id], ['state' => ['opened' => true, 'selected' => true]]);
        }

        return array_values($result);

    }
} 