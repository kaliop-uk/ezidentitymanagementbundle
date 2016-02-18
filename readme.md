Scrip Identity Management Bundle
================================

## Log In By IP

- This is implemented via a custom firewall
    named *ip_login* in the *firewalls* section of security.yml
    the firewall depends on a separate service for the mapping IP => user account name
    
- to do: if user has a session cookie, disregard his IP, to allow login of IP-logged users
- to do: a proper mapping IP => user account (at the moment it is in yml config)
- to do: create user account on the fly + give him roles
- to do: add separate user roles (in the SF sense) for users logged in via login and via IP
- to do: inject the logger service so that we can easily trace how this is working

## Log In By Remote services

- This is implemented via a custom firewall
    named *remoteuser_login* in the *firewalls* section of security.yml
    
- The firewall depends on two additional services for:
    * communicating to the remote webservice
    * creating an instance of (a subclass of) Scrip\IdentityManagementBundle\Security\User\RemoteUser when user logs in
    * mapping that instance into eZPubish users (creating/updating them on the fly at login time)
    
    The idea is that it should be easy to swap/add remote user services without having to learn the intricate details of
    the Symfony auth component (firewall/authenticator/userprovider/factory)

- to do: test: does ez native auth mechanism kick in before the remote one? If so
- to do: make sure remote users can not log in into eZ with the hardcoded password (see RemoteUserHandler) 
- to do: store locally the wsdl files to avoid blocking the site in case the origin server is kaput
- to do: assign to eZ users which get created adequate permissions based on subscriptions
- to do: update eZ users if their permissions get updated, at login time (save md5 of them in the user itself) 
- to do: store the password encrypted in the RemoteUser instead of plaintext
- to do: store in the eZ users the remote-id from the remote service, just in case
- to do: we are sending a bogus session Id to the AMS webservice. Shall we send the real one?
- to do: test: is there a bug where the same company will be created many times?
- to do: check the timeout set for the soap call
- to do: move config of the RemoteUserHandler into parameters, so that it can be changed per environment
- to do: check if it is a good idea to remove the 'remoteuser' provider in app/security.yml. Remoteusers after all are
         not meant to be used as actual logged in users anyway 
- to do: add support for forgotpassword
- to do: add more comprehensive logging support 
- to do: add an interface for RemoteUserHandler classes

out of scope (but could be done):
- store pwd of remote user in ez user table, so that if remote server fails, user can still log in for a while


## How to create a remote-user-provider service

1. create a subclass of Scrip\IdentityManagementBundle\Security\User\RemoteUser

2. create a client class, implementing ClientInterface

3. declare the new class as a service

4. put the service ide in a *remoteuser_login* in the firewall section of security.yml

5. create a handler class, which converts the RemoteUser into eZ users

6. add it the the handler map in the service definition of script_identity.security.remoteuser_provider
