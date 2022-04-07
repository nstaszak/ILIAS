<?php declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

namespace ILIAS\Refinery\Integer;

use ILIAS\Data;
use ILIAS\Refinery\Custom\Constraint as CustomConstraint;
use ilLanguage;

class LessThan extends CustomConstraint
{
    protected int $max;

    public function __construct(int $max, Data\Factory $data_factory, ilLanguage $lng)
    {
        $this->max = $max;
        parent::__construct(
            function ($value) : bool {
                return $value < $this->max;
            },
            function ($txt, $value) : string {
                return (string) $txt("not_less_than", $value, $this->max);
            },
            $data_factory,
            $lng
        );
    }
}
