<?php

namespace devgroup\JsTreeWidget\widgets;

use DevGroup\Metronic\widgets\InputWidget;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class TreeInput extends InputWidget
{
    public $treeConfig = [];

    public $multiple = false;

    public $selectIcon = 'fa fa-folder-o';
    public $selectText;

    public function init()
    {
        parent::init();
        if ($this->selectText === null) {
            $this->selectText = Yii::t('jstw', 'Select');
        }
    }

    public function run()
    {
        $id = 'input_tree__' . $this->getId();
        $this->options['id'] = $id;


        if ($this->hasModel()) {
            $input = Html::activeHiddenInput($this->model, $this->attribute, $this->options);
            $value = $this->model->{$this->attribute};
        } else {
            $input = Html::hiddenInput($this->name, $this->value, $this->options);
            $value = $this->value;
        }
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $this->treeConfig['selectedNodes'] = $value;

        $this->treeConfig['id'] = $id . '__tree';
        $oldOptions = isset($this->treeConfig['options']) ? $this->treeConfig['options'] : [];
        $this->treeConfig['options'] = ArrayHelper::merge($oldOptions, [
            'core' => [
                'multiple' => $this->multiple,
                'dblclick_toggle' => false,
            ],
        ]);
        $this->treeConfig['plugins'] = [
            'wholerow',
            'contextmenu',
            'dnd',
            'types',
        ];

        if ($this->multiple) {
            $this->treeConfig['plugins'][] = 'checkbox';
        }

        return $this->render(
            'tree-input',
            [
                'id' => $id,
                'treeConfig' => $this->treeConfig,
                'multiple' => $this->multiple,
                'selectIcon' => $this->selectIcon,
                'selectText' => $this->selectText,
                'input' => $input,
            ]
        );
    }
}
