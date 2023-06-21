<?php declare(strict_types=1);
function compare($priority1, $priority2) : int
{
    if ($priority1 === $priority2) return 0;

    return $priority1 > $priority2 ? -1 : 1;
}

$arr = [
 "5" => "5",
 "6" => "6",
 "12" => "12",
 "1" => "1",
 "3" => "3"
];

$a = $arr;
uksort($a, 'compare');
print_r($a);

$c = $arr;
krsort($c);
print_r($c);

$a = $arr;
$b = $arr;

foreach($a as $akey => $avalue) {
    foreach($b as $bkey => $bvalue) {
        echo compare($akey, $bkey) . "," . ($bkey <=> $akey) . PHP_EOL;
    }
}

