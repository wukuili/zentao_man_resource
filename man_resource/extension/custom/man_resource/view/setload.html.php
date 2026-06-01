<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<?php js::set('loadTypes', array_keys($lang->man_resource->loadType));?>
<?php js::set('isPost', $isPost);?>
<style>
<?php foreach($config->man_resource->loadRangeColors as $name => $color) echo ".loadType-$name .label-dot {background-color: $color->bg}";?>
</style>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $lang->man_resource->setLoad;?></h2>
  </div>
  <form class="load-indicator main-form form-ajax" method='post'>
    <table class='table table-form loadRange-table'>
      <?php $prevType = '';?>
      <?php foreach($lang->man_resource->loadType as $loadType => $loadName):?>
      <tr class='<?php echo "loadType-$loadType";?> text-center'>
        <td class='w-30px'><span class="label label-dot"></span></td>
        <td class='text-center'>
          <div class='input-group has-icon-right'>
            <?php
            echo html::input($prevType, zget($loadRangeList, $prevType, 0), "data-type='$prevType' class='form-control loadrange-input type-$prevType' minlength='1' maxlength='3'" . ($prevType == '' ? ' disabled' : ''));
            if($prevType != '') echo "<label for='$prevType' class='input-control-icon-right'>%</label>";
            ?>
          </div>
        </td>
        <?php $widthClass = common::checkNotCN() ? 'w-90px' : 'w-80px';?>
        <td class="<?php echo $widthClass?>"><?php echo ' ≤ ' . $loadName . ' < ';?></td>
        <td class='text-center'>
          <?php if($loadType == 'extreme'):?>
          <?php echo html::input($loadType, '∞', "class='form-control' disabled");?>
          <?php else:?>
          <div class='input-group has-icon-right'>
            <?php echo html::input($loadType, zget($loadRangeList, $loadType, '∞'), "data-type='$loadType' class='form-control loadrange-input type-$loadType' minlength='1' maxlength='3'");?>
            <label for='<?php echo $loadType;?>' class='input-control-icon-right'>%</label>
          </div>
          <?php endif;?>
        </td>
      </tr>
      <?php $prevType = $loadType;?>
      <?php endforeach;?>
      <tr>
        <td colspan='4' class='text-center'><?php echo html::submitButton('', 'btn btn-primary primary');?></td>
      </tr>
    </table>
  </form>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>