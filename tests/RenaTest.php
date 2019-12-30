<?php
/*
 * This source code is under the Unlicense
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

    public function test_concat() {
        $r = new Morilib\Rena();
        $this->match($r->concat('765', 'pro'), '765pro', '765pro', 6, 0);
        $this->nomatch($r->concat('765', 'pro'), '961pro');
        $this->nomatch($r->concat('765', 'pro'), '765aaa');
        $this->nomatch($r->concat('765', 'pro'), '765');
        $this->nomatch($r->concat('765', 'pro'), '');
    }

    public function test_concat_ignore() {
        $r = new Morilib\Rena(' ');
        $this->match($r->concat('765', 'pro'), '765 pro', '765 pro', 7, 0);
        $this->match($r->concat('765', 'pro'), '765 pro ', '765 pro ', 8, 0);
    }

    public function test_choice() {
        $r = new Morilib\Rena();
        $this->match($r->choice('765', '346'), '765', '765', 3, 0);
        $this->match($r->choice('765', '346'), '346', '346', 3, 0);
        $this->nomatch($r->choice('765', '346'), '961');
        $this->nomatch($r->choice('765', '346'), '');
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

    public function test_zeroOrMoreIgnore() {
        $r = new Morilib\Rena(' ');
        $this->match($r->zeroOrMore('a'), 'a a a', 'a a a', 5, 0);
        $this->match($r->zeroOrMore('a'), 'aaa', 'aaa', 3, 0);
    }

    public function test_opt() {
        $r = new Morilib\Rena();
        $this->match($r->opt('a'), 'a', 'a', 1, 0);
        $this->match($r->opt('a'), 'aa', 'a', 1, 0);
        $this->match($r->opt('a'), '', '', 0, 0);
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

    public function test_isEnd() {
        $r = new Morilib\Rena();
        $this->match($r->isEnd(), '', '', 0, 0);
        $this->nomatch($r->isEnd(), '961');
    }

    public function test_letrec() {
        $r = new Morilib\Rena();
        $e = $r->letrec(function($x) use (&$r) {
            return $r->concat('(', $r->opt($x), ')');
        });
        $this->match($e, '((()))', '((()))', 6, 0);
        $this->match($e, '(()))', '(())', 4, 0);
        $this->nomatch($e, '((())');
    }
}
?>

