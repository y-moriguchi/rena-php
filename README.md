# Rena PHP
Rena PHP is a library of parsing texts. Rena PHP makes parsing text easily.  
Rena PHP can treat recursion of pattern, hence Rena PHP can parse languages which described top down parsing
like arithmetic expressions and so on.  
Rena PHP can parse class of Parsing Expression Grammar (PEG) language.  
Rena PHP can also treat synthesized and inherited attributes.  
'Rena' is an acronym of REpetation (or REcursion) Notation API.  

## Expression

### Construct Expression Generation Object
```php
$r = new Morilib\Rena(ignore, keys);
```

First argument is an expression to ignore, and second argument is keys (e.g. operators).  
The arguments are optional.

An example which generates object show as follows.
```php
$r0 = new Morilib\Rena();
$r = new Morilib\Rena($r0->re("\\s+"), ["+", "-", "++"]);
```

### Elements of Expression

#### Literals
String literal of PHP is elements of expression.  
To use only one literal as expression, use concat synthesized expression.

#### Regular Expression
Expression to match regular expression is an element of expression.
```
$r->re("regex");
```

#### Attrinbute Setting Expression
Attribute setting expression is an element of expression.
```
$r->attr(attribute to set);
```

#### Key Matching Expression
Key matching expression is an element of expression.  
If keys "+", "++", "-" are specified by option, below expression matches "+" but does not match "+" after "+".
```
$r->key("+");
```

#### Not Key Matching Expression
Not key matching expression is an element of expression.
If keys "+", "++", "-" are specified by option, "+", "++", "-" will not match.
```
$r->notKey();
```

#### Keyword Matching Expression
Keyword matching expression is an element of expression.
```
$r->equalsId(keyword);
```

The table shows how to match expression r.equalsId("keyword") by option.

|option|keyword|keyword1|keyword-1|keyword+|
|:-----|:------|:-------|:--------|:-------|
|no options|match|match|match|match|
|ignore: /-/|match|no match|match|no match|
|keys: ["+"]|match|no match|no match|match|
|ignore: /-/ and keys: ["+"]|match|no match|match|match|

#### Real Number
Real number expression is an element of expression and matches any real number.
```
$r->matchReal();
```

#### Newline
Newline expression is an element of expression and matches CR/LF/CRLF newline.
```
$r->br();
```

#### End of string
End of string is an element of expression and matches the end of string.
```
$r->isEnd();
```

#### Function
Function which fulfilled condition shown as follow is an element of expression.  
* the function has 3 arguments
* first argument is a string to match
* second argument is last index of last match
* third argument is an attribute
* return value of the function is an object which has 3 properties
  * "match": matched string
  * "lastIndex": last index of matched string
  * "attr": result attribute

Every instance of expression is a function fulfilled above condition.

### Synthesized Expression

#### Sequence
Sequence expression matches if all specified expression are matched sequentially.  
Below expression matches "abc".
```
$r->concat("a", "b", "c");
```

#### Choice
Choice expression matches if one of specified expression are matched.  
Specified expression will be tried sequentially.  
Below expression matches "a", "b" or "c".
```
$r->choice("a", "b", "c");
```

#### Repetation
Repetation expression matches repetation of specified expression.  
The family of repetation expression are shown as follows.  
```
r.oneOrMore(expression);
r.zeroOrMore(expression);
r.opt(expression);
```

r.oneOrMore matches one or more times occurrence.
r.zeroOrMore matches zero or more times occurence.
r.opt match zero or one time occurrence.

Repetation expression is already greedy and does not backtrack.

#### Lookahead (AND predicate)
Lookahead (AND predicate) matches the specify expression but does not consume input string.
Below example matches "ab" but matched string is "a", and does not match "ad".
```
$r->concat("a", $r->lookahead("b"));
```

#### Nogative Lookahead (NOT predicate)
Negative lookahead (NOT predicate) matches if the specify expression does not match.
Below example matches "ab" but matched string is "a", and does not match "ad".
```
$r->concat("a", $r->lookaheadNot("d"));
```

#### Action
Action expression matches the specified expression.  
```
$r->action(expression, action);
```

The second argument must be a function with 3 arguments and return result attribute.  
First argument of the function will pass a matched string,
second argument will pass an attribute of repetation expression ("synthesized attribtue"),
and third argument will pass an inherited attribute.  

Below example, argument of action will be passed ("2", "2", "").
```php
$r->action($r->re("[0-9]"), function($match, $synthesized, $inherited) { return $match })("2", 0, "")
```

### Matching Expression
To apply string to match to an expression, call the expression with 3 arguments shown as follows.
1. a string to match
2. an index to begin to match
3. an initial attribute

```php
$match = $r->oneOrMore($r->re("[0-9]"), function($match, $synthesized, $inherited) { return inherited . ":" . synthesized });
$match("27", 0, "");
```

### Description of Recursion
The $r->letrec function is available to recurse an expression.  
The argument of r.letrec function are functions, and return value is the return value of first function.

Below example matches balanced parenthesis.
```php
$paren = $r->letrec(
  function($paren) use (&$r) {
    return $r->concat("(", $r->opt($paren), ")"));
  }
);
```

