Kaliop Identity Management Bundle
=================================

## Log In By IP

- to do: if user has a session cookie, disregard his IP, to allow login of IP-logged users
- to do: a proper mapping IP => user account (at the moment it is in yml config)
- to do: create user account on the fly + give him roles
- to do: add separate user roles (in the SF sense) for users logged in via login and via IP
- to do: inject the logger service so that we can easily trace how this is working

## Log In By Remote services

- use tagged services for remoteUserHandler definition, to avoid injecting the container

- add ldap handler example (it is quite widespread in use after all)

- add a few more 'example' handlers for common services (twitter/fb/google ? do they all use oauth?)

- to do: test: does ez native auth mechanism kick in before the remote one? If so ...
- to do: make sure remote users can not log in into eZ with the hardcoded password (see RemoteUserHandler)
- to do: store the password encrypted in the RemoteUser instead of plaintext
- to do: store in the eZ users the remote-id from the remote service, just in case (done per-handler...)
- to do: move ldap config from settings to semantic, for validation
- to do: check if it is a good idea to remove the 'remoteuser' provider in app/security.yml. Remoteusers after all are
         not meant to be used as actual logged in users anyway
- to do: add support for forgotpassword
- to do: add more comprehensive logging support
- to do: add an interface for RemoteUserHandler classes

out of scope (but could be done):
- store pwd of remote user in ez user table, so that if remote server fails, user can still log in for a while
