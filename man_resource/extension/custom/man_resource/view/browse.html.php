<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<div id='mainContent' class='main-content'>
  <div id='mainMenu' class='clearfix'>
    <div class='btn-toolbar pull-left'>
      <?php echo html::a(inlink('orgdimension'), "<span class='text'>" . $lang->man_resource->company . "</span>", '', "class='btn btn-link ghost'");?>
      <?php echo html::a(inlink('projectdimension'), "<span class='text'>" . $lang->man_resource->projectCalendar . "</span>", '', "class='btn btn-link ghost'");?>
      <?php echo html::a(inlink('memberdimension'), "<span class='text'>" . $lang->man_resource->person . "</span>", '', "class='btn btn-link ghost'");?>
    </div>
  </div>
  <div class='main-row'>
    <div class='main-col'>
      <div class='panel'>
        <div class='panel-heading'>
          <div class='panel-title'><?php echo $lang->man_resource->browseTitle;?></div>
        </div>
        <div class='panel-body'>
          <p><?php echo $lang->man_resource->browseTip;?></p>
          <ul>
            <li><a href="<?php echo inlink('orgdimension'); ?>"><?php echo $lang->man_resource->company; ?></a></li>
            <li><a href="<?php echo inlink('projectdimension'); ?>"><?php echo $lang->man_resource->projectCalendar; ?></a></li>
            <li><a href="<?php echo inlink('memberdimension'); ?>"><?php echo $lang->man_resource->person; ?></a></li>
            <li><a href="<?php echo inlink('simulate'); ?>"><?php echo $lang->man_resource->simulatedLoad; ?></a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>