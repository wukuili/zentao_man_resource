<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $lang->man_resource->setHours;?></h2>
  </div>
  <form class="load-indicator main-form" method='post' target='hiddenwin'>
    <table class='table table-form'>
      <tr>
        <th class='w-150px'><?php echo $lang->custom->workingHours;?></th>
        <td><?php echo html::input('defaultWorkhours', $workhours, "class='form-control w-80px'");?></td>
        <td></td>
      </tr>
      <tr>
        <th><?php echo $lang->custom->setWeekend;?></th>
        <td colspan='2'><?php echo html::radio('weekend', $lang->custom->weekendList, $weekend);?></td>
      </tr>
      <tr>
        <td colspan='3' class='text-center'><?php echo html::submitButton('', 'btn btn-primary primary');?></td>
      </tr>
    </table>
  </form>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>