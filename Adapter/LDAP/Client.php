<?php

namespace Kaliop\IdentityManagementBundle\Adapter\LDAP;

use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\LdapClientInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Kaliop\IdentityManagementBundle\Adapter\ClientInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;

/**
 * A 'generic' LDAP Client, driven by configuration.
 * It should suffice for most cases.
 * It relies on the Symfony LDAP Component.
 */
class Client implements ClientInterface
{
    protected $ldap;
    protected $logger;
    protected $settings;

    /**
     * @param LdapClientInterface|LdapClientInterface[] $ldap
     * @param array $settings
     *
     * @todo document the settings
     */
    public function __construct($ldap, array $settings)
    {
        $this->ldap = $ldap;
        $this->settings = $settings;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $username
     * @param string $password
     * @return RemoteUser
     * @throws BadCredentialsException|AuthenticationServiceException
     */
    public function authenticateUser($username, $password)
    {
        if ($this->logger) $this->logger->info("Looking up remote user: '$username'");

        $ldaps = is_array($this->ldap) ? array_values($this->ldap) : array($this->ldap);
        $i = 0;

        while (true) {

            $ldap = $ldaps[$i];
            $i++;

            try {
                $ldap->bind($this->settings['search_dn'], $this->settings['search_password']);
                $username = $ldap->escape($username, '', LDAP_ESCAPE_FILTER);
                $query = str_replace('{username}', $username, $this->settings['filter']);
                if (isset($this->settings['attributes']) && count($this->settings['attributes'])) {
                    $search = $ldap->find($this->settings['base_dn'], $query, $this->settings['attributes']);
                } else {
                    $search = $ldap->find($this->settings['base_dn'], $query);
                }

            } catch (ConnectionException $e) {
                if ($this->logger) $this->logger->error(sprintf('Connection error "%s"', $e->getMessage()));

                if ($i < count($ldaps)) {
                    if ($this->logger) $this->logger->error("Connecting to ldap server $i");
                    continue;
                }

                /// @todo shall we log an error ?
                throw new AuthenticationServiceException(sprintf('Connection error "%s"', $e->getMessage()), 0, $e);
            } catch (\Exception $e) {
                if ($this->logger) $this->logger->error(sprintf('Unexpected error "%s"', $e->getMessage()));

                throw new AuthenticationServiceException(sprintf('Internal error "%s"', $e->getMessage()), 0, $e);
            }

            if (!$search) {
                if ($this->logger) $this->logger->info("User not found");

                throw new BadCredentialsException(sprintf('User "%s" not found.', $username));
            }

            if ($search['count'] > 1) {
                if ($this->logger) $this->logger->warning('More than one ldap account found for ' . $username);

                throw new AuthenticationServiceException('More than one user found');
            }

            // always carry out this check, as the data is needed to log in
            if (!isset($this->settings['ldap_login_attribute']) || !isset($search[0][$this->settings['ldap_login_attribute']][0])) {
                if ($this->logger) $this->logger->info("Authentication failed for user: '$username', missing attribute used to log in to ldap: " . @$this->settings['ldap_login_attribute']);

                throw new AuthenticationServiceException('Invalid user profile: missing ldap attribute needed for log-in');
            }

            try {
                $this->validateLdapResults($search[0]);
            } catch (\Exception $e) {
                if ($this->logger) $this->logger->warning("Invalid user profile for user: '$username': ".$e->getMessage());

                throw new AuthenticationServiceException('Invalid user profile: '.$e->getMessage());
            }

            if ($this->logger) $this->logger->info("Remote user found, attempting authentication for user: '$username'");

            try {
                $ldap->bind($search[0][$this->settings['ldap_login_attribute']][0], $password);
            } catch (ConnectionException $e) {
                if ($this->logger) $this->logger->info("Authentication failed for user: '$username', bind failed: ".$e->getMessage());
                throw new BadCredentialsException('The presented password is invalid.');
            } catch (\Exception $e) {
                if ($this->logger) $this->logger->info("Authentication failed for user: '$username', unexpected ldap error: ".$e->getMessage());
                throw new AuthenticationServiceException('Unexpected exception: '.$e->getMessage());
            }

            if ($this->logger) $this->logger->info("Authentication succeeded for user: '$username'");

            // allow ldap to give us back the actual login field to be used in eZ. It might be different because of dashes, spaces, case...
            if (isset($this->settings['login_attribute']) && isset($search[0][$this->settings['login_attribute']][0])) {
                if ($username != $search[0][$this->settings['login_attribute']][0]) {
                    if ($this->logger) $this->logger->info("Renamed user '$username' to '{$search[0][$this->settings['login_attribute']][0]}'");

                    $username = $search[0][$this->settings['login_attribute']][0];
                }
            }

            return new RemoteUser($search[0], $this->settings['email_attribute'], $username, $password);
        }
    }

    /**
     * To be overridden in subclasses. Validates the ldap results so that later user creation/update shall not fail
     * @param array $data
     * @return null
     * @throw \Exception
     */
    protected function validateLdapResults(array $data)
    {
    }
}
