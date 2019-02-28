<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\PageBundle\Tests\Unit\Content\Types;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\PageBundle\Content\Types\SingleInternalLink;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Content\Compat\PropertyInterface;

class SingleInternalLinkTest extends TestCase
{
    /**
     * @var PropertyInterface
     */
    private $property;

    /**
     * @var ReferenceStoreInterface
     */
    private $referenceStore;

    /**
     * @var SingleInternalLink
     */
    private $type;

    public function setUp()
    {
        parent::setUp();

        $this->property = $this->prophesize(PropertyInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);

        $this->type = new SingleInternalLink(
            $this->referenceStore->reveal(),
            'some_template.html.twig'
        );
    }

    public function providePreResolve()
    {
        return [
            [
                '4234-2345-2345-3245',
                ['4234-2345-2345-3245'],
            ],
            [
                null,
                [],
            ],
            [
                '',
                [],
            ],
        ];
    }

    /**
     * @dataProvider providePreResolve
     */
    public function testPreResolve($propertyValue, $expected)
    {
        $this->property->getValue()->willReturn($propertyValue);
        $this->type->preResolve($this->property->reveal());

        if (0 === count($expected)) {
            $this->referenceStore->add(Argument::any())->shouldNotBeCalled();
        }

        foreach ($expected as $uuid) {
            $this->referenceStore->add($uuid)->shouldBeCalled();
        }
    }
}
