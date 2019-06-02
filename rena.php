<?php
class Rena {
    private $_ignore;

    function __construct($ignore = null) {
        if($ignore != null) {
            $this->_ignore = $this->wrap($ignore);
        }
    }

    private function ignore($match, $lastIndex) {
        if(!$this->_ignore) {
            return $lastIndex;
        }
        $ignoreCall = $this->_ignore;
        $result = $ignoreCall($match, $lastIndex, false);
        return $result ? $result['lastIndex'] : $lastIndex;
    }

    function wrap($object) {
        if(is_string($object)) {
            return function($match, $lastIndex, $attr) use ($object) {
                if($lastIndex + mb_strlen($object) > mb_strlen($match)) {
                    return false;
                }
                $part = mb_substr($match, $lastIndex, mb_strlen($object));
                if($part == $object) {
                    return array('match' => $object, 'lastIndex' => $lastIndex + mb_strlen($object), 'attr' => $attr);
                } else {
                    return false;
                }
            };
        } else {
            return $object;
        }
    }

    function then(...$funcs) {
        return function($match, $lastIndex, $attr) use (&$funcs) {
            $indexNew = $lastIndex;
            $attrNew = $attr;
            foreach($funcs as $func) {
                $wrapped = $this->wrap($func);
                $result = $wrapped($match, $indexNew, $attrNew);
                if(!$result) {
                    return false;
                } else {
                    $indexNew = $this->ignore($match, $result['lastIndex']);
                    $attrNew = $result['attrNew'];
                }
            }
            return array('match' => mb_substr($match, $lastIndex, $indexNew), 'lastIndex' => $indexNew, 'attr' => $attrNew);
        };
    }

    function choice(...$funcs) {
        return function($match, $lastIndex, $attr) use (&$funcs) {
            foreach($funcs as $func) {
                $wrapped = $this->wrap($func);
                $result = $wrapped($match, $indexNew, $attrNew);
                if($result) {
                    return $result;
                }
            }
            return false;
        };
    }

    function times($minCount, $maxCount, $exp, $action = null) {
        $wrapped = $this->wrap($exp);
        $wrappedAction = $action ? $action : function($match, $syn, $inh) { return $inh; };
        return function($match, $lastIndex, $attr) use ($minCount, $maxCount, &$wrapped, &$wrappedAction) {
            $indexNew = $lastIndex;
            $attrNew = $attr;
            $count = 0;
            for(; $maxCount == false || $count < $maxCount; $count++) {
                $result = $wrapped($match, $indexNew, $attrNew);
                if($result) {
                    $indexNew = $this->ignore($match, $result['lastIndex']);
                    $attrNew = $wrappedAction($result['match'], $result['attr'], $attrNew);
                } else if($count < $minCount) {
                    return false;
                } else {
                    $matched = mb_substr($match, $lastIndex, $indexNew);
                    return array('match' => $matched, 'lastIndex' => $indexNew, 'attr' => $attrNew);
                }
            }
            $matched = mb_substr($match, $lastIndex, $indexNew);
            return array('match' => $matched, 'lastIndex' => $indexNew, 'attr' => $attrNew);
        };
    }

    function atLeast($minCount, $exp, $action = null) {
        return $this->times($minCount, false, $exp, $action);
    }

    function atMost($maxCount, $exp, $action = null) {
        return $this->times(0, $maxCount, $exp, $action);
    }

    function oneOrMore($exp, $action = null) {
        return $this->times(1, false, $exp, $action);
    }

    function zeroOrMore($exp, $action = null) {
        return $this->times(0, false, $exp, $action);
    }

    function maybe($exp) {
        return $this->times(0, 1, $exp);
    }

    function lookahead($exp, $singum = true) {
        $wrapped = $this->wrap($exp);
        return function($match, $lastIndex, $attr) use (&$wrapped) {
            $result = $wrapped($match, $lastIndex, $attr);
            if(($result && $signum) || (!$result && !$signum)) {
                return array('match' => '', 'lastIndex' => $lastIndex, 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function lookaheadNot($exp) {
        return $this->lookahead($exp, false);
    }

    function attr($attr) {
        return function($match, $lastIndex, $attrOld) use (&$attr) {
            return array('match' => '', 'lastIndex' => $lastIndex, 'attr' => $attr);
        };
    }

    function cond($pred) {
        return function($match, $lastIndex, $attr) use (&$pred) {
            if($pred($attr)) {
                return array('match' => '', 'lastIndex' => $lastIndex, 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function action($exp, $action) {
        $wrapped = $this->wrap($exp);
        return function($match, $lastIndex, $attr) use (&$wrapped, &$action) {
            $result = $wrapped($match, $lastIndex, $attr);
            if($result) {
                $attrNew = $action($result['match'], $result['attr'], $attr);
                return array('match' => $result['match'], 'lastIndex' => $result['lastIndex'], 'attr' => $attrNew);
            } else {
                return false;
            }
        };
    }

    function letrec(...$funcs) {
        $f = function($g) {
            return $g($g);
        };
        $h = function($p) use(&$funcs) {
            $res = array();
            foreach($funcs as $func) {
                $res[] = function($match, $lastIndex, $attr) use (&$p, &$func) {
                    $pp = $p($p);
                    $pfunc = $this->wrap(call_user_func_array($func, $pp));
                    return $pfunc($match, $lastIndex, $attr);
                };
            }
            return $res;
        };
        return ($f($h))[0];
    }
}
?>
