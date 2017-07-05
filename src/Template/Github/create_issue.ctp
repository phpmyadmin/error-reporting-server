<?=
    $this->Form->create(
        'Ticket', array(
            'label' => array(
                'class' => 'control-label'
            ),
            'div' => array(
                'class' => 'control-group'
            ),
            'class' => 'input-xxlarge',
            'between' => '<div class="controls">',
            'after' => '</div>',
            'class' => 'form-horizontal',
        )
    );
?>
    <fieldset>
        <legend>Github Issue</legend>
        <?php
            $milestone_default_val = $pma_version;
            if (substr($pma_version, -4) == '-dev') {
                $milestone_default_val = 'Latest_Git';
            }
        ?>
        <?=
            $this->Form->input(
                'summary',
                array(
                    'placeholder' => 'Summary',
                    'value' => $error_name
                )
            );
        ?>
        <?=
            $this->Form->input(
                'milestone',
                array(
                    'placeholder' => 'Milestone',
                    'value'=> $milestone_default_val
                )
            );
        ?>
        <?=
            $this->Form->input(
                'description',
                array(
                    'placeholder' => 'Description',
                    'rows' => 10,
                    'value' => $error_message
                )
            );
        ?>
        <?= $this->Form->input('labels', array('placeholder' => 'Labels')); ?>

        <div class="control-group">
            <div class="controls span6">
                <p>
                    <span class="label label-info">Heads up!</span>
                    The link to the error report is automatically added to the end of the description and an extra label is added to denote that the report is from the automated error reporting system
                </p>
                <button type="submit" class="btn btn-default">Submit</button>
            </div>
        </div>
    </fieldset>
</form>
