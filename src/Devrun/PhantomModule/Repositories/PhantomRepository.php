<?php
/**
 * This file is part of devrun
 * Copyright (c) 2019
 *
 * @file    PhantomRepository.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\PhantomModule\Repositories;

use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\Storage\ImageStorage;
use Kdyby\Doctrine\EntityRepository;

class PhantomRepository extends EntityRepository
{

    /** @var ImageStorage @inject */
    public $storage;

    /** @var string [www] */
    private static $wwwDir;


    /**
     * @var string DI setter
     *
     * @param string $wwwDir
     */
    public static function setWwwDir(string $wwwDir)
    {
        self::$wwwDir = $wwwDir;
    }


    /**
     * from macro phantomImg
     *
     * @param $image ImageEntity|string
     * @return bool
     */
    public static function exist($image)
    {
        if ($image instanceof ImageEntity) {
            $path = self::$wwwDir . DIRECTORY_SEPARATOR . $image->getPath();
            return file_exists($path);

        } else {
            // @todo neumíme zatím zpracovat jiný typ než ImageEntity, string? [capture/module/image.png]
        }

        return false;
    }


}