<?php
/** @var yii\web\View $this  */
use devgroup\JsTreeWidget\widgets\TreeInputAssetBundle;
use devgroup\JsTreeWidget\widgets\TreeWidget;
use yii\helpers\Html;

/**
 * @var string $input
 * @var string $id
 * @var string $selectIcon
 * @var string $selectText
 * @var bool   $multiple
 * @var bool   $search
 * @var bool   $clickToOpen
 * @var array  $treeConfig
 */

TreeInputAssetBundle::register($this);
?>
<div class="tree-input">
    <?= $input ?>
    <?php if ($clickToOpen): ?>
    <div class="tree-input__selected">
        <a href="#" class="btn btn-primary tree-input__button pull-right">
            <i class="fa-fw <?= $selectIcon ?>"></i>
            <?= $selectText ?>
            <i class="fa fa-fw fa-angle-right tree-input__arrow"></i>
        </a>
        <div class="tree-input__selected-values"></div>
        <div class="clearfix"></div>
    </div>
    <?php endif; ?>
    <div class="tree-input__tree-container <?= $clickToOpen === false ? 'tree-input__tree-container_opened_always' : '' ?>">
        <?php if ($multiple === false && $clickToOpen): ?>
        <div class="tree-input__tree-notice text-info">
            <i class="fa fa-info-circle"></i>
            <?= Yii::t('jstw', 'Double click needed tree node to select it') ?>
        </div>
        <?php endif;?>

        <?php if ($search): ?>
            <div class="input-group">
                <input type="text" class="form-control tree-input__search" placeholder="<?= Html::encode(Yii::t('jstw', 'Type to search'))?>">
                <a href="#" class="btn btn-default input-group-addon tree-input__search-clear">
                    <i class="fa fa-times fa-fw"></i>
                    <?= Yii::t('jstw', 'Clear') ?>
                </a>
            </div>
        <?php endif; ?>

        <?=
        TreeWidget::widget($treeConfig)
        ?>
        <?php if ($multiple && $clickToOpen): ?>
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
$clickToOpenJson = $clickToOpen ? 'true' : 'false';
$js = <<<js
  var treeInput = $('#{$id}').parent();
  var jstree = $('#{$id}__tree');
  var treeContainer = treeInput.find('.tree-input__tree-container');
  var search = treeContainer.find('.tree-input__search');
  if (search.length) {
    var to = false;
    search.keyup(function() {
      if(to) { clearTimeout(to); }
      to = setTimeout(function () {
        var v = search.val();
        jstree.jstree(true).search(v);
      }, 1000);      
    });
    
    treeContainer.find('.tree-input__search-clear').click(function(){
      search.val('');
      jstree.jstree('clear_search');
      return false;
    });
  }
  var clickToOpen = $clickToOpenJson;
  if (clickToOpen) {
      var buttonArrow = treeInput.find('.tree-input__arrow');
      var selectButton = treeInput.find('.tree-input__button');
      var selected = treeInput.find('.tree-input__selected-values');
      selectButton.click(function() {
        treeContainer.toggleClass('tree-input__tree-container_active'); 
        buttonArrow.toggleClass('fa-angle-down');
        return false;
      });
  }
  var emptySelected = function() {
    if (clickToOpen) {
      selected.empty()
    }
    $('#{$id}').val('');
  };
  var selectNode = function(node) {
    if (clickToOpen) {
        var path = jstree.jstree('get_path', node, ' > ');
        selected.append('<div class="tree-input__selected-value">' + path + '</div>');
    }
    
    const val = $('#{$id}').val();
    const selectedNow = val.length > 0 ? val.split(',') : [];
    selectedNow.push(node);
    $('#{$id}').val(selectedNow.join(','));
  };
js;

if ($multiple === false) {
    $js .= <<<js
  if (clickToOpen) {
      $('#{$id}__tree')
        .on("dblclick.jstree", function (event) {
          var node = $(event.target).closest('.jstree-anchor').data("id");
          emptySelected();
          selectNode(node);      
          selectButton.click();
        });
  } else {
      $('#{$id}__tree')
        .on('select_node.jstree', function(event, data) {
          emptySelected();
          selectNode(data.node.id);
        });
  }
  const selectedVal = $('#{$id}').val();
  if (selectedVal > 0) {
    $('#{$id}__tree').on('ready.jstree', function(e, data) {
      emptySelected();
      selectNode(selectedVal);
     
    });
  }
js;
} else {
    $js .= <<<js
    var selectedValues = $('#{$id}').val().split(',');
    $('#{$id}__tree').on('ready.jstree', function(e, data) {
      emptySelected();
      selectedValues.forEach(function(value) {
        selectNode(value);     
        
      });
    });
    var applySelected = function() {
      emptySelected(); 
      $("#{$id}__tree").jstree("get_checked",null,true).forEach(function (id) { 
        selectNode(id);
        
      });
      if (clickToOpen) {
        selectButton.click();
      }
    };
    if (clickToOpen) {
        treeContainer.find('.tree-input__select').click(function() {
          applySelected();      
          return false;
        });
    } else {
      $('#{$id}__tree')
        .on('changed.jstree', function() {
          applySelected();
        });
    }
js;

}

$js .= <<<js

js;


$this->registerJs(
    <<<js
(function(){
$js
})();

js

);