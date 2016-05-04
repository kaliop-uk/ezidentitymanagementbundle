<?php

namespace Kaliop\IdentityManagementBundle\Security\User;

interface RemoteUserHandlerInterface
{
    /**
     * @param RemoteUser $user
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    public function createRepoUser(RemoteUser $user);

    /**
     * @param RemoteUser $user
     * @param $eZUser (is this an \eZ\Publish\API\Repository\Values\User\User ?)
     */
    public function updateRepoUser(RemoteUser $user, $eZUser);

    /**
     * Returns the API user corresponding to a given remoteUser (if it exists), or false.
     *
     * @param KaliopRemoteUser $remoteUser
     * @return \eZ\Publish\API\Repository\Values\User\User|false
     */
    public function loadAPIUserByRemoteUser(RemoteUser $remoteUser);
}
