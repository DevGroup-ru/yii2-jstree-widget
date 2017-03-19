<?php
/** @var yii\web\View $this  */
use devgroup\JsTreeWidget\widgets\TreeWidget;

/**
 * @var string $input
 * @var string $id
 * @var string $selectIcon
 * @var string $selectText
 * @var bool   $multiple
 * @var array  $treeConfig
 */
?>
<div class="tree-input">
    <?= $input ?>
    <div class="tree-input__selected">
        <a href="#" class="btn btn-primary tree-input__button pull-right">
            <i class="<?= $selectIcon ?>"></i>
            <?= $selectText ?>
            <i class="fa fa-angle-right tree-input__arrow"></i>
        </a>
        <div class="tree-input__selected-values"></div>
    </div>
    <div class="tree-input__tree-container">
        <div class="tree-input__tree-notice text-info">
            <i class="fa fa-info-circle"></i>
            <?php
            if ($multiple === 'false') {
                echo Yii::t('jstw', 'Double click needed tree node to select it');
            }
            ?>
        </div>
        <?=
        TreeWidget::widget([
            $treeConfig
        ])
        ?>
    </div>
</div>
<?php
$js = <<<js
  var treeInput = $('#{$id}');
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

if ($multiple) {
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
      path += ' > ' + node.find('.jstree-anchor').text();
      selected.empty().append(path);
      selectButton.click();
    });
js;

}

$this->registerJs($js);