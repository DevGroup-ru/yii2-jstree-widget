<?php
/** @var yii\web\View $this  */
use devgroup\JsTreeWidget\widgets\TreeInputAssetBundle;
use devgroup\JsTreeWidget\widgets\TreeWidget;

/**
 * @var string $input
 * @var string $id
 * @var string $selectIcon
 * @var string $selectText
 * @var bool   $multiple
 * @var array  $treeConfig
 */

TreeInputAssetBundle::register($this);
?>
<div class="tree-input">
    <?= $input ?>
    <div class="tree-input__selected">
        <a href="#" class="btn btn-primary tree-input__button pull-right">
            <i class="fa-fw <?= $selectIcon ?>"></i>
            <?= $selectText ?>
            <i class="fa fa-fw fa-angle-right tree-input__arrow"></i>
        </a>
        <div class="tree-input__selected-values"></div>
        <div class="clearfix"></div>
    </div>
    <div class="tree-input__tree-container">
        <?php if ($multiple === false): ?>
        <div class="tree-input__tree-notice text-info">
            <i class="fa fa-info-circle"></i>
            <?= Yii::t('jstw', 'Double click needed tree node to select it') ?>
        </div>
        <?php endif;?>
        <?=
        TreeWidget::widget($treeConfig)
        ?>
        <?php if ($multiple): ?>
        <div class="tree-input__tree-footer">
            <a href="#" class="btn btn-primary tree-input__select">
                <i class="fa fa-fw fa-check"></i>
                <?= Yii::t('jstw', 'OK') ?>
            </a>
        </div>
        <?php endif;?>
    </div>
</div>
<?php
$js = <<<js
  var treeInput = $('#{$id}').parent();
  var treeContainer = treeInput.find('.tree-input__tree-container');
  var buttonArrow = treeInput.find('.tree-input__arrow');
  var selectButton = treeInput.find('.tree-input__button');
  var selected = treeInput.find('.tree-input__selected-values');
  selectButton.click(function() {
    treeContainer.toggleClass('tree-input__tree-container_active'); 
    buttonArrow.toggleClass('fa-angle-down');
    return false;
  });
  var emptySelected = function() {
    selected.empty()
    $('#{$id}').val('');
  };
  var selectNode = function(node) {
    var path = '';
    var anchor = node.find('>.jstree-anchor');
    node.parents('.jstree-node').find('>.jstree-anchor').each(function () {
      var name = $(this).text();
      path = path + (path === '' ? '' : ' > ') + name;
    });
    path = path + (path === '' ? '' : ' > ') + anchor.text();
    selected.append('<div class="tree-input__selected-value">' + path + '</div>');
    const val = $('#{$id}').val();
    const selectedNow = val.length > 0 ? val.split(',') : [];
    selectedNow.push(anchor.data('id'));
    $('#{$id}').val(selectedNow.join(','));
  };
js;

if ($multiple === false) {
    $js .= <<<js
  $('#{$id}__tree')
    .bind("dblclick.jstree", function (event) {
      var node = $(event.target).closest("li");
      selectNode(node);      
      selectButton.click();
    });
  const selectedVal = parseInt($('#{$id}').val());
  if (selectedVal > 0) {
    $('#{$id}__tree').on('ready.jstree', function(e, data) {
      var node = data.instance.get_node(selectedVal, true).closest('li');
      emptySelected();
      selectNode(node);
     
    });
  }
js;
} else {
    $js .= <<<js
    const selectedValues = $('#{$id}').val().split(',');
    $('#{$id}__tree').on('ready.jstree', function(e, data) {
      emptySelected();
      selectedValues.forEach(function(value) {
        var selectedVal = parseInt(value);
        var node = data.instance.get_node(selectedVal, true).closest('li');
        selectNode(node);      
      });
    });
    treeContainer.find('.tree-input__select').click(function() {
      emptySelected(); 
      $("#{$id}__tree").jstree("get_checked",null,true).forEach(function (id) { 
        var selectedVal = parseInt(id);
        if (selectedVal > 0) {
            var node = $("#{$id}__tree").jstree('get_node', selectedVal, true).closest('li');
            selectNode(node);
        }
      });
      selectButton.click();
      return false;
    });
js;

}

$js .= <<<js

js;


$this->registerJs($js);