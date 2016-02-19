Kaliop Identity Management Bundle
=================================

## Features

### Log In By IP

- This is implemented via a custom firewall
    named *ip_login* in the *firewalls* section of security.yml
    the firewall depends on a separate service for the mapping IP => user account name

### Log In By Remote services

- This is implemented via a custom firewall
    named *remoteuser_login* in the *firewalls* section of security.yml

- The firewall depends on two additional services for:
    * communicating to the remote webservice
    * creating an instance of (a subclass of) Kaliop\IdentityManagementBundle\Security\User\RemoteUser when user logs in
    * mapping that instance into eZPubish users (creating/updating them on the fly at login time)

    The idea is that it should be easy to swap/add remote user services without having to learn the intricate details of
    the Symfony auth component (firewall/authenticator/userprovider/factory)


## Getting started

...


## Advanced usage

### How to create a remote-user-provider service

1. create a subclass of Kaliop\IdentityManagementBundle\Security\User\RemoteUser

2. create a client class, implementing ClientInterface

3. declare the new class as a service

4. put the service id in a *remoteuser_login* in the firewall section of security.yml

5. create a handler class, which converts the RemoteUser into eZ users

6. add it the the handler map in the parameter `kaliop_identity.remoteuser_service_map`
