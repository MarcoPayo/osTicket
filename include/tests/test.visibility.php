<?php
/*********************************************************************
    test.visibility.php

    Unit tests for VisibilityConstraint, the conditional-field engine in
    include/class.forms.php.

    Each case here corresponds to a defect fixed in that class. Running this
    file against the pre-fix revision produces 7 failures; see the commit
    that introduced it.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

// Fixtures come from stubs.forms.php, loaded by run.php.

// Overridable so the suite can be pointed at another revision of the file,
// which is how these cases were shown to fail before the fixes landed:
//
//   git show <rev>:include/class.forms.php > /tmp/old.php
//   OSTICKET_FORMS_PHP=/tmp/old.php php include/tests/run.php visibility
//
load_class(getenv('OSTICKET_FORMS_PHP')
    ?: dirname(__DIR__).'/class.forms.php', 'VisibilityConstraint');


T::suite('VisibilityConstraint::getAllFields()');

// ::getAllFields() drives which fields get change handlers bound. It once
// tested the constraint KEY for `instanceof Q` rather than the value, so
// nested groups were never descended into and the fields inside them were
// never wired up -- the rule evaluated correctly on submit but did nothing
// in the browser.
$vc = new VisibilityConstraint(new Q(array()));
$found = $vc->getAllFields(new Q(array(
    'outer__eq' => 'x',
    new Q(array('inner__eq' => 'y', 'deep__neq' => 'z'), Q::ANY),
)));
sort($found);
T::check('descends into nested Q groups', $found, array('deep', 'inner', 'outer'));


T::suite('VisibilityConstraint::isVisible()');

// A constraint may name a field which is no longer on the form, because
// field names are admin-editable and fields can be deleted. ::compileQ()
// skips such terms, so ::compileQPhp() must too, or the browser and the
// server disagree about which expression is being evaluated.
$form = new StubForm();
$target = $form->add('target', new StubField('t1'));

$ghost = new VisibilityConstraint(new Q(array('ghost__eq' => 'boo')));
T::nothrow('missing field does not throw', function() use ($ghost, $target) {
    $ghost->isVisible($target);
});
T::check('missing field skipped, AND group stays visible',
    $ghost->isVisible($target), true);

// The reachable null dereference: an empty comparison value makes
// in_array(null, array('')) loosely true, so evaluation reached
// ->isVisible() on the missing (null) field. A non-empty value short
// circuits at in_array() and never gets there, which is why this went
// unnoticed.
$ghost_empty = new VisibilityConstraint(new Q(array('ghost__eq' => '')));
T::nothrow('missing field with empty value does not throw',
    function() use ($ghost_empty, $target) {
        $ghost_empty->isVisible($target);
    });

// ::compileQPhp() reassigned its own $field parameter while looping over
// constraints, so a nested group evaluated after a scalar term recursed
// with the wrong field.
$form2 = new StubForm();
$t2 = $form2->add('target', new StubField('t2'));
$form2->add('a', new StubField('a2', 'yes'));
$form2->add('b', new StubField('b2', 'no'));
$both = new VisibilityConstraint(new Q(array(
    'a__eq' => 'yes',
    new Q(array('b__eq' => 'no')),
)));
T::check('scalar term and nested group both satisfied',
    $both->isVisible($t2), true);

$form3 = new StubForm();
$t3 = $form3->add('target', new StubField('t3'));
$form3->add('a', new StubField('a3', 'yes'));
$unmet = new VisibilityConstraint(new Q(array(
    'a__eq' => 'yes',
    new Q(array('a__eq' => 'no')),
)));
T::check('nested group failing hides the field',
    $unmet->isVisible($t3), false);

// OR groups glue with || and start from false.
$form4 = new StubForm();
$t4 = $form4->add('target', new StubField('t4'));
$form4->add('a', new StubField('a4', 'no'));
$form4->add('b', new StubField('b4', 'yes'));
$ored = new VisibilityConstraint(new Q(array(
    'a__eq' => 'yes',
    'b__eq' => 'yes',
), Q::ANY));
T::check('OR group satisfied by either term', $ored->isVisible($t4), true);

// Both compilers read $Q->isNegated as a property; Q defines $negated with
// an isNegated() accessor, so negation silently never applied.
$form5 = new StubForm();
$t5 = $form5->add('target', new StubField('t5'));
$form5->add('a', new StubField('a5', 'yes'));
$negated = new VisibilityConstraint(new Q(array('a__eq' => 'yes'), Q::NEGATED));
T::check('NEGATED group inverts the result', $negated->isVisible($t5), false);

// Multi-value matching, pipe separated.
$form6 = new StubForm();
$t6 = $form6->add('target', new StubField('t6'));
$form6->add('a', new StubField('a6', 'two'));
$multi = new VisibilityConstraint(new Q(array('a__eq' => 'one|two|three')));
T::check('pipe separated values match any listed',
    $multi->isVisible($t6), true);


T::suite('VisibilityConstraint::emitJavascript()');

// The expression was compiled after the opening <script> tag had already
// been written. A constraint naming only unresolvable fields compiles to an
// empty string, which was emitted as `if ()` -- a syntax error that took out
// the entire inline block, including handlers for unrelated fields.
$form7 = new StubForm();
$t7 = $form7->add('target', new StubField('t7'));
$dead = new VisibilityConstraint(new Q(array('ghost__eq' => 'boo')));
ob_start();
$dead->emitJavascript($t7);
$js = ob_get_clean();
T::check('unresolvable constraint emits nothing', trim($js), '');
T::check('never emits an empty if ()',
    (bool) preg_match('/if\s*\(\s*\)/', $js), false);

// A resolvable constraint still wires up normally.
$form8 = new StubForm();
$t8 = $form8->add('target', new StubField('t8'));
$form8->add('a', new StubField('a8', 'yes'));
$live = new VisibilityConstraint(new Q(array('a__eq' => 'yes')));
ob_start();
$live->emitJavascript($t8);
$js8 = ob_get_clean();
T::check('emits the recheck function',
    strpos($js8, 'recheck_t8') !== false, true);
T::check('binds change on the referenced field',
    strpos($js8, "\$('#a8').on('change'") !== false, true);
T::check('binds show/hide so nested rules propagate',
    strpos($js8, "\$('#fielda8').on('show hide'") !== false, true);

// Fields referenced only inside a nested group must also be bound; this is
// the browser-side consequence of the ::getAllFields() defect above.
$form9 = new StubForm();
$t9 = $form9->add('target', new StubField('t9'));
$form9->add('a', new StubField('a9', 'yes'));
$form9->add('n', new StubField('n9', 'deep'));
$nested = new VisibilityConstraint(new Q(array(
    'a__eq' => 'yes',
    new Q(array('n__eq' => 'deep')),
)));
ob_start();
$nested->emitJavascript($t9);
$js9 = ob_get_clean();
T::check('binds change on a field inside a nested group',
    strpos($js9, "\$('#n9').on('change'") !== false, true);
