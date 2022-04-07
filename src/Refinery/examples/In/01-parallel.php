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

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
function parallel()
{
    global $DIC;

    $refinery = $DIC->refinery();

    $transformation = $refinery->in()->parallel(
        array(
            new ILIAS\Refinery\To\Transformation\IntegerTransformation(),
            new ILIAS\Refinery\To\Transformation\IntegerTransformation(),
        )
    );

    $result = $transformation->transform(5);

    return assert(array(5, 5), $result);
}
