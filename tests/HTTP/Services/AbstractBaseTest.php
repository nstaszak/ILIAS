<?php

namespace ILIAS\HTTP;

/** @noRector */

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/
/**
 * Class AbstractBaseTest
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
abstract class AbstractBaseTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RequestInterface
     */
    protected $request_interface;

    /**
     * @inheritDoc
     */
    protected function setUp() : void
    {
        parent::setUp();
        $this->request_interface = $this->createMock(ServerRequestInterface::class);
    }
}
