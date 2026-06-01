<?php
/**
 * The projectdimension view file of man_resource module of ZenTaoPMS.
 */
?>
<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<div id='mainContent' class='main-content'>
  <div id='mainMenu' class='clearfix'>
    <div class='btn-toolbar pull-left'>
      <form method='post' action='<?php echo inlink('projectdimension');?>'>
        <div class='input-control'>
          <span class='form-name'><strong><?php echo $lang->man_resource->projectCol;?></strong></span>
          <?php echo html::select('projectID', $projects, $projectID, "class='form-control chosen'");?>
        </div>
        <div class='input-control'>
          <span class='form-name'><strong><?php echo $lang->man_resource->user;?></strong></span>
          <?php echo html::select('users[]', $userList, $users, "class='form-control picker-select' multiple");?>
        </div>
        <div class='input-control'>
          <span class='form-name'><strong><?php echo $lang->man_resource->date;?></strong></span>
          <div class='input-group'>
            <?php echo html::input('begin', $begin, "class='form-control form-date'");?>
            <span class='input-group-addon'><?php echo $lang->man_resource->to;?></span>
            <?php echo html::input('end', $end, "class='form-control form-date'");?>
          </div>
        </div>
        <div class='input-control'>
          <span class='form-name'><strong><?php echo $lang->man_resource->waitItem;?>/<?php echo $lang->man_resource->doneItem;?></strong></span>
          <?php echo html::select('status', array('todo' => $lang->man_resource->wait, 'done' => $lang->man_resource->done), $status, "class='form-control chosen'");?>
        </div>
        <div class='input-control'>
          <?php echo html::checkbox('showHoliday', array('1' => $lang->man_resource->showHoliday), $showHoliday);?>
        </div>
        <button type='submit' class='btn btn-primary primary'><i class='icon icon-search'></i> <?php echo $lang->man_resource->search;?></button>
      </form>
    </div>
    <div class='btn-toolbar pull-right'>
      <?php if(common::hasPriv('man_resource', 'exportProject')) echo html::a(inlink('exportProject', "projectID=$projectID&begin=" . strtotime($begin) . "&end=" . strtotime($end) . "&mode=$status"), "<i class='icon-export'></i> " . $lang->export, '', "class='btn btn-link iframe' data-width='600px'");?>
      <?php echo html::a(inlink('simulate'), "<i class='icon-cogs'></i> " . $lang->man_resource->simulatedLoad, '', "class='btn btn-primary primary'");?>

      <div class='btn-group menu-actions'>
        <?php echo html::a('javascript:;', "<i class='icon icon-cog-outline muted'></i> " . $lang->man_resource->setting, '', "data-toggle='dropdown' class='btn btn-link ghost'")?>
        <ul class='dropdown-menu pull-right'>
          <li><?php echo html::a(helper::createLink('man_resource', 'setHours', '', '', true), $lang->man_resource->setHours, '', "class='btn btn-link ghost iframe' title='{$lang->man_resource->setHours}' data-width='500px'");?></li>
          <li><?php echo html::a(helper::createLink('man_resource', 'setLoad', '', '', true), $lang->man_resource->setLoad, '', "class='btn btn-link ghost iframe' title='{$lang->man_resource->setLoad}' data-width='600px'");?></li>  
          <li><?php echo html::a(helper::createLink('man_resource', 'setPredictHours', '', '', true), $lang->man_resource->setPredictHours, '', "class='btn btn-link ghost iframe' title='{$lang->man_resource->setPredictHours}' data-width='820px'");?></li>
        </ul>
      </div>
    </div>
  </div>

  <div class='main-table'>
    <table class='table has-sort-head' id='projectList'>
      <thead>
        <tr>
          <th class='w-150px'><?php echo $lang->man_resource->user;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->estimatedHoursCol;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->consumeHoursCol;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->totalEstimatedHoursCol;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->taskCountCol;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->loadRateCol;?></th>
          <th class='w-100px'><?php echo $lang->man_resource->status;?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($calendarData as $userID => $data):?>
        <tr>
          <td><?php echo html::a(inlink('memberdimension', "userID=$userID"), $data['realname']);?></td>
          <td><?php echo $data['estimated_hours'];?></td>
          <td><?php echo $data['consumed_hours'];?></td>
          <td><?php echo $data['remain_hours'];?></td>
          <td><?php echo $data['parallel_tasks'];?></td>
          <td>
              <?php
              $status = $data['load_status'];
              $colorObj = zget($config->man_resource->loadRangeColors, $status, new stdClass());
              $color = isset($colorObj->text) ? $colorObj->text : '#000';
              $bgColor = isset($colorObj->bg) ? $colorObj->bg : '#eee';
              $loadName = zget($lang->man_resource->loadType, $status, $status);
              $rate = min(100, $data['load_rate']);
              ?>
              <div style='color: <?php echo $color;?>'><?php echo $data['load_rate'];?>%</div>
              <div class='load-rate-bar'><div class='load-rate-fill' style='width: <?php echo $rate;?>%; background: <?php echo $color;?>'></div></div>
          </td>
          <td><?php echo $loadName;?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>