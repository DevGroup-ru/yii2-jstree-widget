<?php
namespace devgroup\JsTreeWidget;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;
/**
 * Helper action to change parent_ad attribute via JsTree Drag&Drop
 * Example use in controller:
 * ``` php
 * public function actions()
 * {
 *     return [
 *         'move' => [
 *             'class' => TreeNodeMoveAction::className(),
 *             'class_name' => Category::className(),
 *         ],
 *         'upload' => [
 *             'class' => UploadAction::className(),
 *             'upload' => 'theme/resources/product-images',
 *         ],
 *         'remove' => [
 *             'class' => RemoveAction::className(),
 *             'uploadDir' => 'theme/resources/product-images',
 *         ],
 *         'save-info' => [
 *             'class' => SaveInfoAction::className(),
 *         ],
 *     ];
 * }
 * ```
 */

class TreeNodeMoveAction extends Action{
    public $className = null;
    public $modelParentIdField = 'parent_id';
    public $parentId = null;

    public function init()
    {
        if (!isset($this->className)) {
            throw new InvalidConfigException("Model name should be set in controller actions");
        }
        if (!class_exists($this->className)) {
            throw new InvalidConfigException("Model class does not exists");
        }
    }

    public function run($id = null)
    {
        $this->parentId = Yii::$app->request->get('parent_id');
        $class = $this->className;
        if (null === $id || null === $this->parentId || (null === $model = $class::findById($id)) || (null === $parent = $class::findById($this->parentId))) {
            throw new NotFoundHttpException;
        }
        $model->{$this->modelParentIdField} = $parent->id;
        $model->save();
    }
}