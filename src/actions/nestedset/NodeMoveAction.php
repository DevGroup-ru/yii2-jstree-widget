<?php

namespace devgroup\JsTreeWidget\actions\nestedset;

use devgroup\JsTreeWidget\widgets\TreeWidget;
use yii\base\Action;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\Response;

/**
 * Class NodeMoveAction
 *
 * @package devgroup\JsTreeWidget\actions\nestedset
 */
class NodeMoveAction extends Action
{
    /** @var  ActiveRecord */
    public $className;
    /** @var string set root column name for multi root tree */
    public $rootAttribute = false;
    /** @var string  */
    public $leftAttribute = 'lft';
    /** @var string  */
    public $rightAttribute = 'rgt';
    /** @var string  */
    public $depthAttribute = 'depth';

    /** @var  ActiveRecord */
    private $node;
    /** @var  ActiveRecord */
    private $parent;
    /** @var  string */
    private $tableName;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (true === empty($this->className) || false === is_subclass_of($this->className, ActiveRecord::class)) {
            throw new InvalidConfigException('"className" param must be set and must be child of ActiveRecord');
        }
        /** @var ActiveRecord $class */
        $class = $this->className;
        $this->tableName = $class::tableName();
        $scheme = Yii::$app->getDb()->getTableSchema($this->tableName);
        $columns = $scheme->columns;
        if (false !== $this->rootAttribute && false === isset($columns[$this->rootAttribute])) {
            throw new InvalidConfigException("Column '{$this->rootAttribute}' not found in the '{$this->tableName}' table");
        }
        if (false === isset(
                $columns[$this->leftAttribute],
                $columns[$this->rightAttribute],
                $columns[$this->depthAttribute]
            )
        ) {
            throw new InvalidConfigException(
                "Some of the '{$this->leftAttribute}', '{$this->rightAttribute}', '{$this->depthAttribute}', "
                . "not found in the '{$this->tableName}' columns list"
            );
        }
        TreeWidget::registerTranslations();
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $newParentId = Yii::$app->request->post('parent');
        $oldParentId = Yii::$app->request->post('old_parent');
        $position = Yii::$app->request->post('position');
        $oldPosition = Yii::$app->request->post('old_position');
        $nodeId = Yii::$app->request->post('node_id');
        $siblings = Yii::$app->request->post('siblings', []);
        $class = $this->className;
        if ((int)$newParentId == 0) {
            return ['error' => Yii::t('jstw', 'Can not move node as root!')];
        }
        if ((null === $node = $class::findOne($nodeId)) || (null === $parent = $class::findOne($newParentId))) {
            return ['error' => Yii::t('jstw', 'Invalid node id or parent id received!')];
        }
        $this->node = $node;
        $this->parent = $parent;
        if (false !== $this->rootAttribute && ($node->{$this->rootAttribute} != $parent->{$this->rootAttribute})) {
            return $this->moveMultiRoot($position, $siblings, $oldParentId);
        }
        if ($newParentId == $oldParentId) {
            return $this->reorder($oldPosition, $position, $siblings);
        } else {
            return $this->move($position, $siblings, $oldParentId);
        }
    }

    /**
     * Moves node inside one parent inside one root
     *
     * @param null $oldPosition
     * @param null $position
     * @param array $siblings
     * @return array|bool
     * @throws \yii\db\Exception
     */
    public function reorder($oldPosition = null, $position = null, $siblings = [])
    {
        if (null === $oldPosition || null === $position || true === empty($siblings)) {
            return ['error' => Yii::t('jstw', 'Invalid data provided!')];
        }
        $nodeId = $siblings[$position];
        $class = $this->className;
        $lr = $workWith = [];
        $nodeOperator = $siblingsOperator = '';
        if ($oldPosition > $position) {
            //change next
            $nodeOperator = '-';
            $siblingsOperator = '+';
            $workWith = array_slice($siblings, $position, $oldPosition - $position + 1);
        } else if ($oldPosition < $position) {
            //change previous
            $nodeOperator = '+';
            $siblingsOperator = '-';
            $workWith = array_slice($siblings, $oldPosition, $position - $oldPosition + 1);
        } else {
            return true;
        }
        if (true === empty($workWith)) {
            return ['error' => Yii::t('jstw', 'Invalid data provided!')];
        }
        $lr = $workWithLr = $this->getLr($workWith);
        if (true === empty($lr)) {
            return ['error' => Yii::t('jstw', 'Invalid data provided!')];
        }
        unset($workWithLr[$nodeId]);
        $lft = array_column($workWithLr, $this->leftAttribute);
        $lft = min($lft);
        $rgt = array_column($workWithLr, $this->rightAttribute);
        $rgt = max($rgt);
        $nodeCondition = [
            'and',
            ['>=', $this->leftAttribute, $lft],
            ['<=', $this->rightAttribute, $rgt]
        ];
        $this->applyRootCondition($nodeCondition);
        $nodeDelta = $this->getCount($nodeCondition);
        $nodeDelta *= 2;
        $siblingsCondition = [
            'and',
            ['>=', $this->leftAttribute, $lr[$nodeId][$this->leftAttribute]],
            ['<=', $this->rightAttribute, $lr[$nodeId][$this->rightAttribute]]
        ];
        $this->applyRootCondition($siblingsCondition);
        $nodeChildren = $this->getChildIds($siblingsCondition);
        $siblingsDelta = count($nodeChildren) * 2;
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            //updating necessary node siblings
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('%s%d', $siblingsOperator, $siblingsDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('%s%d', $siblingsOperator, $siblingsDelta)),
                ],
                $nodeCondition
            )->execute();
            //updating node
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                ],
                ['id' => $nodeChildren]
            )->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['error' => $e->getMessage()];
        }
        return true;
    }

    /**
     * Moves node inside one root
     *
     * @param null $position
     * @param array $siblings
     * @param string | integer $oldParentId
     * @return array|bool
     * @throws \yii\db\Exception
     */
    private function move($position = null, $siblings = [], $oldParentId)
    {
        $class = $this->className;
        if (null === $oldParent = $class::findOne($oldParentId)) {
            return ['error' => Yii::t('jstw', "Old parent with id '{id}' not found!", ['id' => $oldParentId])];
        }
        $nodeCountCondition = [
            'and',
            ['>=', $this->leftAttribute, $this->node{$this->leftAttribute}],
            ['<=', $this->rightAttribute, $this->node{$this->rightAttribute}]
        ];
        $this->applyRootCondition($nodeCountCondition);
        $nodeChildren = $this->getChildIds($nodeCountCondition);
        $siblingsDelta = count($nodeChildren) * 2;
        if ($position == 0) {
            $compareRight = $this->parent->{$this->leftAttribute} + 1;
        } else {
            if (false === isset($siblings[$position - 1])) {
                return ['error' => Yii::t('jstw', 'New previous sibling not exists')];
            }
            $newPrevSiblingId = $siblings[$position - 1];
            $newPrevSiblingData = $this->getLr($newPrevSiblingId);
            $compareRight = $newPrevSiblingData[$newPrevSiblingId][$this->rightAttribute];
        }
        if ($this->node->{$this->leftAttribute} > $compareRight) {
            //move node up
            if ($position == 0) {
                $leftFrom = $this->parent->{$this->leftAttribute} + 1;
            } else {
                $leftFrom = $newPrevSiblingData[$newPrevSiblingId][$this->rightAttribute] + 1;
            }
            $rightTo = $this->node->{$this->leftAttribute};
            $nodeDelta = $this->node->{$this->leftAttribute} - $leftFrom;
            $nodeOperator = '-';
            $parentOperator = $siblingsOperator = '+';
            $newParentUpdateField = $this->rightAttribute;
            $oldParentUpdateField = $this->leftAttribute;
        } else if ($this->node->{$this->leftAttribute} < $compareRight) {
            //move node down
            $leftFrom = $this->node->{$this->rightAttribute};
            if ($position == 0) {
                $rightTo = $this->parent->{$this->leftAttribute};
            } else {
                $rightTo = $newPrevSiblingData[$newPrevSiblingId][$this->rightAttribute];
            }
            $nodeOperator = '+';
            $parentOperator = $siblingsOperator = '-';
            $nodeDelta = $rightTo - $siblingsDelta + 1 - $this->node->{$this->leftAttribute};
            $newParentUpdateField = $this->leftAttribute;
            $oldParentUpdateField = $this->rightAttribute;
        } else {
            return ['error' => Yii::t('jstw', 'There are two nodes with same "left" value. This should not be.')];
        }
        $siblingsCondition = [
            'and',
            ['>=', $this->leftAttribute, $leftFrom],
            ['<=', $this->rightAttribute, $rightTo]
        ];
        $this->applyRootCondition($siblingsCondition);
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        $oldParentDepth = $oldParent->{$this->depthAttribute};
        $newParentDepth = $this->parent->{$this->depthAttribute};
        if ($newParentDepth < $oldParentDepth) {
            $depthOperator = '-';
            $depthDelta = $oldParentDepth - $newParentDepth;
        } else {
            $depthOperator = '+';
            $depthDelta = $newParentDepth - $oldParentDepth;
        }
        $commonParentsCondition = [
            'and',
            ['<', $this->leftAttribute, $leftFrom],
            ['>', $this->rightAttribute, $rightTo]
        ];
        $this->applyRootCondition($commonParentsCondition);
        $commonParentsIds = $class::find()->select('id')->where($commonParentsCondition)->column();
        $commonCondition = [
            ['!=', $this->depthAttribute, 0],
            ['not in', 'id', $commonParentsIds],
        ];
        $this->applyRootCondition($commonCondition);
        $newParentCondition = array_merge([
            'and',
            ['<=', $this->leftAttribute, $this->parent->{$this->leftAttribute}],
            ['>=', $this->rightAttribute, $this->parent->{$this->rightAttribute}],
        ], $commonCondition);
        $oldParentsCondition = array_merge([
            'and',
            ['<', $this->leftAttribute, $this->node->{$this->leftAttribute}],
            ['>', $this->rightAttribute, $this->node->{$this->rightAttribute}],
        ], $commonCondition);
        try {
            //updating necessary node siblings
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('%s%d', $siblingsOperator, $siblingsDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('%s%d', $siblingsOperator, $siblingsDelta)),
                ],
                $siblingsCondition
            )->execute();
            //updating old parents
            $db->createCommand()->update(
                $class::tableName(),
                [
                    //down - right
                    $oldParentUpdateField => new Expression($oldParentUpdateField . sprintf('%s%d', $parentOperator, $siblingsDelta)),
                ],
                $oldParentsCondition
            )->execute();
            //updating new parents
            $db->createCommand()->update(
                $class::tableName(),
                [
                    //down - left
                    $newParentUpdateField => new Expression($newParentUpdateField . sprintf('%s%d', $parentOperator, $siblingsDelta)),
                ],
                $newParentCondition
            )->execute();
            //updating node with children
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                    $this->depthAttribute => new Expression($this->depthAttribute . sprintf('%s%d', $depthOperator, $depthDelta)),
                ],
                ['id' => $nodeChildren]
            )->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['error' => $e->getMessage()];
        }
        return true;
    }

    /**
     * Moves node between two roots
     *
     * @param null $position
     * @param array $siblings
     * @param string | integer $oldParentId
     * @return array|bool
     * @throws \yii\db\Exception
     */
    private function moveMultiRoot($position = null, $siblings = [], $oldParentId)
    {
        $class = $this->className;
        if (null === $oldParent = $class::findOne($oldParentId)) {
            return ['error' => Yii::t('jstw', "Old parent with id '{id}' not found!", ['id' => $oldParentId])];
        }
        $nodeCountCondition = [
            'and',
            ['>=', $this->leftAttribute, $this->node->{$this->leftAttribute}],
            ['<=', $this->rightAttribute, $this->node->{$this->rightAttribute}],
            [$this->rootAttribute => $this->node->{$this->rootAttribute}]
        ];
        $nodeChildren = $this->getChildIds($nodeCountCondition);
        $siblingsDelta = count($nodeChildren) * 2;
        if ($position == 0) {
            $leftFrom = $this->parent->{$this->leftAttribute} + 1;
        } else {
            if (false === isset($siblings[$position - 1])) {
                return ['error' => Yii::t('jstw', 'New previous sibling not exists')];
            }
            $newPrevSiblingId = $siblings[$position - 1];
            $newPrevSiblingData = $this->getLr($newPrevSiblingId);
            $leftFrom = $newPrevSiblingData[$newPrevSiblingId][$this->rightAttribute] + 1;
        }
        if ($this->node->{$this->leftAttribute} > $leftFrom) {
            $nodeDelta = $this->node->{$this->leftAttribute} - $leftFrom;
            $nodeOperator = '-';
        } else {
            $nodeDelta = $leftFrom - $this->node->{$this->leftAttribute};
            $nodeOperator = '+';
        }
        $siblingsCondition = [
            'and',
            ['>=', $this->leftAttribute, $leftFrom],
            [$this->rootAttribute => $this->parent->{$this->rootAttribute}]
        ];
        $oldSiblingsCondition = [
            'and',
            ['>', $this->leftAttribute, $this->node->{$this->rightAttribute}],
            [$this->rootAttribute => $this->node->{$this->rootAttribute}]
        ];
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        $oldParentDepth = $oldParent->{$this->depthAttribute};
        $newParentDepth = $this->parent->{$this->depthAttribute};
        if ($newParentDepth < $oldParentDepth) {
            $depthOperator = '-';
            $depthDelta = $oldParentDepth - $newParentDepth;
        } else {
            $depthOperator = '+';
            $depthDelta = $newParentDepth - $oldParentDepth;
        }
        $newParentCondition = [
            'and',
            ['<=', $this->leftAttribute, $this->parent->{$this->leftAttribute}],
            ['>=', $this->rightAttribute, $this->parent->{$this->rightAttribute}],
            [$this->rootAttribute => $this->parent->{$this->rootAttribute}]
        ];
        $oldParentsCondition = [
            'and',
            ['<=', $this->leftAttribute, $oldParent->{$this->leftAttribute}],
            ['>=', $this->rightAttribute, $oldParent->{$this->rightAttribute}],
            [$this->rootAttribute => $oldParent->{$this->rootAttribute}]
        ];
        try {
            //updating necessary node new siblings
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('+%d', $siblingsDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('+%d', $siblingsDelta)),
                ],
                $siblingsCondition
            )->execute();
            //updating necessary node old siblings
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('-%d', $siblingsDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('-%d', $siblingsDelta)),
                ],
                $oldSiblingsCondition
            )->execute();
            //updating old parents
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('-%d', $siblingsDelta)),
                ],
                $oldParentsCondition
            )->execute();
            //updating new parents
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('+%d', $siblingsDelta)),
                ],
                $newParentCondition
            )->execute();
            //updating node with children
            $db->createCommand()->update(
                $class::tableName(),
                [
                    $this->leftAttribute => new Expression($this->leftAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                    $this->rightAttribute => new Expression($this->rightAttribute . sprintf('%s%d', $nodeOperator, $nodeDelta)),
                    $this->depthAttribute => new Expression($this->depthAttribute . sprintf('%s%d', $depthOperator, $depthDelta)),
                    $this->rootAttribute => $this->parent->{$this->rootAttribute}
                ],
                ['id' => $nodeChildren]
            )->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['error' => $e->getMessage()];
        }
        return true;
    }

    /**
     * Returns field set of rows to be modified while reordering
     *
     * @param array $ids
     * @return array|\yii\db\ActiveRecord[]
     */
    private function getLr($ids)
    {
        $class = $this->className;
        return $class::find()
            ->select(['id', $this->leftAttribute, $this->rightAttribute])
            ->where(['id' => $ids])
            ->indexBy('id')
            ->asArray(true)
            ->all();
    }

    /**
     * Returns count of records to be modified while reordering
     *
     * @param array $condition
     * @return int|string
     */
    public function getCount($condition)
    {
        $class = $this->className;
        return $class::find()
            ->select(['id', $this->leftAttribute, $this->rightAttribute, $this->rootAttribute])
            ->where($condition)
            ->count();
    }


    /**
     * Returns child ids of selected node
     *
     * @param array $condition
     * @return array
     */
    private function getChildIds($condition)
    {
        $class = $this->className;
        return $class::find()
            ->select('id')
            ->where($condition)
            ->column();
    }

    /**
     * Applies tree root condition if multi root
     *
     * @param $condition
     */
    private function applyRootCondition(&$condition)
    {
        if (false !== $this->rootAttribute) {
            $condition[] = [$this->rootAttribute => $this->node->{$this->rootAttribute}];
        }
    }
}