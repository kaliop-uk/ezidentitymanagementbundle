<?php

namespace Kaliop\IdentityManagementBundle\Security\User;

interface RemoteUserProviderInterface
{
    /**
     * Optionally creates the user, sets his permissions etc...
     *
     * @param RemoteUser $remoteUser
     * @return \eZ\Publish\API\Repository\Values\User\User|null null if the repo user will remain anon
     */
    public function loadAPIUserByRemoteUser(RemoteUser $remoteUser);
}