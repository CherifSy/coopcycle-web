<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Order;
use AppBundle\Form\AddressType;
use AppBundle\Form\UpdateProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route as Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends Controller
{
    use AdminTrait;
    use RestaurantTrait;

    /**
     * @Route("/profile/edit", name="profile_edit")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function editProfile(Request $request) {

        $user = $this->getUser();

        $editForm = $this->createForm(UpdateProfileType::class, $user);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userManager = $this->getDoctrine()->getManagerForClass(ApiUser::class);
            $userManager->persist($user);
            $userManager->flush();

            return $this->redirectToRoute('fos_user_profile_show');
        }

        return array(
            'form' => $editForm->createView()
        );
    }

    /**
     * @Route("/profile/orders", name="profile_orders")
     * @Template()
     */
    public function ordersAction(Request $request)
    {
        $orderManager = $this->getDoctrine()->getManagerForClass('AppBundle:Order');
        $orderRepository = $orderManager->getRepository('AppBundle:Order');

        $page = $request->query->get('page', 1);

        $qb = $orderRepository->createQueryBuilder('o');

        $qb->select($qb->expr()->count('o'))
           ->where('o.customer = ?1')
           ->setParameter(1, $this->getUser());

        $query = $qb->getQuery();
        $ordersCount = $query->getSingleScalarResult();

        $perPage = 15;

        $pages = ceil($ordersCount / $perPage);
        $offset = $perPage * ($page - 1);

        $orders = $orderRepository->findBy(
            ['customer' => $this->getUser()],
            ['createdAt' => 'DESC'],
            $perPage,
            $offset
        );

        return array(
            'orders' => $orders,
            'page' => $page,
            'pages' => $pages,
        );
    }

    /**
     * @Route("/profile/orders/{id}", name="profile_order")
     * @Template("@App/Order/details.html.twig")
     */
    public function orderAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository(Order::class)->find($id);

        $orderEvents = [];
        foreach ($order->getEvents() as $event) {
            $orderEvents[] = [
                'eventName' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        $deliveryEvents = [];
        foreach ($order->getDelivery()->getEvents() as $event) {
            $deliveryEvents[] = [
                'eventName' => $event->getEventName(),
                'timestamp' => $event->getCreatedAt()->getTimestamp()
            ];
        }

        return array(
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld'),
            'order_events_json' => $this->get('serializer')->serialize($orderEvents, 'json'),
            'delivery_events_json' => $this->get('serializer')->serialize($deliveryEvents, 'json'),
            'layout' => 'AppBundle::profile.html.twig',
            'breadcrumb_path' => 'profile_orders'
        );
    }

    /**
     * @Route("/profile/addresses", name="profile_addresses")
     * @Template()
     */
    public function addressesAction(Request $request)
    {
        return array(
            'addresses' => $this->getUser()->getAddresses(),
        );
    }

    /**
     * @Route("/profile/addresses/new", name="profile_address_new")
     * @Template()
     */
    public function newAddressAction(Request $request)
    {
        $address = new Address();

        $form = $this->createForm(AddressType::class, $address);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address = $form->getData();

            $this->getUser()->addAddress($address);

            $manager = $this->getDoctrine()->getManagerForClass(Address::class);
            $manager->persist($address);
            $manager->flush();

            return $this->redirectToRoute('profile_addresses');
        }

        return array(
            'google_api_key' => $this->getParameter('google_api_key'),
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/profile/deliveries", name="profile_courier_deliveries")
     * @Template()
     */
    public function courierDeliveriesAction(Request $request)
    {
        $deliveryTimes = $this->getDoctrine()->getRepository('AppBundle:Order')
            ->getDeliveryTimes($this->getUser());

        $avgDeliveryTime = $this->getDoctrine()->getRepository('AppBundle:Order')
            ->getAverageDeliveryTime($this->getUser());

        $orders = $this->getDoctrine()->getRepository('AppBundle:Order')->findBy(
            ['courier' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return [
            'orders' => $orders,
            'avg_delivery_time' => $avgDeliveryTime,
            'delivery_times' => $deliveryTimes,
        ];
    }

    /**
     * @Route("/profile/restaurants", name="profile_restaurants")
     * @Template()
     */
    public function restaurantsAction(Request $request)
    {
        $restaurants = $this->getUser()->getRestaurants();

        return [
            'restaurants' => $restaurants,
        ];
    }

    /**
     * @Route("/profile/restaurants/new", name="profile_restaurant_new")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function newRestaurantAction(Request $request)
    {
        return $this->editRestaurantAction(null, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurants',
            'restaurants' => 'profile_restaurants',
            'menu' => 'profile_restaurant_menu',
            'orders' => 'profile_restaurant_orders',
        ]);
    }

    /**
     * @Route("/profile/restaurants/{id}", name="profile_restaurant_edit")
     * @Template("@App/Restaurant/form.html.twig")
     */
    public function restaurantEditAction($id, Request $request)
    {
        return $this->editRestaurantAction($id, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurants',
            'restaurants' => 'profile_restaurants',
            'menu' => 'profile_restaurant_menu',
            'orders' => 'profile_restaurant_orders',
        ]);
    }

    /**
     * @Route("/profile/restaurants/{id}/menu", name="profile_restaurant_menu")
     * @Template("@App/Restaurant/form-menu.html.twig")
     */
    public function restaurantMenuAction($id, Request $request)
    {
        return $this->editMenuAction($id, $request, 'AppBundle::profile.html.twig', [
            'success' => 'profile_restaurants',
            'restaurants' => 'profile_restaurants',
            'restaurant' => 'profile_restaurant_edit',
        ]);
    }

    /**
     * @Route("/profile/payment", name="profile_payment")
     * @Template()
     */
    public function paymentAction(Request $request)
    {
        $stripeParams = $this->getUser()->getStripeParams();

        $stripeClientId = $this->getParameter('stripe_connect_client_id');
        $stripeAuthorizeURL = 'https://connect.stripe.com/oauth/authorize?response_type=code&client_id='.$stripeClientId.'&scope=read_write';

        return [
            'stripe_authorize_url' => $stripeAuthorizeURL,
            'stripe_user_id' => $stripeParams ? $stripeParams->getUserId() : null
        ];
    }

    /**
     * @Route("/profile/restaurants/{id}/orders", name="profile_restaurant_orders")
     * @Template("@App/Admin/Restaurant/orders.html.twig")
     */
    public function restaurantOrdersAction($id, Request $request)
    {
        return $this->restaurantOrders($id, [
            'order_accept'  => 'profile_order_accept',
            'order_refuse'  => 'profile_order_refuse',
            'order_cancel'  => 'profile_order_cancel',
            'order_ready'   => 'profile_order_ready',
            'order_details' => 'profile_order',
            'restaurants'   => 'profile_restaurants',
            'restaurant'    => 'profile_restaurant_edit',
        ], $request->query->get('tab', 'today'));
    }

    /**
     * @Route("/profile/orders/{id}/accept", name="profile_order_accept")
     * @Template
     */
    public function acceptOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->acceptOrder($id, 'profile_restaurant_orders', ['id' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/refuse", name="profile_order_refuse")
     * @Template
     */
    public function refuseOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->refuseOrder($id, 'profile_restaurant_orders', ['id' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/ready", name="profile_order_ready")
     * @Template
     */
    public function readyOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->readyOrder($id, 'profile_restaurant_orders', ['id' => $order->getRestaurant()->getId()]);
        }
    }

    /**
     * @Route("/profile/orders/{id}/cancel", name="profile_order_cancel")
     */
    public function cancelOrderAction($id, Request $request)
    {
        if ($request->isMethod('POST')) {
            $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

            return $this->cancelOrder($id, 'profile_restaurant_orders', ['id' => $order->getRestaurant()->getId()]);
        }
    }
}
