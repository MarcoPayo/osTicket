<?php
/*********************************************************************
    stubs.forms.php

    Stand-ins for the parts of the forms API that the visibility engine
    touches. Shared by every test which exercises a constraint, so no test
    depends on another having been loaded first.

    VisibilityConstraint only ever asks a form for a field, and a field for
    its form, cleaned value, visibility and widget -- so that is all these
    need to provide.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

/**
 * Mirrors the constructor and accessors of the real Q in class.orm.php.
 * Reproduced rather than extracted because the ORM's Q implements
 * Serializable and pulls in the query compiler. If Q's flag semantics
 * change, this needs the same change.
 */
class Q {
    const NEGATED = 0x0001;
    const ANY     = 0x0002;

    var $constraints;
    var $negated = false;
    var $ored = false;

    function __construct($filter=array(), $flags=0) {
        if (!is_array($filter))
            $filter = array($filter);
        $this->constraints = $filter;
        $this->negated = $flags & self::NEGATED;
        $this->ored    = $flags & self::ANY;
    }

    function isNegated() { return $this->negated; }
    function isOred()    { return $this->ored; }
}

class StubWidget {
    public $id;

    function __construct($id) { $this->id = $id; }

    function getJsValueGetter($id='%s') {
        return sprintf('%s.val()', $id);
    }

    function getJsComparator($value, $id) {
        return sprintf('%s == %s',
            $this->getJsValueGetter($id), json_encode($value));
    }
}

class StubField {
    public $clean, $visible, $form, $widget;

    function __construct($id, $clean=null, $visible=true) {
        $this->widget  = new StubWidget($id);
        $this->clean   = $clean;
        $this->visible = $visible;
    }

    function getForm()   { return $this->form; }
    function getClean()  { return $this->clean; }
    function isVisible() { return $this->visible; }
    function getWidget() { return $this->widget; }
    function get($what, $default=null) { return $default; }
}

class StubForm {
    public $fields = array();

    function add($name, $field) {
        $this->fields[$name] = $field;
        $field->form = $this;
        return $field;
    }

    function getFields() {
        return $this->fields;
    }

    function getField($name) {
        return isset($this->fields[$name]) ? $this->fields[$name] : null;
    }
}
