<?php
/**
 * This file is part of devrun.
 * Copyright (c) 2019
 *
 * @file    Image.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\PhantomModule\Entities;

use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\Doctrine\Entities\ImageTrait;
use Doctrine\ORM\Mapping as ORM;
use Devrun\Doctrine\Entities\DateTimeTrait;
use Devrun\Doctrine\Entities\IdentifiedEntityTrait;
use Kdyby\Doctrine\Entities\MagicAccessors;

/**
 * Class Images
 *
 * @ORM\Entity(repositoryClass="Devrun\PhantomModule\Repositories\PhantomRepository")
 * @ORM\Table(name="phantom_images", indexes={
 *     @ORM\Index(name="phantom_identifier_idx", columns={"identifier"}),
 *     @ORM\Index(name="phantom_namespace_name_idx", columns={"namespace", "name"}),
 * })
 * @package Devrun\CmsModule\Entities
 */
class ImageEntity
{
    use IdentifiedEntityTrait;
    use MagicAccessors;
    use DateTimeTrait;
    use ImageTrait;


    /**
     * @var RouteEntity
     * @ORM\ManyToOne(targetEntity="Devrun\CmsModule\Entities\RouteEntity")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    protected $route;

    /**
     * @var string original identifier
     * @ORM\Column(type="string")
     */
    protected $referenceIdentifier;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $captureName;



    /**
     * ImageEntity constructor.
     *
     * @param $route
     */
    public function __construct(RouteEntity $route)
    {
        $this->route = $route;
    }






    /*
     * ----------------------------------------------------------------------------------------
     * getters / setters properties
     * ----------------------------------------------------------------------------------------
     */
    /**
     * @return RouteEntity
     */
    public function getRoute(): RouteEntity
    {
        return $this->route;
    }

    /**
     * @param RouteEntity $route
     *
     * @return ImageEntity
     */
    public function setRoute(RouteEntity $route): ImageEntity
    {
        $this->route = $route;
        return $this;
    }


    /**
     * @return string
     */
    public function getReferenceIdentifier(): string
    {
        return $this->referenceIdentifier;
    }

    /**
     * @param string $referenceIdentifier
     *
     * @return ImageEntity
     */
    public function setReferenceIdentifier(string $referenceIdentifier): ImageEntity
    {
        $this->referenceIdentifier = $referenceIdentifier;
        return $this;
    }

    /**
     * @return string
     */
    public function getCaptureName(): string
    {
        return $this->captureName;
    }

    /**
     * @param string $captureName
     *
     * @return ImageEntity
     */
    public function setCaptureName(string $captureName): ImageEntity
    {
        $this->captureName = $captureName;
        return $this;
    }









    /*
     * ----------------------------------------------------------------------------------------
     * internal properties
     * ----------------------------------------------------------------------------------------
     */


}