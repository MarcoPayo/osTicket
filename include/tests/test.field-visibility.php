<?php
/*********************************************************************
    test.field-visibility.php

    Unit tests for FieldVisibilityConstraint, which turns an admin
    configured, id-keyed definition into the name-keyed Q that
    VisibilityConstraint evaluates.

    Fixtures come from stubs.forms.php, loaded by run.php.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

$__forms = getenv('OSTICKET_FORMS_PHP')
    ?: dirname(__DIR__).'/class.forms.php';
load_class($__forms, 'VisibilityConstraint');
load_class($__forms, 'FieldVisibilityConstraint');

/**
 * StubField carries a name and an id the way a real dynamic field does,
 * since resolution maps one to the other.
 */
class IdentifiedField extends StubField {
    public $id, $name;

    function __construct($id, $name, $widgetId, $clean=null, $visible=true) {
        parent::__construct($widgetId, $clean, $visible);
        $this->id = $id;
        $this->name = $name;
    }

    function get($what, $default=null) {
        if ($what == 'id')   return $this->id;
        if ($what == 'name') return $this->name;
        return $default;
    }
}

function vis_form(array $spec) {
    $form = new StubForm();
    foreach ($spec as $name => $nfo) {
        list($id, $value) = $nfo;
        $form->add($name, new IdentifiedField($id, $name, $name.'_w', $value));
    }
    return $form;
}


T::suite('FieldVisibilityConstraint::normalize()');

$n = FieldVisibilityConstraint::normalize(array(
    'initial' => 'hidden',
    'match'   => 'all',
    'terms'   => array(array('field' => '24', 'op' => 'eq', 'value' => 'yes')),
));
T::check('coerces field id to int', $n['terms'][0]['field'], 24);
T::check('keeps recognised operator', $n['terms'][0]['op'], 'eq');
T::check('outer group carries initial', $n['initial'], 'hidden');

$n = FieldVisibilityConstraint::normalize(array(
    'match' => 'sideways',
    'terms' => array(array('field' => 1, 'op' => 'like', 'value' => 'x')),
));
T::check('unknown match falls back to all', $n['match'], 'all');
T::check('unknown operator falls back to eq', $n['terms'][0]['op'], 'eq');

T::check('accepts a JSON string',
    FieldVisibilityConstraint::normalize(
        '{"terms":[{"field":7,"op":"neq","value":"a"}]}')['terms'][0]['op'],
    'neq');

T::check('no usable terms yields null',
    FieldVisibilityConstraint::normalize(array('terms' => array())), null);
T::check('junk yields null',
    FieldVisibilityConstraint::normalize('not json at all'), null);
T::check('term without a field id is dropped',
    FieldVisibilityConstraint::normalize(
        array('terms' => array(array('op' => 'eq', 'value' => 'x')))), null);

// A field conditioned on itself would recurse through isVisible() forever.
T::check('self reference is dropped',
    FieldVisibilityConstraint::normalize(
        array('terms' => array(array('field' => 5, 'value' => 'x'))), 5), null);
T::check('other fields survive alongside a self reference',
    count(FieldVisibilityConstraint::normalize(array('terms' => array(
        array('field' => 5, 'value' => 'x'),
        array('field' => 6, 'value' => 'y'),
    )), 5)['terms']), 1);

// Depth is bounded so a hand-built request cannot exhaust the stack.
$deep = array('terms' => array(array('field' => 1, 'value' => 'x')));
for ($i = 0; $i < 8; $i++)
    $deep = array('terms' => array($deep));
$nd = FieldVisibilityConstraint::normalize($deep);
T::check('over-deep nesting is rejected', $nd, null);

// Nested groups round-trip.
$nested = FieldVisibilityConstraint::normalize(array(
    'match' => 'all',
    'terms' => array(
        array('field' => 1, 'value' => 'a'),
        array('match' => 'any', 'terms' => array(
            array('field' => 2, 'value' => 'b'),
            array('field' => 3, 'value' => 'c'),
        )),
    ),
));
T::check('nested group preserved', count($nested['terms']), 2);
T::check('nested group keeps its match', $nested['terms'][1]['match'], 'any');
T::check('nested group carries no initial',
    isset($nested['terms'][1]['initial']), false);


