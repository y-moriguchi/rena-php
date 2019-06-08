<?php
/*
 * Rena PHP
 *
 * Copyright (c) 2019 Yuichiro MORIGUCHI
 *
 * This software is released under the MIT License.
 * http://opensource.org/licenses/mit-license.php
 */
require_once('vendor/autoload.php');

class RenaTest extends PHPUnit\Framework\TestCase {

    private function match($exp, $tomatch, $matched, $index, $attr) {
        $result = $exp($tomatch, 0, 0);
        $this->assertEquals($matched, $result['match']);
        $this->assertEquals($index, $result['lastIndex']);
        $this->assertEquals($attr, $result['attr']);
    }

    private function matchIndex($exp, $tomatch, $start, $matched, $index, $attr) {
        $result = $exp($tomatch, $start, 0);
        $this->assertEquals($matched, $result['match']);
        $this->assertEquals($index, $result['lastIndex']);
        $this->assertEquals($attr, $result['attr']);
    }

    private function matchAttr($exp, $tomatch, $initAttr, $matched, $index, $attr) {
        $result = $exp($tomatch, 0, $initAttr);
        $this->assertEquals($matched, $result['match']);
        $this->assertEquals($index, $result['lastIndex']);
        $this->assertEquals($attr, $result['attr']);
    }

    private function nomatch($exp, $tomatch) {
        $result = $exp($tomatch, 0, 0);
        $this->assertFalse($result);
    }

    private function nomatchAttr($exp, $tomatch, $initAttr) {
        $result = $exp($tomatch, 0, $initAttr);
        $this->assertFalse($result);
    }

    public function test_wrap() {
        $r = new Morilib\Rena();
        $this->match($r->wrap('765'), '765', '765', 3, 0);
        $this->nomatch($r->wrap('765'), '961');
        $this->nomatch($r->wrap('765'), '96');
        $this->nomatch($r->wrap('765'), '');
    }

    public function test_re() {
        $r = new Morilib\Rena();
        $this->match($r->re('/[a-z]/'), 'a', 'a', 1, 0);
        $this->match($r->re('/[a-z]/i'), 'A', 'A', 1, 0);
        $this->matchIndex($r->re('/[a-z]/'), '0a', 1, 'a', 2, 0);
        $this->nomatch($r->re('/[a-z]/'), '0a');
        $this->nomatch($r->re('/[a-z]/'), '');
    }

    public function test_then() {
        $r = new Morilib\Rena();
        $this->match($r->then('765', 'pro'), '765pro', '765pro', 6, 0);
        $this->nomatch($r->then('765', 'pro'), '961pro');
        $this->nomatch($r->then('765', 'pro'), '765aaa');
        $this->nomatch($r->then('765', 'pro'), '765');
        $this->nomatch($r->then('765', 'pro'), '');
    }

    public function test_then_ignore() {
        $r = new Morilib\Rena(' ');
        $this->match($r->then('765', 'pro'), '765 pro', '765 pro', 7, 0);
        $this->match($r->then('765', 'pro'), '765 pro ', '765 pro ', 8, 0);
    }

    public function test_choice() {
        $r = new Morilib\Rena();
        $this->match($r->choice('765', '346'), '765', '765', 3, 0);
        $this->match($r->choice('765', '346'), '346', '346', 3, 0);
        $this->nomatch($r->choice('765', '346'), '961');
        $this->nomatch($r->choice('765', '346'), '');
    }

    public function test_times() {
        $r = new Morilib\Rena();
        $this->match($r->times(2, 4, 'a'), 'aa', 'aa', 2, 0);
        $this->match($r->times(2, 4, 'a'), 'aaa', 'aaa', 3, 0);
        $this->match($r->times(2, 4, 'a'), 'aaaa', 'aaaa', 4, 0);
        $this->match($r->times(2, 4, 'a'), 'aaaaa', 'aaaa', 4, 0);
        $this->match($r->times(2, false, 'a'), 'aaaaa', 'aaaaa', 5, 0);
        $this->nomatch($r->times(2, 4, 'a'), 'a');
        $this->nomatch($r->times(2, 4, 'a'), '');
    }

    public function test_times_ignore() {
        $r = new Morilib\Rena(' ');
        $this->match($r->times(2, 4, 'a'), 'a aa', 'a aa', 4, 0);
        $this->match($r->times(2, 4, 'a'), 'a a ', 'a a ', 4, 0);
    }

    public function test_times_action() {
        $r = new Morilib\Rena();
        $e0 = $r->action($r->re('/[1-9]/'), function($match, $syn, $inh) {
            return (int)$match;
        });
        $e = $r->times(2, 4, $e0, function($match, $syn, $inh) {
            return $syn + $inh;
        });
        $this->match($e, '765', '765', 3, 18);
        $this->match($e, '27', '27', 2, 9);
    }

