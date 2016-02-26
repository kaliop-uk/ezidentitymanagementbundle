<?php

namespace Kaliop\IdentityManagementBundle\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

abstract class RemoteUser implements UserInterface
{
    protected $username;
    protected $password;
    /**
     * Most likely to be set at creation time, holds the data coming from the remote system
     * @var mixed
     */
    protected $profile;

    abstract public function getRoles();

    /**
     * Called before the actual user object is created, with data gotten from the remote service
     *
     * @param $profile
     * @return null
     * @throws \Exception if the profile retrieved from the remote service is invalid, eg. misses mandatory data
     */
    abstract public static function validateProfile($profile);

    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * Returns the password used to authenticate the user.
     * @return string The password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }


    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
    }
}