<?php
class Messy
{
    public function tangled($a, $b, $c)
    {
        $unused = 42;
        if ($a) {
            if ($b) {
                if ($c) {
                    while ($a > 0) {
                        $a--;
                        if ($a == 5) { return 1; }
                    }
                }
            }
        }
        return 0;
    }
    public function m1() {} public function m2() {} public function m3() {}
    public function m4() {} public function m5() {} public function m6() {}
}
