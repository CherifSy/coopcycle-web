<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu;
use AppBundle\Entity\MenuSection;
use AppBundle\Form\RestaurantMenuType;
use AppBundle\Form\MenuType;
use AppBundle\Form\RestaurantType;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait RestaurantTrait
{
    protected function checkAccess(Restaurant $restaurant)
    {
        if ($this->getUser()->hasRole('ROLE_ADMIN')) {
            return;
        }

        if ($this->getUser()->ownsRestaurant($restaurant)) {
            return;
        }

        throw new AccessDeniedHttpException();
    }

    protected function editRestaurantAction($id, Request $request, $layout, array $routes)
    {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Restaurant');
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');

        if (null === $id) {
            $restaurant = new Restaurant();
        } else {
            $restaurant = $repository->find($id);
            $this->checkAccess($restaurant);
        }

        $form = $this->createForm(RestaurantType::class, $restaurant);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $restaurant = $form->getData();

            if ($id === null) {
                $em->persist($restaurant);
                $this->getUser()->addRestaurant($restaurant);
            }

            $em->flush();

            return $this->redirectToRoute($routes['success']);
        }

        return [
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'layout' => $layout,
            'menu_route' => $routes['menu'],
            'orders_route' => $routes['orders'],
            'restaurants_route' => $routes['restaurants'],
            'google_api_key' => $this->getParameter('google_api_key'),
        ];
    }

    private function createMenuForm(Menu $menu, MenuSection $sectionAdded = null)
    {
        $form = $this->createForm(MenuType::class, $menu, [
            'section_added' => $sectionAdded,
        ]);
        $form->add('submit', SubmitType::class, array('label' => 'Save'));

        return $form;
    }

    protected function editMenuAction($id, Request $request, $layout, array $routes)
    {
        $em = $this->getDoctrine()->getManagerForClass('AppBundle:Restaurant');

        $restaurant = $this->getDoctrine()
            ->getRepository('AppBundle:Restaurant')->find($id);

        $this->checkAccess($restaurant);

        $menu = $restaurant->getMenu();

        $originalItems = new \SplObjectStorage();
        foreach ($menu->getSections() as $section) {
            $items = new ArrayCollection();
            foreach ($section->getItems() as $item) {
                $items->add($item);
            }

            $originalItems[$section] = $items;
        }

        $form = $this->createMenuForm($menu);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = $form->getData();

            foreach ($menu->getSections() as $section) {
                foreach ($originalItems[$section] as $originalItem) {
                    if (false === $section->getItems()->contains($originalItem)) {
                        $originalItem->setIsDeleted(true);
                    }
                }
            }

            $restaurant->setMenu($menu);

            $em->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('Your changes were saved.')
            );

            return $this->redirectToRoute($routes['success'], ['id' => $restaurant->getId()]);
        }

        return [
            'restaurant' => $restaurant,
            'form' => $form->createView(),
            'layout' => $layout,
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
        ];
    }
}
