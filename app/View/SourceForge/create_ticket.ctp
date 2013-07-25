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
    <?php echo $this->Form->input('summary', array('placeholder' => 'Summary')); ?>
    <?php echo $this->Form->input('milestone', array('placeholder' => 'Milestone')); ?>
    <?php echo $this->Form->input('description',
        array('placeholder' => 'Description', 'rows' => 10)); ?>
    <?php echo $this->Form->input('labels', array('placeholder' => 'Labels')); ?>
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