    public function test_atLeast() {
        $r = new Morilib\Rena();
        $this->match($r->atLeast(2, 'a'), 'aa', 'aa', 2, 0);
        $this->match($r->atLeast(2, 'a'), 'aaaaa', 'aaaaa', 5, 0);
        $this->nomatch($r->atLeast(2, 'a'), 'a');
        $this->nomatch($r->atLeast(2, 'a'), '');
    }

    public function test_atMost() {
        $r = new Morilib\Rena();
        $this->match($r->atMost(4, 'a'), 'aa', 'aa', 2, 0);
        $this->match($r->atMost(4, 'a'), 'aaaa', 'aaaa', 4, 0);
        $this->match($r->atMost(4, 'a'), 'aaaaa', 'aaaa', 4, 0);
        $this->match($r->atMost(4, 'a'), '', '', 0, 0);
    }

    public function test_oneOrMore() {
        $r = new Morilib\Rena();
        $this->match($r->oneOrMore('a'), 'aa', 'aa', 2, 0);
        $this->match($r->oneOrMore('a'), 'aaaaa', 'aaaaa', 5, 0);
        $this->match($r->oneOrMore('a'), 'a', 'a', 1, 0);
        $this->nomatch($r->oneOrMore('a'), '');
    }

    public function test_zeroOrMore() {
        $r = new Morilib\Rena();
        $this->match($r->zeroOrMore('a'), 'aa', 'aa', 2, 0);
        $this->match($r->zeroOrMore('a'), 'aaaaa', 'aaaaa', 5, 0);
        $this->match($r->zeroOrMore('a'), 'a', 'a', 1, 0);
        $this->match($r->zeroOrMore('a'), '', '', 0, 0);
    }

    public function test_maybe() {
        $r = new Morilib\Rena();
        $this->match($r->maybe('a'), 'a', 'a', 1, 0);
        $this->match($r->maybe('a'), 'aa', 'a', 1, 0);
        $this->match($r->maybe('a'), '', '', 0, 0);
    }

    public function test_delimit() {
        $r = new Morilib\Rena();
        $this->match($r->delimit('a', ','), 'a,a', 'a,a', 3, 0);
        $this->match($r->delimit('a', ','), 'a', 'a', 1, 0);
        $this->match($r->delimit('a', ','), 'a,', 'a', 1, 0);
        $this->match($r->delimit('a', ','), 'a,b', 'a', 1, 0);
        $this->nomatch($r->delimit('a', ','), '');
    }

    public function test_delimit_ignore() {
        $r = new Morilib\Rena(' ');
        $this->match($r->delimit('a', ','), 'a , a', 'a , a', 5, 0);
        $this->match($r->delimit('a', ','), 'a ,a ', 'a ,a ', 5, 0);
        $this->match($r->delimit('a', ','), 'a , ', 'a ', 2, 0);
        $this->match($r->delimit('a', ','), 'a , b', 'a ', 2, 0);
        $this->match($r->delimit('a', ','), 'a ', 'a ', 2, 0);
    }

    public function test_delimit_action() {
        $r = new Morilib\Rena();
        $e = $r->delimit($r->matchReal(), ',', function($match, $syn, $inh) {
            return $syn + $inh;
        });
        $this->match($e, '765,346', '765,346', 7, 1111);
        $this->match($e, '3,4,6', '3,4,6', 5, 13);
        $this->match($e, '283', '283', 3, 283);
    }

    public function test_lookahead() {
        $r = new Morilib\Rena();
        $this->match($r->lookahead('765'), '765', '', 0, 0);
        $this->nomatch($r->lookahead('765'), '961');
        $this->nomatch($r->lookahead('765'), '');
    }

    public function test_lookaheadNot() {
        $r = new Morilib\Rena();
        $this->match($r->lookaheadNot('961'), '765', '', 0, 0);
        $this->match($r->lookaheadNot('961'), '', '', 0, 0);
        $this->nomatch($r->lookaheadNot('961'), '961');
    }

    public function test_attr() {
        $r = new Morilib\Rena();
        $this->match($r->attr(27), '', '', 0, 27);
    }

    public function test_cond() {
        $r = new Morilib\Rena();
        $this->matchAttr($r->cond(function($attr) { return $attr === 27; }), '', 27, '', 0, 27);
        $this->nomatchAttr($r->cond(function($attr) { return $attr === 27; }), '', 28);
    }

    public function test_action() {
        $r = new Morilib\Rena();
        $this->match($r->action('765', function($match, $syn, $inh) { return (double)$match; }), '765', '765', 3, 765);
        $this->matchAttr($r->action($r->matchReal(), function($match, $syn, $inh) { return $inh - $syn; }), '765', 1111, '765', 3, 346);
        $this->nomatch($r->action('765', function($match, $syn, $inh) { return (double)$match; }), '961');
    }

