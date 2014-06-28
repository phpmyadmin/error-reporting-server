<?php echo $this->Form->create('Ticket', array(
  'inputDefaults' => array(
    'label' => array(
      'class' => 'control-label'
    ),
    'div' => array(
      'class' => 'control-group'
    ),
    'class' => 'input-xxlarge',
    'between' => '<div class="controls">',
    'after' => '</div>',
  ),
  'class' => 'form-horizontal',
))?>
  <fieldset>
    <legend>Sourceforge ticket</legend>
    <?php
      if (substr($pma_version, -4) == '-dev') {
        $milestone_default_val = 'Latest_Git';
      } else {
        // Ignore whatever after -
        $arr = explode('-',$pma_version);
        $arr = explode('.',$arr[0]);
        // remove custom strings appended to versions like in 4.2.2deb0.1
        $tmp_arr = preg_split("/[a-zA-Z]+/", $arr[2]);
        $arr[2] = $tmp_arr[0];
        $arr = array_splice($arr, 0, 3);
        $milestone_default_val = implode('.', $arr);
      }
      echo $this->Form->input('summary', array('placeholder' => 'Summary'));
      echo $this->Form->input('milestone', array('placeholder' => 'Milestone', 'value'=> $milestone_default_val));
      echo $this->Form->input('description',
          array('placeholder' => 'Description', 'rows' => 10));
      echo $this->Form->input('labels', array('placeholder' => 'Labels'));
    ?>
    <div class="control-group">
      <div class="controls span6">
        <p>
          <span class="label label-info">Heads up!</span>
          The link to the error report is automatically added to the end of the
          description and an extra label is added to denote that the report is from
          the automated error reporting system
        </p>
        <button type="submit" class="btn">Submit</button>
      </div>
    </div>
  </fieldset>
</form>
