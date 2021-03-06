<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class JsonRequestTransformerListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 128],
        ];
    }

    public function onRequest(GetResponseEvent $event): void
    {
        if ($event->getRequest()->getContent() && stripos($event->getRequest()->headers->get('Content-Type', ''), 'application/json') === 0) {
            $data = json_decode($event->getRequest()->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BadRequestHttpException('The JSON payload is malformed.');
            }

            $event->getRequest()->request->replace(\is_array($data) ? $data : []);
        }
    }
}