T::suite('FieldVisibilityConstraint resolution');

// Terms reference field ids; the parent needs names. Resolution happens
// against the assembled form.
$form = vis_form(array(
    'target'  => array(10, null),
    'trigger' => array(11, 'yes'),
));
$c = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 11, 'op' => 'eq', 'value' => 'yes')),
));
T::check('id resolves to name and matches',
    $c->isVisible($form->getField('target')), true);

$form2 = vis_form(array(
    'target'  => array(10, null),
    'trigger' => array(11, 'no'),
));
$c2 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 11, 'op' => 'eq', 'value' => 'yes')),
));
T::check('unmatched value hides', $c2->isVisible($form2->getField('target')), false);

// A term naming a field which is no longer on the form is dropped, the same
// way the parent drops unresolvable names.
$form3 = vis_form(array('target' => array(10, null)));
$c3 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 99, 'op' => 'eq', 'value' => 'yes')),
));
T::nothrow('deleted field does not throw', function() use ($c3, $form3) {
    $c3->isVisible($form3->getField('target'));
});
// A rule whose every trigger has been deleted can no longer be evaluated.
// It must fail open: an inert rule is recoverable, a field which silently
// stops appearing (and so stops collecting data) is not.
T::check('rule with all triggers deleted falls open',
    $c3->isVisible($form3->getField('target')), true);

// ... but a rule which still has a live term is evaluated normally, even
// when a sibling term references something deleted.
$form3b = vis_form(array(
    'target' => array(10, null),
    'live'   => array(11, 'no'),
));
$c3b = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(
        array('field' => 99, 'op' => 'eq', 'value' => 'yes'),
        array('field' => 11, 'op' => 'eq', 'value' => 'yes'),
    ),
));
T::check('surviving term still decides', $c3b->isVisible($form3b->getField('target')), false);

// Renaming the trigger field must not break the rule, which is the whole
// reason terms store ids.
$renamed = vis_form(array(
    'target'         => array(10, null),
    'trigger_v2_new' => array(11, 'yes'),
));
$c4 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 11, 'op' => 'eq', 'value' => 'yes')),
));
T::check('rule survives the trigger field being renamed',
    $c4->isVisible($renamed->getField('target')), true);

// match=any glues with OR.
$form5 = vis_form(array(
    'target' => array(10, null),
    'a'      => array(11, 'no'),
    'b'      => array(12, 'yes'),
));
$c5 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'match' => 'any',
    'terms' => array(
        array('field' => 11, 'op' => 'eq', 'value' => 'yes'),
        array('field' => 12, 'op' => 'eq', 'value' => 'yes'),
    ),
));
T::check('match=any satisfied by either term',
    $c5->isVisible($form5->getField('target')), true);

// Nested group evaluation.
$form6 = vis_form(array(
    'target' => array(10, null),
    'a'      => array(11, 'yes'),
    'b'      => array(12, 'no'),
    'c'      => array(13, 'maybe'),
));
$c6 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(
        array('field' => 11, 'op' => 'eq', 'value' => 'yes'),
        array('match' => 'any', 'terms' => array(
            array('field' => 12, 'op' => 'eq', 'value' => 'yes'),
            array('field' => 13, 'op' => 'eq', 'value' => 'maybe'),
        )),
    ),
));
T::check('nested OR inside an AND evaluates',
    $c6->isVisible($form6->getField('target')), true);

// An empty definition falls back to the declared initial state.
$form7 = vis_form(array('target' => array(10, null)));
$empty = new FieldVisibilityConstraint(array('initial' => 'visible'));
T::check('empty definition honours initial=visible',
    $empty->isVisible($form7->getField('target')), true);
$empty2 = new FieldVisibilityConstraint(array('initial' => 'hidden'));
T::check('empty definition honours initial=hidden',
    $empty2->isVisible($form7->getField('target')), false);

// The engine dispatches on this base class, so the subclass has to satisfy
// the same instanceof check FormField::isVisible() makes.
T::check('is a VisibilityConstraint',
    $empty instanceof VisibilityConstraint, true);

// Definition round-trips for storage.
$def = array('initial' => 'hidden', 'match' => 'all', 'negated' => false,
    'terms' => array(array('field' => 11, 'op' => 'eq', 'value' => 'yes')));
