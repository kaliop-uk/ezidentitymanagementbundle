Kaliop Identity Management Bundle
=================================

An eZ5 bundle designed to cater all your needs for custom user authentication scenarios:
- log in user by his IP
- log in user using his email instead of login
- get user accounts from an LDAP server (including MS Active Directory)
- get user accounts from an external service (needs custom code)
- allow logging in to the eZ backoffice using the customized symfony login handlers

The base idea is that it should be easy to swap/add remote user services without having to learn the intricate details of
the Symfony auth component (firewall/authenticator/userprovider/factory).

As such, the logic of the 'ldap login handler' from eZP4 is replicated:
1. when the user tries to log in the 1st time, retrieve his/her profile on the remote system, and create a corresponding eZ user on the fly
2. when the user tries to log in after the 1st time, retrieve his/her profile on the remote system, and update the corresponding eZ user if needed

Some nice bits are still missing, but the bundle should be sufficient to get started with simple LDAP integrations.

Contributions are welcome :-)


## Allow Log In By Email

- This happens without the need to save the user email in the 'login field'

- To activate it: enable the following parameters in parameters.yml:

        parameters:
            # take over the default user provider - to log him in other ways than login field
            ezpublish.security.user_provider.class: Kaliop\IdentityManagementBundle\Security\User\Provider\EmailUser
            # take over the auth provider as well, in accord
            security.authentication.provider.dao.class: Kaliop\IdentityManagementBundle\Security\Authentication\Provider\RepositoryAuthenticationProvider


## Allow Log In By IP

- This is implemented via a custom firewall named *ip_login* in the *firewalls* section of security.yml.
    The firewall depends on a separate service for the mapping IP => user account name

- To activate it: ...


## Log In By Remote Services (LDAP/Active Directory or other)

- Support for LDAP is built-in, and needs some config and minimal php code

- For other custom external services you wll need to write more php code

- This is implemented via a custom firewall named *remoteuser_login* in the *firewalls* section of security.yml

- The firewall depends on two additional services for:
    * communicating to the remote webservice
    * creating an instance of (a subclass of) Kaliop\IdentityManagementBundle\Security\User\RemoteUser when user logs in
    * mapping that instance into eZPubish users (creating/updating them on the fly at login time)

### Getting started: integrating an LDAP directory

1. configure the connection to the ldap server, eg:

        services:
            # The ldap client config
            my.ldap:
                class: Symfony\Component\Ldap\LdapClient
                arguments:
                    - ldap.server.com
                    - 636
                    - 3
                    - true

2. configure the retrieval of user account information from the ldap server, eg:

        # The service used to communicate with the LDAP server
        my.ldap_auth.client:
            class: Kaliop\IdentityManagementBundle\Adapter\LDAP\Client
            arguments:
                # NB: here you can pass in either one ldap client, or an array of clients, to achieve high-availability
                - "@my.ldap"
                -
                    # the credentials used to serach the ldap
                    search_dn: Lookup.Service@domain.com
                    search_password: abcdefg
                    # the filter used to look up the user account
                    base_dn: dc=domain,dc=com,
                    filter: "(sAMAccountName={username})"
                    # The ldap attributes to retrieve to build the user profile.
                    # NB: by default, when the value of any of these changes, the ez user account is updated
                    attributes:
                        - displayname
                        - mail
                        - telephonenumber
                        - memberof
                        - thumbnailphoto
                        - title
                    # The name of the ldap attribute used to hold the user email
                    email_attribute: mail
                    # The name of attribute used to log-in to ldap and validate the password
                    ldap_login_attribute: mail
            calls:
                - [ setLogger, [ @?logger ] ]

3. create a handler class, which converts the RemoteUser into eZ users.
    Subclass Kaliop\IdentityManagementBundle\Security\User\RemoteUserHandler, implement `setFieldValuesFromProfile` and
    `getGroupsFromProfile`

4. declare it as a service, eg:

        # The service which creates repo users out of ldap users
        my.ldap_auth.remoteuser_handler:
            class: My\LdapAuthBundle\Adapter\LDAP\RemoteUserHandler
            arguments:
                - "@my.ldap_auth.client"
                - "@ezpublish.api.repository"
                -
                    user_contenttype: user
                    default_content_language: eng-GB
                    group_mapping:
                        "CN=LTD_Intranet_Administrator": 12
                        "CN=LTD_Intranet_CorpContentManager": 13

5. tie your new service to the RemoteUser class returned by the ldap client:

        parameters:
            kaliop_identity.remoteuser_service_map:
                Kaliop\IdentityManagementBundle\Adapter\LDAP\RemoteUser: my.ldap_auth.remoteuser_handler

6. set up a firewall definition which activates the whole thing: in security.yml:

        ezpublish_front:
            pattern: ^/
            anonymous: ~
            # Allow users to log in via LDAP.
            # The name HAS TO BE 'remoteuser_login'
            remoteuser_login:
                # the service used to connect to the LDAP server
                client: my.ldap_auth.client
            form_login:
                require_previous_session: false
            logout: ~

### Allowing remote-service login to the Legacy Admin interface

1. enable the identitymangementextension extension (bundled in this bundle)

2. if you have renamed the firewall in security.yml to anything but ezpublish_front, set up identitymanagement.ini.append.php

3. clear caches, test, done!

### Advanced usage

### Creating a remote-user-provider service for non-ldap services

1. create a subclass of Kaliop\IdentityManagementBundle\Security\User\RemoteUser

2. create a client class, implementing ClientInterface
    (take a look at Kaliop\IdentityManagementBundle\Adapter\LDAP\Client as an example)

3. declare the new class as a service

4. put the service id in a *remoteuser_login* in the firewall section of security.yml

5. create a handler class, which converts the RemoteUser into eZ users, implementing RemoteUserHandlerInterface
    (probably subclassing Kaliop\IdentityManagementBundle\Security\User\RemoteUserHandler is a good idea)

6. declare it as a service

7. add it the the handler map in the parameter `kaliop_identity.remoteuser_service_map`

The logical flow is the following:
- when a site visitor tries to log in, the client will query the remote system, and, if login is ok, build and return a
  remoteUser object from the data it gets
- immediately afterwards, the handler takes care of matching the remoteUser with an eZuser account, updating/creating it
  if needed


[![License](https://poser.pugx.org/kaliop/identitymanagementbundle/license)](https://packagist.org/packages/kaliop/identitymanagementbundle)
[![Latest Stable Version](https://poser.pugx.org/kaliop/identitymanagementbundle/v/stable)](https://packagist.org/packages/kaliop/identitymanagementbundle)
[![Total Downloads](https://poser.pugx.org/kaliop/identitymanagementbundle/downloads)](https://packagist.org/packages/kaliop/identitymanagementbundle) 

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kaliop-uk/ezidentitymanagementbundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kaliop-uk/ezidentitymanagementbundle/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/deb0788e-d3f5-47f2-a86f-21a99011f803/mini.png)](https://insight.sensiolabs.com/projects/deb0788e-d3f5-47f2-a86f-21a99011f803)
