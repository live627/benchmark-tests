<?php
function preg_match_entire( string $pattern, string $subject, int $flags = 0 ){
  // Rebuild and wrap the pattern
  $delimiter = $pattern[0];
  $ldp       = strrpos( $pattern, $delimiter );
  $pattern   = substr( $pattern, 1, $ldp - 1 );
  $modifiers = substr( $pattern,    $ldp + 1 );
  $pattern   = "{$delimiter}\G\z(*MARK:END)\G|(?:{$pattern})   {$delimiter}x{$modifiers}";
  $r = preg_match_all( $pattern, $subject, $m, $flags );
  var_dump($m);
  if( $r === false )  return false;  // error
  $end = array_pop( $m );
  if( $end === null || ! isset( $end['MARK']) || $end['MARK'] !== 'END')
    return null;  // end of string not reached
  return $m;  // return actual matches, may be an empty array
}

// Same results:
test('#{\d+}#', '');              // []
test('#{\d+}#', '{11}{22}{33}');  // {11},{22},{33}

// Different results: preg_match_entire won't match this:
test('#{\d+}#', '{11}{}{aa}{22},{{33}}');
// preg_match_entire: null
// preg_match_all:    {11},{22},{33}

function test( $pattern, $subject ){
  echo "pattern:           $pattern\n";
  echo "subject:           $subject\n";
  var_dump('preg_match_entire: ', preg_match_entire( $pattern, $subject ));
  preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER );
  print_matches('preg_match_all:    ', $matches );
  echo "\n";
}
function print_matches( $t, $m ){
  echo $t, is_array( $m ) && $m ? implode(',', array_column( $m, 0 )) : json_encode( $m ), "\n";
} ?>