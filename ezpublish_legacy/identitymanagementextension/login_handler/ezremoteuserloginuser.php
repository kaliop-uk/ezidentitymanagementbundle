<?php

use Kaliop\IdentityManagementBundle\Security\Authentication\Provider\RemoteUserAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class eZRemoteUserLoginUser extends eZUser
{

    /**
     * We need to override this because parent call uses 'self' instead of 'static'
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return bool|mixed
     */
    static function loginUser( $login, $password, $authenticationMatch = false )
    {
        $user = self::_loginUser( $login, $password, $authenticationMatch );

        if ( is_object( $user ) )
        {
            self::loginSucceeded( $user );
            return $user;
        }
        else
        {
            self::loginFailed( $user, $login );
            return false;
        }
    }

    protected static function _loginUser( $login, $password, $authenticationMatch = false )
    {
        $fwName = eZINI::instance('identitymanagement.ini')->variable('GeneralSettings', 'FirewallName');

        $container = ezpKernel::instance()->getServiceContainer();

        // nb: this string is related to the name of the firewall!
        /** @var RemoteUserAuthenticationProvider $remoteUserAuthProvider */
        $remoteUserAuthProvider = $container->get('security.authentication.provider.remoteuser.'.$fwName);
        $token = new UsernamePasswordToken($login, $password, $fwName, array('ROLE_USER'));

        try {
            // get the authorized token, which contains the remoteUser
            $authToken = $remoteUserAuthProvider->authenticate($token);
            // convert the remoteUser into an eZP user (this creates the user in the db if needed)
            $request = $container->get('request_stack')->getCurrentRequest();
            $event = new InteractiveLoginEvent($request, $authToken);
            $container->get("event_dispatcher")->dispatch("security.interactive_login", $event);

            // now get back the eZP user for the eZ4 stack
            /** @var eZ\Publish\Core\Repository\Values\User\User $user */
            $user = $container->get('security.token_storage')->getToken()->getUser()->getAPIUser();

            // and set back an anon token for Sf, as after the redirect, that's what the eZ\Bundle\EzPublishLegacyBundle\EventListener\RequestListener expects
            $container->get('security.token_storage')->setToken(null);

            /// @todo shall we check isenabled ?

            return self::fetch($user->id);

        } catch(\Exception $e) {
            /// @todo make it easier to tell apart system error from user errors such as bad password...

            eZDebug::writeError($e->getMessage(), __METHOD__ );

            return false;
        }
    }

}
