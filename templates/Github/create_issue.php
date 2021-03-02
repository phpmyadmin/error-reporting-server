<?=
    $this->Form->create(
        'Ticket',
        [
            'label' => ['class' => 'control-label'],
            'div' => ['class' => 'control-group'],
            'class' => 'input-xxlarge',
            'between' => '<div class="controls">',
            'after' => '</div>',
            'class' => 'form-horizontal',
        ]
    );
?>
    <fieldset>
        <legend>Github Issue</legend>
        <?=
            $this->Form->input(
                'summary',
                [
                    'placeholder' => 'Summary',
                    'value' => $error_name,
                ]
            );
        ?>
        <?=
            $this->Form->input(
                'description',
                [
                    'placeholder' => 'Description',
                    'rows' => 10,
                    'value' => $error_message,
                ]
            );
        ?>
        <?= $this->Form->input('labels', ['placeholder' => 'Labels']); ?>

        <div class="control-group row">
            <div class="span3">
                <p>
                    <span class="label label-info">Heads up!</span>
                    The link to the error report is automatically added to the end of the description and an extra label is added to denote that the report is from the automated error reporting system
                </p>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </fieldset>
</form>
