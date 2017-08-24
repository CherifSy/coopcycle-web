<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Order;
use AppBundle\Entity\OrderEvent;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OrderListener
{
    private $tokenStorage;
    private $deliveryServiceFactory;

    public function __construct(TokenStorageInterface $tokenStorage, DeliveryServiceFactory $deliveryServiceFactory)
    {
        $this->tokenStorage = $tokenStorage;
        $this->deliveryServiceFactory = $deliveryServiceFactory;
    }

    private function getDeliveryService(Order $order)
    {
        return $this->deliveryServiceFactory->createForRestaurant($order->getRestaurant());
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(Order $order, LifecycleEventArgs $args)
    {
        $delivery = $order->getDelivery();

        // Make sure customer is set
        if (null === $order->getCustomer()) {
            $order->setCustomer($this->getUser());
        }

        // Make sure models are associated
        $delivery->setOrder($order);

        // Make sure originAddress is set
        if (null === $delivery->getOriginAddress()) {
            $delivery->setOriginAddress($order->getRestaurant()->getAddress());
        }

        if (!$delivery->isCalculated()) {
            $this->getDeliveryService($order)->calculate($delivery);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Order $order, LifecycleEventArgs $args)
    {
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(Order $order, LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();

        $orderEvent = new OrderEvent($order, $order->getStatus(), $order->getCourier());
        $em->persist($orderEvent);
        $em->flush();

        $this->getDeliveryService($order)->onOrderUpdate($order);
    }
}