T::check('toArray returns the stored definition',
    (new FieldVisibilityConstraint($def))->toArray(), $def);


T::suite('FieldVisibilityConstraint::emitJavascript()');

$form8 = vis_form(array(
    'target'  => array(10, null),
    'trigger' => array(11, 'yes'),
));
$c8 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 11, 'op' => 'eq', 'value' => 'yes')),
));
ob_start();
$c8->emitJavascript($form8->getField('target'));
$js = ob_get_clean();
T::check('binds change against the resolved widget',
    strpos($js, "\$('#trigger_w').on('change'") !== false, true);

// A rule whose fields have all been deleted must emit nothing rather than
// an empty if ().
$form9 = vis_form(array('target' => array(10, null)));
$c9 = new FieldVisibilityConstraint(array(
    'initial' => 'hidden',
    'terms' => array(array('field' => 99, 'op' => 'eq', 'value' => 'yes')),
));
ob_start();
$c9->emitJavascript($form9->getField('target'));
$js9 = ob_get_clean();
T::check('fully unresolvable rule emits nothing', trim($js9), '');


T::suite('FieldVisibilityConstraint::references()');

// The edge list that makes circular rules detectable.
T::check('collects a flat term',
    FieldVisibilityConstraint::references(
        array('terms' => array(array('field' => 7, 'value' => 'x')))),
    array(7));

$refs = FieldVisibilityConstraint::references(array('terms' => array(
    array('field' => 1, 'value' => 'a'),
    array('match' => 'any', 'terms' => array(
        array('field' => 2, 'value' => 'b'),
        array('match' => 'all', 'terms' => array(
            array('field' => 3, 'value' => 'c'),
        )),
    )),
)));
sort($refs);
T::check('descends into nested groups', $refs, array(1, 2, 3));

$dupes = FieldVisibilityConstraint::references(array('terms' => array(
    array('field' => 4, 'value' => 'a'),
    array('field' => 4, 'op' => 'neq', 'value' => 'b'),
)));
T::check('reports each field once', $dupes, array(4));

T::check('empty definition has no references',
    FieldVisibilityConstraint::references(array()), array());
T::check('junk has no references',
    FieldVisibilityConstraint::references('nonsense'), array());


T::suite('FieldVisibilityConstraint::findCycle()');

// Straight chain: no cycle.
T::check('acyclic chain reports nothing',
    FieldVisibilityConstraint::findCycle(
        array(1 => array(2), 2 => array(3), 3 => array()), 1), null);

// Two fields depending on each other.
T::check('mutual dependency detected',
    (bool) FieldVisibilityConstraint::findCycle(
        array(1 => array(2), 2 => array(1)), 1), true);

// Longer loop.
T::check('three-node loop detected',
    (bool) FieldVisibilityConstraint::findCycle(
        array(1 => array(2), 2 => array(3), 3 => array(1)), 1), true);

// A loop downstream still makes the starting field unevaluable, even though
// it does not close back on the start.
T::check('downstream loop detected from outside it',
    (bool) FieldVisibilityConstraint::findCycle(
        array(1 => array(2), 2 => array(3), 3 => array(2)), 1), true);

// A field pointing at itself.
T::check('self loop detected',
    FieldVisibilityConstraint::findCycle(array(1 => array(1)), 1), 1);

// Diamond: two paths to the same node is not a cycle.
T::check('diamond is not a cycle',
    FieldVisibilityConstraint::findCycle(
        array(1 => array(2, 3), 2 => array(4), 3 => array(4), 4 => array()), 1),
    null);

// Unrelated cycles elsewhere in the graph are not this field's problem.
T::check('unreachable cycle ignored',
    FieldVisibilityConstraint::findCycle(
        array(1 => array(2), 2 => array(), 8 => array(9), 9 => array(8)), 1),
    null);

// Nodes with no outgoing edges, and ids absent from the graph entirely.
T::check('missing node is acyclic',
    FieldVisibilityConstraint::findCycle(array(1 => array(42)), 1), null);
T::check('empty graph is acyclic',
    FieldVisibilityConstraint::findCycle(array(), 1), null);
