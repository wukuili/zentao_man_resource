<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<?php if(common::checkNotCN()):?>
<style>
.table > tbody .c-taskHourPredict > th {width: 245px;}
.table > tbody td.setHoursBox > .predictHoursTitle {width: 140px;}
</style>
<?php endif?>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $lang->man_resource->setPredictHours;?></h2>
  </div>
  <form class="load-indicator main-form" method='post' target='hiddenwin'>
    <table class='table table-form'>
      <tr class='c-taskHourPredict'>
        <th><?php echo $lang->man_resource->taskHourPredict;?></th>
        <td class='setHoursTd'><?php echo html::radio('taskHourPredict', $lang->man_resource->setHoursList, $taskHourPredict);?></td>
        <td></td>
      </tr>
      <tr class='c-notTaskHourPredict'>
        <th><?php echo $lang->man_resource->notTaskHourPredict;?></th>
        <td class='setHoursTd'><?php echo html::radio('notTaskHourPredict', $lang->man_resource->setHoursList, $notTaskHourPredict);?></td>
        <?php $hidden   = $notTaskHourPredict ? '' : 'hidden';?>
        <?php $disabled = $notTaskHourPredict ? '' : 'disabled';?>
        <td class="<?php echo $hidden?> setHoursBox">
          <div class='predictHoursTitle'><?php echo $lang->man_resource->predictHoursTitle;?></div>
          <div class='input-group has-icon-right predictHoursBox'>
            <?php echo html::input('predictHours', $predictHours, "class='form-control' required $disabled");?>
            <label class='input-control-icon-right'>h</label>
          </div>
        </td>
      </tr>
      <tr>
       <?php $tip = $this->config->edition == 'ipd' ? 'maxSetpredicthoursTip' : $this->config->edition . 'SetpredicthoursTip'?>
       <?php
       $prefix  = common::checkNotCN() ? ', ' : '，';
       $tipText = isset($lang->man_resource->$tip) ? $lang->man_resource->$tip : '此处可以设置每项工时预测。开启后，系统将自动预测任务或非任务类条目的工时。';
       $urCommon = isset($lang->URCommon) ? $lang->URCommon : '';
       $erCommon = isset($lang->ERCommon) ? $lang->ERCommon : '';
       if(!isset($config->URAndSR) || !$config->URAndSR) $tipText = str_replace($urCommon . $prefix, '', $tipText);
       if(isset($lang->ERCommon) && (!isset($config->enableER) || !$config->enableER)) $tipText = str_replace($erCommon . $prefix, '', $tipText);
       ?>
        <td colspan='3' class='tipBox'><?php echo $tipText;?></td>
      </tr>
      <tr>
        <td colspan='3' class='text-center'><?php echo html::submitButton('', 'btn btn-primary primary');?></td>
      </tr>
    </table>
  </form>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>
