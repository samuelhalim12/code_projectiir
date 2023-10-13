<?php

declare(strict_types=1);

namespace Phpml\Math\Distance;

use Phpml\Exception\InvalidArgumentException;
use Phpml\Math\Distance;

class Asymmetric implements Distance
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
        for ($i = 0; $i < $count; ++$i) {
            $aa += min($a[$i],$b[$i]);
        }
        if($aa == 0) {
            return 0;
        } else {
            return $aa/$aa;    
        }
    }
}
