<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Restaurant;


class Cart
{
    private $restaurantId;
    private $date;

    /**
     * @var CartItem[]
     */
    private $items = array();

    public function __construct(Restaurant $restaurant = null)
    {
        if (null !== $restaurant) {
            $this->restaurantId = $restaurant->getId();
        }
    }

    public function isForRestaurant(Restaurant $restaurant)
    {
        return $this->restaurantId === $restaurant->getId();
    }

    public function getRestaurantId()
    {
        return $this->restaurantId;
    }

    public function clear()
    {
        $this->items = array();
    }

    public function addItem($menuItem, $quantity = 1, $modifierChoices = [])
    {
        $cartItem = new CartItem($menuItem, $quantity, $modifierChoices);
        $itemKey = $cartItem->getKey();

        if (array_key_exists($itemKey, $this->items)) {
            $this->items[$itemKey]->update($quantity);
        }
        else {
            $this->items[$itemKey] = $cartItem;
        }

        return $this->items;

    }

    public function removeItem($itemKey)
    {
        if (array_key_exists($itemKey, $this->items)) {
            unset($this->items[$itemKey]);
        }

        return $this->items;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function getTotal()
    {
        return array_reduce($this->items, function ($carry, $item) {
            return $carry + $item->getTotal();
        }, 0);
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getNormalizedItems()
    {
        // Make sure this is a zero-indexed array, for proper JSON serialization
        return array_values(array_map(function ($el) { return $el->toArray(); }, $this->items));
    }

    public function toArray()
    {
        return array(
            'date' => $this->date->format('Y-m-d H:i:s'),
            'items' => $this->getNormalizedItems(),
        );
    }
}
