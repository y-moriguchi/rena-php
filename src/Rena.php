<?php
/*
 * This source code is under the Unlicense
 */
namespace Morilib;

class Rena {
    private $_ignore;
    private $_trie;

    function __construct($ignore = null, $keys = null) {
        if($ignore != null) {
            $this->_ignore = $this->wrap($ignore);
        } else {
            $this->_ignore = null;
        }
        if($keys != null) {
            $this->_trie = array('trie' => array(), 'terminate' => false);
            foreach($keys as $key) {
                $trie = &$this->_trie;
                for($i = 0; $i < mb_strlen($key); $i++) {
                    $ch = mb_substr($key, $i, 1);
                    if(!$trie['trie'][$ch]) {
                        $trie['trie'][$ch] = array('trie' => array(), 'terminate' => false);
                    }
                    $trie = &$trie['trie'][$ch];
                }
                $trie['terminate'] = true;
            }
        } else {
            $this->_trie = null;
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

    private function matchKey($match, $index) {
        if(!$this->_trie) {
            return "";
        }
        $trie = &$this->_trie;
        $now = "";
        $result = "";
        for($i = $index; true; $i++) {
            if($i >= mb_strlen($match)) {
                return $result;
            }
            $ch = mb_substr($match, $i, 1);
            $now = $now . $ch;
            if($trie['trie'][$ch]) {
                $trie = &$trie['trie'][$ch];
                if($trie['terminate']) {
                    $result = $now;
                }
            } else {
                return $result;
            }
        }
    }

    private function convertToByte($match, $lastIndex) {
        $cut = mb_substr($match, 0, $lastIndex);
        return strlen($cut);
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

    function re($regex) {
        $firstCh = mb_substr($regex, 0, 1);
        $lastPos = mb_strlen($regex) - mb_strrpos($regex, $firstCh);
        $lastCh = mb_substr($regex, -$lastPos, $lastPos);
        $middle = mb_substr($regex, 1, mb_strlen($regex) - $lastPos - 1);
        $modifiedRe = $firstCh . '\\G(?:' . $middle . ')' . $lastCh;
        return function($match, $lastIndex, $attr) use ($modifiedRe) {
            $lastByte = $this->convertToByte($match, $lastIndex);
            $matches = array();
            $ret = preg_match($modifiedRe, $match, $matches, 0, $lastByte);
            if($ret === 1) {
                return array('match' => $matches[0], 'lastIndex' => $lastIndex + mb_strlen($matches[0]), 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function concat(...$funcs) {
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
            return array('match' => mb_substr($match, $lastIndex, $indexNew - $lastIndex), 'lastIndex' => $indexNew, 'attr' => $attrNew);
        };
    }

    function choice(...$funcs) {
        return function($match, $lastIndex, $attr) use (&$funcs) {
            foreach($funcs as $func) {
                $wrapped = $this->wrap($func);
                $result = $wrapped($match, $lastIndex, $attr);
                if($result) {
                    return $result;
                }
            }
            return false;
        };
    }

    private function times($minCount, $maxCount, $exp, $action = null) {
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
                    $matched = mb_substr($match, $lastIndex, $indexNew - $lastIndex);
                    return array('match' => $matched, 'lastIndex' => $indexNew, 'attr' => $attrNew);
                }
            }
            $matched = mb_substr($match, $lastIndex, $indexNew - $lastIndex);
            return array('match' => $matched, 'lastIndex' => $indexNew, 'attr' => $attrNew);
        };
    }

    function oneOrMore($exp) {
        return $this->times(1, false, $exp);
    }

    function zeroOrMore($exp) {
        return $this->times(0, false, $exp);
    }

    function opt($exp) {
        return $this->times(0, 1, $exp);
    }

    function lookahead($exp, $signum = true) {
        $wrapped = $this->wrap($exp);
        return function($match, $lastIndex, $attr) use (&$wrapped, $signum) {
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

    function key($key) {
        return function($match, $lastIndex, $attr) use (&$key) {
            $result = $this->matchKey($match, $lastIndex);
            if($result == $key) {
                return array('match' => $result, 'lastIndex' => $lastIndex + mb_strlen($result), 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function notKey() {
        return function($match, $lastIndex, $attr) use (&$key) {
            $result = $this->matchKey($match, $lastIndex);
            if($result == "") {
                return array('match' => "", 'lastIndex' => $lastIndex, 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function equalsId($key) {
        $wrapped = $this->wrap($key);
        return function($match, $lastIndex, $attr) use (&$wrapped) {
            $result = $wrapped($match, $lastIndex, $attr);
            if(!$result) {
                return false;
            } else if($result['lastIndex'] >= mb_strlen($match)) {
                return $result;
            } else if($this->_ignore == null && $this->_trie == null) {
                return $result;
            } else if($this->_ignore && $this->ignore($match, $result['lastIndex']) > $result['lastIndex']) {
                $result['lastIndex'] = $this->ignore($match, $result['lastIndex']);
                return $result;
            } else if($this->_trie && $this->matchKey($match, $result['lastIndex']) != "") {
                return $result;
            } else {
                return false;
            }
        };
    }

    function matchReal() {
        return $this->action($this->re('/[\\+\\-]?(?:[0-9]+(?:\\.[0-9]+)?|\\.[0-9]+)(?:[eE][\\+\\-]?[0-9]+)?/'), function($match, $syn, $inh) {
            return (double)$match;
        });
    }

    function br() {
        return $this->re('/\r\n|\r|\n/');
    }

    function isEnd() {
        return function($match, $lastIndex, $attr) {
            if($lastIndex >= mb_strlen($match)) {
                return array('match' => '', 'lastIndex' => $lastIndex, 'attr' => $attr);
            } else {
                return false;
            }
        };
    }

    function letrec(...$funcs) {
        $delays = array();
        $memo = array();
        for($i = 0, $size = count($funcs); $i < $size; $i++) {
            (function($i) use(&$delays, &$funcs, &$memo) {
                $delays[] = function($match, $lastIndex, $attr) use(&$funcs, &$memo, &$delays, &$i) {
                    if(!$memo[$i]) {
                        $memo[$i] = call_user_func_array($funcs[$i], $delays);
                    }
                    return $memo[$i]($match, $lastIndex, $attr);
                };
            })($i);
        }
        return $delays[0];
    }
}
?>
