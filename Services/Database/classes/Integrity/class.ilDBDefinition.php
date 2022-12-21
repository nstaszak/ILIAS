<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

namespace ILIAS\Services\Database\Integrity;

use InvalidArgumentException;

class ilDBDefinition
{
    /**
     * @var ilDBAssociation[]
     */
    private array $associations;
    private ilDBIgnore $ignore;

    public function __construct(array $associations, ?ilDBIgnore $ignore = null)
    {
        $this->associations = $associations;
        $this->ignore = null === $ignore ? new ilDBIgnore() : $ignore;
        $this->validate();
    }

    private function validate() : void
    {
        if (count($this->associations) === 0) {
            throw new InvalidArgumentException('Associations must not be empty.');
        }

        $first = $this->associations[0];

        foreach ($this->associations as $association) {
            if ($association->field()->tableName() !== $first->field()->tableName() ||
                $association->referenceField()->tableName() !== $first->referenceField()->tableName()
            ) {
                throw new InvalidArgumentException('All fields must have the same table');
            }
        }
    }

    public function tableName() : string
    {
        return $this->associations[0]->field()->tableName();
    }

    /**
     * @return ilDBAssociation[]
     */
    public function associations() : array
    {
        return $this->associations;
    }

    /**
     * @return string[]
     */
    public function ignoreValues() : array
    {
        return $this->ignore->values();
    }

    public function referenceTableName() : string
    {
        return $this->associations[0]->referenceField()->tableName();
    }
}