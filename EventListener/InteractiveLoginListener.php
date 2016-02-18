<?php

namespace Kaliop\IdentityManagementBundle\EventListener;

use eZ\Publish\Core\MVC\Symfony\Event\InteractiveLoginEvent;
use eZ\Publish\Core\MVC\Symfony\MVCEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Kaliop\IdentityManagementBundle\Security\User\RemoteUserProviderInterface;

/**
 * Used to transform 'Remote Users' into 'eZ Users'
 * @see https://doc.ez.no/display/EZP/How+to+authenticate+a+user+with+multiple+user+providers
 */
class InteractiveLoginListener implements EventSubscriberInterface
{
    /**
     * @var \eZ\Publish\API\Repository\UserService
     */
    protected $userProviderService;

    public function __construct(RemoteUserProviderInterface $userProviderService)
    {
        $this->userProviderService = $userProviderService;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MVCEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin'
        );
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $this->userProviderService->loadAPIUserByRemoteUser($event->getAuthenticationToken()->getUser());

        // a NULL means that from the POV of the repo, the user will stay anonymous!
        if ($user !== null) {
            $event->setApiUser($user);
        }
    }
}