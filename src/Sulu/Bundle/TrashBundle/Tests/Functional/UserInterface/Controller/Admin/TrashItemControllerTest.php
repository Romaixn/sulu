<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\TrashBundle\Tests\Functional\UserInterface\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\SecurityBundle\Entity\Permission;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Sulu\Bundle\TestBundle\Kernel\SuluKernelBrowser;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Bundle\TrashBundle\Tests\Functional\Traits\CreateTrashItemTrait;
use Sulu\Component\Security\Authorization\AccessControl\SecuredEntityInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;

class TrashItemControllerTest extends SuluTestCase
{
    use CreateTrashItemTrait;

    public const GRANTED_CONTEXT = 'sulu.context.granted';
    public const NOT_GRANTED_CONTEXT = 'sulu.context.not_granted';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TrashItemRepositoryInterface
     */
    private $repository;

    /**
     * @var SuluKernelBrowser
     */
    private $client;

    public function setUp(): void
    {
        /** @var SuluKernelBrowser $client */
        $client = $this->createAuthenticatedClient();

        $this->client = $client;

        static::purgeDatabase();

        $this->entityManager = static::getEntityManager();
        $this->repository = static::getTrashItemRepository();
    }

    public function testCgetAction(): void
    {
        $accessControlManager = static::getContainer()->get('sulu_security.access_control_manager');

        $role = static::setUpUserRole();

        $count = 0;
        foreach ([null, self::GRANTED_CONTEXT, self::NOT_GRANTED_CONTEXT] as $resourceSecurityContext) {
            foreach ([null, true, false] as $objectSecurity) {
                $resourceId = (string) ++$count;
                $resourceSecurityObjectType = null !== $objectSecurity ? SecuredEntityInterface::class : null;
                $resourceSecurityObjectId = null !== $objectSecurity ? $resourceId : null;

                static::createTrashItem(
                    'test_resource',
                    $resourceId,
                    [],
                    'Resource title',
                    $resourceSecurityContext,
                    $resourceSecurityObjectType,
                    $resourceSecurityObjectId
                );

                if (null !== $resourceSecurityObjectType && null !== $resourceSecurityObjectId) {
                    $accessControlManager->setPermissions(
                        $resourceSecurityObjectType,
                        $resourceSecurityObjectId,
                        [
                            $role->getId() => [
                                PermissionTypes::VIEW => $objectSecurity,
                                PermissionTypes::DELETE => $objectSecurity,
                            ],
                        ]
                    );
                }
            }
        }

        $this->client->jsonRequest('GET', '/api/trash-items', ['limit' => 0]);
        $content = \json_decode((string) $this->client->getResponse()->getContent());

        self::assertSame(4, $content->total);
    }

    public function testGetAction(): void
    {
        $trashItem = static::createTrashItem();
        $id = $trashItem->getId();

        $this->client->jsonRequest('GET', '/api/trash-items/' . $id);
        static::assertHttpStatusCode(200, $this->client->getResponse());
        $content = \json_decode((string) $this->client->getResponse()->getContent());

        static::assertSame($id, $content->id);
    }

    public function testDeleteAction(): void
    {
        $trashItem = static::createTrashItem();
        $id = $trashItem->getId();

        $this->client->jsonRequest('DELETE', '/api/trash-items/' . $id);
        static::assertHttpStatusCode(204, $this->client->getResponse());

        $this->client->jsonRequest('GET', '/api/trash-items/' . $id);
        static::assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testDeleteActionNotExisting(): void
    {
        $this->client->jsonRequest('DELETE', '/api/trash-items/12345');
        static::assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPostTriggerActionRestore(): void
    {
        $trashItem = static::createTrashItem();
        $id = $trashItem->getId();

        $this->client->jsonRequest('POST', '/api/trash-items/' . $id, ['action' => 'restore']);
        static::assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->jsonRequest('GET', '/api/trash-items/' . $id);
        static::assertHttpStatusCode(404, $this->client->getResponse());
    }

    private static function setUpUserRole(): Role
    {
        $entityManager = static::getEntityManager();
        $testUser = static::getTestUser();

        $role = new Role();
        $role->setAnonymous(false);
        $role->setKey('test');
        $role->setName('Test');
        $role->setSystem(Admin::SULU_ADMIN_SECURITY_SYSTEM);

        $entityManager->persist($role);

        $grantedPermission = new Permission();
        $grantedPermission->setContext(static::GRANTED_CONTEXT);
        $grantedPermission->setPermissions(127);
        $grantedPermission->setRole($role);

        $entityManager->persist($grantedPermission);

        $role->addPermission($grantedPermission);

        $notGrantedPermission = new Permission();
        $notGrantedPermission->setContext(static::NOT_GRANTED_CONTEXT);
        $notGrantedPermission->setPermissions(0);
        $notGrantedPermission->setRole($role);

        $entityManager->persist($notGrantedPermission);

        $role->addPermission($notGrantedPermission);

        $userRole = new UserRole();
        $userRole->setRole($role);
        $userRole->setLocale('["en"]');
        $userRole->setUser($testUser);
        $entityManager->persist($userRole);

        $testUser->addUserRole($userRole);

        $entityManager->flush();

        return $role;
    }

    protected static function getTrashItemRepository(): TrashItemRepositoryInterface
    {
        return static::getContainer()->get('sulu_trash.trash_item_repository');
    }
}
