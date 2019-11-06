<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    PhantomExtension.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\PhantomModule\DI;

use Devrun\Config\CompilerExtension;
use Devrun\PhantomModule\Facades\PhantomFacade;
use Devrun\PhantomModule\Repositories\PhantomRepository;
use Kdyby\Doctrine\DI\IEntityProvider;
use Kdyby\Doctrine\DI\OrmExtension;
use Devrun\PhantomModule\Entities\ImageEntity;

class PhantomExtension extends CompilerExtension implements IEntityProvider
{

    public $defaults = array(
        'phantom-bin' => '%libsDir%/bin/phantomjs',
        'width'       => 1920,
        'height'      => 1280,
        'tempImage'   => '%wwwCacheDir%/preview.jpg',
    );


    public function loadConfiguration()
    {
        parent::loadConfiguration();

        $builder = $this->getContainerBuilder();
        $config  = $this->getConfig($this->defaults);


        $builder->addDefinition($this->prefix('repository.phantom'))
                ->setType(PhantomRepository::class)
                ->addTag(OrmExtension::TAG_REPOSITORY_ENTITY, ImageEntity::class);

        $builder->addDefinition($this->prefix('facade.phantom'))
                ->setFactory(PhantomFacade::class, [$config['phantom-bin'], $config['tempImage'], $config['width'], $config['height']]);

    }


    /**
     * Returns associative array of Namespace => mapping definition
     *
     * @return array
     */
    public function getEntityMappings()
    {
        return array(
            'Devrun\PhantomModule' => dirname(__DIR__) . '/Entities/',
        );

    }
}