<?php

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/
/**
 * Class CachedActiveRecord
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 */
abstract class CachedActiveRecord extends ActiveRecord
{
    private string $_hash = '';

    abstract public function getCache() : ilGlobalCache;

    final public function getCacheIdentifier() : string
    {
        if ($this->getArFieldList()->getPrimaryField()) {
            return ($this->getConnectorContainerName() . "_" . $this->getPrimaryFieldValue());
        }

        return "";
    }

    public function getTTL() : int
    {
        return 60;
    }

    /**
     * @inheritDoc
     */
    public function __construct($primary_key = 0, arConnector $connector = null)
    {
        if (is_null($connector)) {
            $connector = new arConnectorDB();
        }

        $connector = new arConnectorCache($connector);
        arConnectorMap::register($this, $connector);
        parent::__construct($primary_key);
    }

    public function afterObjectLoad() : void
    {
        parent::afterObjectLoad();
        $this->_hash = $this->buildHash();
    }

    private function buildHash() : string
    {
        $hashing = [];
        foreach ($this->getArFieldList()->getFields() as $field) {
            $name = $field->getName();
            $hashing[$name] = $this->{$name};
        }
        return md5(serialize($hashing));
    }

    public function create() : void
    {
        $this->getCache()->flush();
        parent::create();
    }

    /**
     * @inheritDoc
     */
    public function copy(int $new_id = 0) : \ActiveRecord
    {
        $this->getCache()->flush();
        return parent::copy($new_id);
    }

    public function read() : void
    {
        parent::read();
        $this->_hash = $this->buildHash();
    }

    public function update()
    {
        if ($this->buildHash() !== $this->_hash) {
            $this->getCache()->flush();
            parent::update();
        }
    }

    public function delete()
    {
        $this->getCache()->flush();
        parent::delete(); // TODO: Change the autogenerated stub
    }
}
