<?php

namespace Kaliop\IdentityManagementBundle\Adapter\LDAP;

use Kaliop\IdentityManagementBundle\Security\User\RemoteUser as BaseRemoteUser;

/**
 * A 'generic' LDAP Remote user class.
 * Since this is not a service, we allow all config to be set in the code creating instances of this (i.e. the Client)
 */
class RemoteUser extends BaseRemoteUser
{
    protected $emailField;

    /**
     * @param array $authUserResult (nested array)
     * @param string $emailField the name of the ldap attribute which holds the user email address
     * @param string $login
     * @param string $password
     *
     * @todo decide what to store of $AuthUserResult, so that it can be serialized without taking up too much space
     *       (otoh maybe this never gets serialized, and only the eZ-mvc-user does?
     *       Note that the list of attributes gotten from ladp is decided by settings for the client class...
     * @todo store the password salted and encrypted in memory instead of plaintext
     */
    public function __construct($authUserResult, $emailField, $login, $password='')
    {
        $this->username = $login;
        $this->password = $password;
        $this->emailField = $emailField;
        $this->profile = $this->ldap2array($authUserResult);
    }

    /**
     * SF roles. Important: not to have this empty, otherwise SF will think this user is not an authenticated one
     * @return array
     */
    public function getRoles()
    {
        return array('ROLE_USER');
    }

    /**
     * @todo throw if unset ?
     * @return string
     */
    public function getEmail()
    {
        return $this->profile[$this->emailField];
    }

    /**
     * Add typehint :-)
     * @return array
     */
    public function getProfile()
    {
        return parent::getProfile();
    }

    /**
     * Transforms the data received from an LDAP query into a more 'normal' php array by removing redundant stuff.
     * NB: assumes a well-formed array
     *
     * @param array $data
     * @return array
     *
     * @todo return a stdclass object instead ?
     */
    protected function ldap2array($data) {
        //return $data;
        foreach($data as $key => $value) {
            if ($key === 'dn') {
                continue;
            }
            if (is_int($key) || $key === 'count') {
                unset($data[$key]);
                continue;
            }
            if ($value['count'] === 1) {
                $data[$key] = $value[0];
                continue;
            }
            if ($value['count'] > 1) {
                unset($data[$key]['count']);
                continue;
            }
        }
        return $data;
    }
}
