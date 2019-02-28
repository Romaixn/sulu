<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AdminBundle\ResourceMetadata;

interface ResourceMetadataProviderInterface
{
    /**
     * @return ResourceMetadataInterface[]
     */
    public function getAllResourceMetadata(string $locale): array;

    public function getResourceMetadata(string $resourceKey, string $locale): ?ResourceMetadataInterface;
}
