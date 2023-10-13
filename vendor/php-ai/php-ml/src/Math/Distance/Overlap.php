<?php

declare(strict_types=1);

namespace Phpml\Math\Distance;

use Phpml\Exception\InvalidArgumentException;
use Phpml\Math\Distance;

class Overlap implements Distance
{
    /**
     * @throws InvalidArgumentException
     */
    public function distance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Size of given arrays does not match');
        }

        $differences = [];
        $count = count($a);
        $aa = 0;
        $bb = 0;
        $pembilang = 0;
        for ($i = 0; $i < $count; ++$i) {
            $aa += pow($a[$i],2);
            $bb += pow($b[$i],2);
            $pembilang += $a[$i] * $b[$i];
        }
        if(min($aa,$bb) != 0){
            return $pembilang/min($aa,$bb);    
        } else {
            return 0;
        }
    }
}
