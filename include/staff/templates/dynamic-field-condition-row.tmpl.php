<?php
/*********************************************************************
    dynamic-field-condition-row.tmpl.php

    One row of the condition builder. Included by
    dynamic-field-conditions.tmpl.php, once per stored term and once more
    with $i set to the literal __I__ to produce the template the "Add
    condition" button clones.

    Expects: $i (row index), $term (stored term, may be empty), $candidates.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

$selected = isset($term['field']) ? $term['field'] : null;
$op       = (isset($term['op']) && $term['op'] == 'neq') ? 'neq' : 'eq';
$value    = isset($term['value']) ? $term['value'] : '';

// The value input starts as a picker when the selected field enumerates its
// values, so a stored rule renders correctly without waiting for script.
$rowChoices = null;
foreach ($candidates as $c) {
    if ($selected !== null && $c['field']->get('id') == $selected)
        $rowChoices = $c['choices'];
}
?>
<tr class="condition">
    <td width="40%">
        <select class="condition-field" name="visibility[terms][<?php echo $i; ?>][field]">
<?php foreach ($candidates as $c) {
        $cf = $c['field']; ?>
            <option value="<?php echo $cf->get('id'); ?>" <?php
                if ($selected !== null && $cf->get('id') == $selected)
                    echo 'selected="selected"'; ?>><?php
                echo Format::htmlchars($cf->get('label') ?: $cf->get('name')); ?></option>
<?php } ?>
        </select>
    </td>
    <td width="15%">
        <select name="visibility[terms][<?php echo $i; ?>][op]">
            <option value="eq"><?php echo __('is'); ?></option>
            <option value="neq" <?php if ($op == 'neq') echo 'selected="selected"';
                ?>><?php echo __('is not'); ?></option>
        </select>
    </td>
    <td width="35%" class="condition-value"
        data-name="visibility[terms][<?php echo $i; ?>][value]">
<?php if ($rowChoices) { ?>
        <select name="visibility[terms][<?php echo $i; ?>][value]">
<?php   foreach ($rowChoices as $v => $label) { ?>
            <option value="<?php echo Format::htmlchars($v); ?>" <?php
                if ((string) $v === (string) $value) echo 'selected="selected"';
                ?>><?php echo Format::htmlchars($label); ?></option>
<?php   } ?>
        </select>
<?php } else { ?>
        <input type="text" name="visibility[terms][<?php echo $i; ?>][value]"
            value="<?php echo Format::htmlchars($value); ?>"/>
<?php } ?>
    </td>
    <td width="10%">
        <a href="#" class="remove-condition" title="<?php echo __('Remove'); ?>">
            <i class="icon-trash"></i></a>
    </td>
</tr>
