<?php

declare(strict_types=1);

namespace Phpml\Math\Distance;

use Phpml\Exception\InvalidArgumentException;
use Phpml\Math\Distance;

class Jaccard implements Distance
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
        for ($i = 0; $i < $count; ++$i) {
            $aa += $a[$i]*$b[$i];
            $bb += $a[$i]*$a[$i] + $b[$i]* $b[$i] - $a[$i] * $b[$i];
        }
        if ($bb != 0) {
            return $aa/$bb;
        } else {
            return 0;
        }
        
    }
}
