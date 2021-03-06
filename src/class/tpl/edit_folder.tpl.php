<!DOCTYPE html>
<html>
  <head>
    <?php FeedPage::includesTpl(); ?>
  </head>
  <body>
    <div class="container-fluid">
      <div class="row-fluid">
        <div id="edit-folder" class="span4 offset4">
          <?php FeedPage::navTpl(); ?>
          <form class="form-horizontal" method="post" action="">
            <fieldset>
              <div class="control-group">
                <label class="control-label" for="foldertitle"><?php echo Intl::msg('Folder title'); ?></label>
                <div class="controls">
                  <input type="text" id="foldertitle" name="foldertitle" value="<?php echo $foldertitle; ?>">
                  <span class="help-block"><?php echo Intl::msg('Leave empty to delete'); ?></span>
                </div>
              </div>

              <div class="control-group">
                <div class="controls">
                  <input class="btn" type="submit" name="cancel" value="<?php echo Intl::msg('Cancel'); ?>"/>
                  <input class="btn" type="submit" name="save" value="<?php echo Intl::msg('Save modifications'); ?>" />
                </div>
              </div>
            </fieldset>

            <input type="hidden" name="returnurl" value="<?php echo $referer; ?>" />
            <input type="hidden" name="token" value="<?php echo Session::getToken(); ?>">
          </form>
        </div>
      </div>
    </div>
  </body>
</html>
