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
use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\PhantomModule\Facades\PhantomFacade;
use Devrun\PhantomModule\Repositories\PhantomRepository;
use Kdyby\Doctrine\DI\IEntityProvider;
use Kdyby\Doctrine\DI\OrmExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class PhantomExtension extends CompilerExtension implements IEntityProvider
{

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'phantomBin' => Expect::string('%libsDir%/bin/phantomjs'),
            'width'      => Expect::int(1920),
            'height'     => Expect::int(1280),
            'wwwDir'     => Expect::string('%wwwDir%'),
            'syncLoad'   => Expect::bool(true),
            'tempImage'  => Expect::string('%wwwCacheDir%/preview.jpg'),
        ]);
    }


    public function loadConfiguration()
    {
        parent::loadConfiguration();

        $builder = $this->getContainerBuilder();
        $config  = $this->getConfig();


        $builder->addDefinition($this->prefix('repository.phantom'))
                ->setType(PhantomRepository::class)
                ->addSetup('setWwwDir', [$config->wwwDir])
                ->addTag(OrmExtension::TAG_REPOSITORY_ENTITY, ImageEntity::class);

        $builder->addDefinition($this->prefix('facade.phantom'))
                ->setFactory(PhantomFacade::class, [$config->phantomBin, $config->tempImage, $config->width, $config->height]);

        $engine  = $builder->getDefinition('nette.latteFactory');
        $install = 'Devrun\PhantomModule\Macros\UIMacros::install';

        if (method_exists('Latte\Engine', 'getCompiler')) {
            $engine->addSetup('Devrun\PhantomModule\Macros\UIMacros::install(?->getCompiler())', array('@self'));
        } else {
            $engine->addSetup($install . '(?->compiler)', array('@self'));
        }

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