    public function test_key() {
        $r = new Morilib\Rena(null, ['+', '+++']);
        $this->match($r->key('+++'), '++++', '+++', 3, 0);
        $this->match($r->key('+'), '+', '+', 1, 0);
        $this->match($r->key('+'), '++', '+', 1, 0);
        $this->nomatch($r->key('+'), '+++');
    }

    public function test_notKey() {
        $r = new Morilib\Rena(null, ['+', '+++', '--']);
        $this->match($r->notKey(), '-', '', 0, 0);
        $this->nomatch($r->notKey(), '+');
        $this->nomatch($r->notKey(), '++');
        $this->nomatch($r->notKey(), '+++');
        $this->nomatch($r->notKey(), '--');
    }

    public function test_equalsId1() {
        $r = new Morilib\Rena();
        $this->match($r->equalsId('key'), 'key', 'key', 3, 0);
        $this->match($r->equalsId('key'), 'keys', 'key', 3, 0);
        $this->match($r->equalsId('key'), 'key 1', 'key', 3, 0);
        $this->match($r->equalsId('key'), 'key+', 'key', 3, 0);
        $this->match($r->equalsId('key'), 'key++', 'key', 3, 0);
    }

    public function test_equalsId2() {
        $r = new Morilib\Rena(' ');
        $this->match($r->equalsId('key'), 'key', 'key', 3, 0);
        $this->nomatch($r->equalsId('key'), 'keys');
        $this->match($r->equalsId('key'), 'key 1', 'key', 4, 0);
        $this->nomatch($r->equalsId('key'), 'key+');
        $this->nomatch($r->equalsId('key'), 'key++');
    }

    public function test_equalsId3() {
        $r = new Morilib\Rena(' ', ['++']);
        $this->match($r->equalsId('key'), 'key', 'key', 3, 0);
        $this->nomatch($r->equalsId('key'), 'keys');
        $this->match($r->equalsId('key'), 'key 1', 'key', 4, 0);
        $this->nomatch($r->equalsId('key'), 'key+');
        $this->match($r->equalsId('key'), 'key++', 'key', 3, 0);
    }

    private function assertReal($tomatch, $expect) {
        $r = new Morilib\Rena();
        $result = $r->matchReal()($tomatch, 0, 0);
        if($expect !== false) {
            $this->assertEquals($expect, $result['attr']);
        } else {
            $this->assertFalse($result);
        }
    }

    public function test_matchReal() {
        $this->assertReal("765", 765);
        $this->assertReal("76.5", 76.5);
        $this->assertReal("0.765", 0.765);
        $this->assertReal(".765", 0.765);
        $this->assertReal("765e2", 76500);
        $this->assertReal("765E2", 76500);
        $this->assertReal("765e+2", 76500);
        $this->assertReal("765e-2", 7.65);
        $this->assertReal("765e+346", INF);
        $this->assertReal("765e-346", 0);
        $this->assertReal("a961", false);
        $this->assertReal("+765", 765);
        $this->assertReal("+76.5", 76.5);
        $this->assertReal("+0.765", 0.765);
        $this->assertReal("+.765", 0.765);
        $this->assertReal("+765e2", 76500);
        $this->assertReal("+765E2", 76500);
        $this->assertReal("+765e+2", 76500);
        $this->assertReal("+765e-2", 7.65);
        $this->assertReal("+765e+346", INF);
        $this->assertReal("+765e-346", 0);
        $this->assertReal("+a961", false);
        $this->assertReal("-765", -765);
        $this->assertReal("-76.5", -76.5);
        $this->assertReal("-0.765", -0.765);
        $this->assertReal("-.765", -0.765);
        $this->assertReal("-765e2", -76500);
        $this->assertReal("-765E2", -76500);
        $this->assertReal("-765e+2", -76500);
        $this->assertReal("-765e-2", -7.65);
        $this->assertReal("-765e+346", -INF);
        $this->assertReal("-765e-346", 0);
        $this->assertReal("-a961", false);
    }

    public function test_br() {
        $r = new Morilib\Rena();
        $this->match($r->br(), "\r\n", "\r\n", 2, 0);
        $this->match($r->br(), "\r", "\r", 1, 0);
        $this->match($r->br(), "\n", "\n", 1, 0);
    }

    public function test_end() {
        $r = new Morilib\Rena();
        $this->match($r->end(), '', '', 0, 0);
        $this->nomatch($r->end(), '961');
    }

    public function test_letrec() {
        $r = new Morilib\Rena();
        $e = $r->letrec(function($x) use (&$r) {
            return $r->then('(', $r->maybe($x), ')');
        });
        $this->match($e, '((()))', '((()))', 6, 0);
        $this->match($e, '(()))', '(())', 4, 0);
        $this->nomatch($e, '((())');
    }
}
?>

