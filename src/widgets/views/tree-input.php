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
        <?php if ($multiple === 'false'): ?>
        <div class="tree-input__tree-notice text-info">
            <i class="fa fa-info-circle"></i>
            <?= Yii::t('jstw', 'Double click needed tree node to select it') ?>
        </div>
        <?php endif;?>
        <?=
        TreeWidget::widget($treeConfig)
        ?>
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
js;

if ($multiple === false) {
    $js .= <<<js
  $('#{$id}__tree')
    .bind("dblclick.jstree", function (event) {
      const node = $(event.target).closest("li");
      let path = '';
      window.DAT_node = node;
      node.parents('.jstree-node').find('>.jstree-anchor').each(function () {
        const name = $(this).text();
        //! @todo Add ids combining into hidden field here
        path = path + (path === '' ? '' : ' > ') + name;
      });
      path += ' > ' + node.find('>.jstree-anchor').text();
      selected.empty().append(path);
      $('#{$id}').val(node.find('>.jstree-anchor').data('id'));
      selectButton.click();
    });
js;

}

$this->registerJs($js);