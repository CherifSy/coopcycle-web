<?php

namespace AppBundle\Entity\Menu;

use AppBundle\Entity\MenuItem as BaseMenuItem;
use AppBundle\Entity\Menu\MenuItemModifier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="menu_item")
 * @ApiResource(
 *  shortName="MenuItem",
 *  iri="http://schema.org/MenuItem",
 *  collectionOperations={},
 *  itemOperations={
 *    "get"={"method"="GET"}
 *  })
 */
class MenuItem extends BaseMenuItem
{
    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu\MenuSection", inversedBy="items")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    private $section;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Menu\MenuItemModifier",
     *                mappedBy="menuItem",
     *                cascade={"persist"})
     * @Groups({"restaurant"})
     */
    protected $modifiers;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $isDeleted = false;

    /**
     * @Groups({"restaurant"})
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $isAvailable = false;

    public function __construct()
    {
        $this->modifiers = new ArrayCollection();
    }

    public function getSection()
    {
        return $this->section;
    }

    public function setSection(MenuSection $section = null)
    {
        $this->section = $section;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsDeleted()
    {
        return $this->isDeleted;
    }

    /**
     * @param mixed $isDeleted
     */
    public function setIsDeleted($isDeleted)
    {
        $this->isDeleted = $isDeleted;
    }

    /**
     * @return mixed
     */
    public function getIsAvailable()
    {
        return $this->isAvailable;
    }

    /**
     * @param mixed $isUnavailable
     */
    public function setIsAvailable($isUnavailable)
    {
        $this->isAvailable = $isUnavailable;
    }

    /**
     * @return ArrayCollection|MenuItemModifier[]
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * @param mixed $modifiers
     */
    public function setModifiers($modifiers)
    {
        $this->modifiers = $modifiers;
    }

    public function addModifier(MenuItemModifier $modifier) {
        $modifier->setMenuItem($this);
        $this->modifiers->add($modifier);
    }

}
