<?php

namespace devgroup\JsTreeWidget\actions\nestedset;

use yii\base\Action;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\Response;

class NodeMoveAction extends Action
{
    /** @var  ActiveRecord */
    public $className;

    public $rootAttribute = 'tree';
    public $leftAttribute = 'lft';
    public $rightAttribute = 'rgt';
    public $depthAttribute = 'depth';

    private $node;
    private $parent;


    public function init()
    {
        //check for move node as root
        //root reorder
        //maybe move root to another root
    }

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $newParentId = Yii::$app->request->post('parent');
        $oldParentId = Yii::$app->request->post('old_parent');
        $position = Yii::$app->request->post('position');
        $oldPosition = Yii::$app->request->post('old_position');
        $nodeId = Yii::$app->request->post('node_id');
        $siblings = Yii::$app->request->post('siblings', []);
        $oldSiblings = Yii::$app->request->post('oldSiblings', []);
        $class = $this->className;
        if ((null === $node = $class::findOne($nodeId)) || (null === $parent = $class::findOne($newParentId))) {
            return ['error' => 'Invalid data received'];
        }
        $this->node = $node;
        $this->parent = $parent;
        if ($newParentId == $oldParentId) {
            return $this->reorder($oldPosition, $position, $siblings);
        } else {
            return $this->move($position, $siblings, $oldParentId);
        }
    }

    public function reorder($oldPosition = null, $position = null, $siblings = [])
    {
        if (null === $oldPosition || null === $position || true === empty($siblings)) {
            return ['info' => 'nothing to change'];
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
            return ['info' => 'nothing to change'];
        }
        if (true === empty($workWith)) {
            return ['info' => 'nothing to change'];
        }
        $lr = $workWithLr = $this->getLr($workWith);
        if (true === empty($lr)) {
            return ['info' => 'nothing to change'];
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
            return ['error', $e->getMessage()];
        }
    }

    private function move($position = null, $siblings = [], $oldParentId)
    {
        $class = $this->className;
        if (null === $oldParent = $class::findOne($oldParentId)) {
            return ['error' => "Old parent with id '{$oldParentId}' not found"];
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
                return ['error' => 'New previous sibling not exists'];
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
            return ['error' => 'There are two nodes with same "left" value. This should not be.'];
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
            return ['error', $e->getMessage()];
        }
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
        $lr = $class::find()
            ->select(['id', $this->leftAttribute, $this->rightAttribute])
            ->where(['id' => $ids])
            ->indexBy('id')
            ->asArray(true)
            ->all();
        return $lr;
    }

    /**
     * Returns count of records to be modified while reordering
     * @param array $condition
     * @return int|string
     */
    public function getCount($condition)
    {
        $class = $this->className;
        $count = $class::find()
            ->select(['id', $this->leftAttribute, $this->rightAttribute, $this->rootAttribute])
            ->where($condition)
            ->count();
        return $count;
    }

    private function getChildIds($condition)
    {
        $class = $this->className;
        $count = $class::find()
            ->select('id')
            ->where($condition)
            ->column();
        return $count;
    }

    private function applyRootCondition(&$condition)
    {
        if (false !== $this->rootAttribute) {
            $condition[] = [$this->rootAttribute => $this->node->{$this->rootAttribute}];
        }
    }
}