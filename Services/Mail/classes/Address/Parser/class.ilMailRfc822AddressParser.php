<?php

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

declare(strict_types=1);

/**
 * Class ilMailRfc822AddressParser
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilMailRfc822AddressParser extends ilBaseMailRfc822AddressParser
{
    protected ilBaseMailRfc822AddressParser $aggregatedParser;

    public function __construct(ilBaseMailRfc822AddressParser $addresses)
    {
        parent::__construct($addresses->getAddresses());
        $this->aggregatedParser = $addresses;
    }

    protected function parseAddressString(string $addresses): array
    {
        return $this->aggregatedParser->parse();
    }
}
