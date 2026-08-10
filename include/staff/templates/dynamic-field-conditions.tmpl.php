<?php
/*********************************************************************
    dynamic-field-conditions.tmpl.php

    Condition builder for a field's visibility rule. Included by
    dynamic-field-config.tmpl.php as its own tab.

    Posts into $_POST['visibility'], which DynamicFormField::setConfiguration()
    hands to FieldVisibilityConstraint::normalize(). Inputs are named so PHP
    assembles the nested structure itself:

        visibility[match]              all | any
        visibility[negated]            0 = show when matched, 1 = hide
        visibility[terms][N][field]    id of the field being tested
        visibility[terms][N][op]       eq | neq
        visibility[terms][N][value]

    Term indices need not be contiguous -- normalize() iterates whatever
    arrives -- so removing a row does not have to renumber the rest.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

$candidates = $field->getConditionCandidates();
$conditionErrors = $field->getVisibilityErrors();
$rule = $field->getVisibilityDefinition() ?: array();

// A rejected save re-renders this dialog. Show what was submitted rather
// than what is stored, so the correction is made against the author's own
// input instead of silently discarding it.
if ($conditionErrors && isset($_POST['visibility'])
        && ($posted = FieldVisibilityConstraint::normalize(
                $_POST['visibility'], $field->getId())))
    $rule = $posted;

$terms = (isset($rule['terms']) && is_array($rule['terms'])) ? $rule['terms'] : array();

// Nested groups can be stored, but this builder only edits a flat list. Keep
// them out of the editable rows so saving cannot silently discard them.
$flat = array();
$nested = 0;
foreach ($terms as $t) {
    if (isset($t['terms'])) { $nested++; continue; }
    $flat[] = $t;
}

// value => label for every candidate, so the value input can become a picker
$choiceMap = array();
foreach ($candidates as $c) {
    if ($c['choices'])
        $choiceMap[$c['field']->get('id')] = $c['choices'];
}
?>
<div class="hidden tab_content" id="conditions">

<?php if (!$candidates) { ?>
    <div class="span12">
        <p><em><?php echo __('No other field on this form can be used as a condition.'); ?></em></p>
        <p><em><?php echo __('Conditions can test drop-down, checkbox and short text fields. Add one to this form to make this field conditional.'); ?></em></p>
    </div>
<?php } else { ?>

    <div class="span12">
        <p><em><?php echo __('Show or hide this field based on what has been entered in another field on the same form.'); ?></em></p>
    </div>

<?php foreach ($conditionErrors as $e) { ?>
    <div class="span12">
        <div class="error"><?php echo Format::htmlchars($e); ?></div>
    </div>
<?php } ?>

<?php if ($nested) { ?>
    <div class="span12">
        <p id="msg_warning"><?php echo sprintf(
            __('This rule contains %d grouped condition(s) which this editor cannot show. Saving here will keep them.'),
            $nested); ?></p>
    </div>
<?php } ?>

    <div class="span12" style="margin-bottom:8px">
        <select name="visibility[negated]">
            <option value="0"><?php echo __('Show this field when'); ?></option>
            <option value="1" <?php if (!empty($rule['negated'])) echo 'selected="selected"';
                ?>><?php echo __('Hide this field when'); ?></option>
        </select>
        <select name="visibility[match]">
            <option value="all"><?php echo __('all'); ?></option>
            <option value="any" <?php if (isset($rule['match']) && $rule['match'] == 'any')
                echo 'selected="selected"'; ?>><?php echo __('any'); ?></option>
        </select>
        <?php echo __('of the following conditions are met:'); ?>
    </div>

    <table class="condition-list" width="100%">
        <tbody>
<?php
    $i = 0;
    foreach ($flat as $term) {
        include STAFFINC_DIR . 'templates/dynamic-field-condition-row.tmpl.php';
        $i++;
    }
?>
        </tbody>
    </table>

    <div class="span12" style="margin-top:6px">
        <a href="#" class="add-condition"><i class="icon-plus-sign"></i>
            <?php echo __('Add condition'); ?></a>
    </div>

    <div class="span12" style="margin-top:10px">
        <em style="color:gray"><?php echo __('A field which is hidden is not validated and its value is not required.'); ?></em>
    </div>

<?php } ?>
</div>

<?php if ($candidates) { ?>
<script type="text/javascript">
!(function() {
    var root = $('#conditions'),
        // Cast so an empty map encodes as {} rather than [], and a lookup by
        // field id is a plain property access either way.
        choices = <?php echo JsonDataEncoder::encode((object) $choiceMap); ?>,
        // Indices only have to be unique, not contiguous.
        next = <?php echo count($flat); ?>;

    // Template for a fresh row, with __I__ standing in for the index.
    var blank = <?php
        $i = '__I__';
        $term = array();
        ob_start();
        include STAFFINC_DIR . 'templates/dynamic-field-condition-row.tmpl.php';
        echo JsonDataEncoder::encode(ob_get_clean());
    ?>;

    // Swap the value input between a picker and free text depending on
    // whether the field being tested can enumerate its values.
    function retarget(row) {
        var id = row.find('select.condition-field').val(),
            holder = row.find('.condition-value'),
            name = holder.data('name'),
            current = holder.find(':input').val(),
            input;

        if (choices[id]) {
            input = $('<select>');
            $.each(choices[id], function(v, label) {
                input.append($('<option>').attr('value', v).text(label));
            });
            input.val(current);
        }
        else {
            input = $('<input>').attr('type', 'text').val(current);
        }
        input.attr('name', name);
        holder.empty().append(input);
    }

    root.on('change', 'select.condition-field', function() {
        retarget($(this).closest('tr'));
    });

    root.on('click', '.remove-condition', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    root.on('click', '.add-condition', function(e) {
        e.preventDefault();
        var row = $(blank.replace(/__I__/g, next++));
        root.find('table.condition-list tbody').append(row);
        retarget(row);
    });
})();
</script>
<style type="text/css">
#conditions table.condition-list td {
    padding: 3px 4px;
    vertical-align: middle;
}
#conditions table.condition-list select,
#conditions table.condition-list input[type=text] {
    max-width: 100%;
}
</style>
<?php } ?>
