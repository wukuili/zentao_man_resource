<?php include $app->moduleRoot . 'common/view/header.html.php';?>
<div id='mainContent' class='main-content'>
  <div class='main-header'>
    <h2><?php echo $lang->export;?></h2>
  </div>
  <form class='main-form' method='post' target='hiddenwin'>
    <table class="table table-form">
      <tbody>
        <tr>
          <th class='w-120px'><?php echo $lang->file->fileName;?></th>
          <td><?php echo html::input('fileName', $fileName, "class='form-control' autofocus");?></td>
        </tr>
        <tr>
          <th><?php echo $lang->file->extension;?></th>
          <td><?php echo html::select('fileType', array('xlsx' => 'xlsx', 'csv' => 'csv'), 'xlsx', 'class="form-control"');?></td>
        </tr>
        <tr>
          <td colspan='2' class='text-center'><?php echo html::submitButton($lang->export, "onclick='setTimeout(function(){parent.$.closeModal()}, 1000);'", 'btn btn-primary primary');?></td>
        </tr>
      </tbody>
    </table>
  </form>
</div>
<?php include $app->moduleRoot . 'common/view/footer.html.php';?>
