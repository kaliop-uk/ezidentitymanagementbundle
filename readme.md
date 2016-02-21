Kaliop Identity Management Bundle
=================================

## Log In By Email

- This does not need to save the user email in the 'login field'

- To activate it: enable the following parameters in parameters.yml:

        parameters:
            # take over the default user provider - to log him in other ways than login field
            ezpublish.security.user_provider.class: Kaliop\IdentityManagementBundle\Security\User\Provider\EmailUser
            # take over the auth provider as well, in accord
            security.authentication.provider.dao.class: Kaliop\IdentityManagementBundle\Security\Authentication\Provider\RepositoryAuthenticationProvider


## Log In By IP

- This is implemented via a custom firewall
    named *ip_login* in the *firewalls* section of security.yml
    the firewall depends on a separate service for the mapping IP => user account name

- To activate it: ...


## Log In By Remote Services (LDAP/Active Directory or other)

- Support for LDAP is built-in, and needs some config and minimal php code

- For other custom external services you wll need to write more php code

- This is implemented via a custom firewall
    named *remoteuser_login* in the *firewalls* section of security.yml

- The firewall depends on two additional services for:
    * communicating to the remote webservice
    * creating an instance of (a subclass of) Kaliop\IdentityManagementBundle\Security\User\RemoteUser when user logs in
    * mapping that instance into eZPubish users (creating/updating them on the fly at login time)

    The idea is that it should be easy to swap/add remote user services without having to learn the intricate details of
    the Symfony auth component (firewall/authenticator/userprovider/factory)

### Getting started: integrating an LDAP directory

...


### Advanced usage

### How to create a remote-user-provider service

1. create a subclass of Kaliop\IdentityManagementBundle\Security\User\RemoteUser

2. create a client class, implementing ClientInterface

3. declare the new class as a service

4. put the service id in a *remoteuser_login* in the firewall section of security.yml

5. create a handler class, which converts the RemoteUser into eZ users

6. add it the the handler map in the parameter `kaliop_identity.remoteuser_service_map`
