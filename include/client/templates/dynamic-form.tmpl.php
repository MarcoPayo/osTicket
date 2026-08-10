<?php
// Return if no visible fields
global $thisclient;
if (!$form->hasAnyVisibleFields($thisclient))
    return;

$isCreate = (isset($options['mode']) && $options['mode'] == 'create');
?>
    <tr><td colspan="2"><hr />
    <div class="form-header" style="margin-bottom:0.5em">
    <h3><?php echo Format::htmlchars($form->getTitle()); ?></h3>
    <div><?php echo Format::display($form->getInstructions()); ?></div>
    </div>
    </td></tr>
    <?php
    // Form fields, each with corresponding errors follows. Fields marked
    // 'private' are not included in the output for clients
    foreach ($form->getFields() as $field) {
        try {
            if (!$field->isEnabled())
                continue;
        }
        catch (Exception $e) {
            // Not connected to a DynamicFormField
        }

        if ($isCreate) {
            if (!$field->isVisibleToUsers() && !$field->isRequiredForUsers())
                continue;
        } elseif (!$field->isVisibleToUsers()) {
            continue;
        }
        ?>
        <tr <?php
            // A conditional field is toggled by slideDown/slideUp against
            // #field<widget id>, and nested rules propagate by listening for
            // show/hide on that same element. Neither exists in this template
            // unless the row carries the id, so without this a visibility
            // constraint silently does nothing here.
            //
            // The class applies the server-side initial state, so a field
            // which starts hidden is not visible before the script runs --
            // the emitted script only binds handlers, it does not evaluate
            // the condition on load.
            try {
                $w = $field->getWidget();
                printf('id="field%s"%s', $w->id,
                    $field->isVisible() ? '' : ' class="hidden"');
            }
            catch (Exception $e) {
                // Field type declares no widget; nothing to toggle
            }
        ?>>
            <td colspan="2" style="padding-top:10px;">
            <?php if (!$field->isBlockLevel()) { ?>
                <label for="<?php echo $field->getFormName(); ?>"><span class="<?php
                    if ($field->isRequiredForUsers()) echo 'required'; ?>">
                <?php echo Format::htmlchars($field->getLocal('label')); ?>
            <?php if ($field->isRequiredForUsers() &&
                    ($field->isEditableToUsers() || $isCreate)) { ?>
                <span class="error">*</span>
            <?php }
            ?></span><?php
                if ($field->get('hint')) { ?>
                    <br /><em style="color:gray;display:inline-block"><?php
                        echo Format::viewableImages($field->getLocal('hint')); ?></em>
                <?php
                } ?>
            <br/>
            <?php
            }
            if ($field->isEditableToUsers() || $isCreate) {
                $field->render(array('client'=>true));
                ?></label><?php
                foreach ($field->errors() as $e) { ?>
                    <div class="error"><?php echo $e; ?></div>
                <?php }
                $field->renderExtras(array('client'=>true));
            } else {
                $val = '';
                if ($field->value)
                    $val = $field->display($field->value);
                elseif (($a=$field->getAnswer()))
                    $val = $a->display();

                echo sprintf('%s </label>', $val);
            }
            ?>
            </td>
        </tr>
        <?php
    }
?>
