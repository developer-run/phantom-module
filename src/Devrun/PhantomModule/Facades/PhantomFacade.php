<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    PhantomFacade.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\PhantomModule\Facades;

use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\FileNotFoundException;
use Devrun\InvalidStateException;
use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\PhantomModule\Repositories\PhantomRepository;
use Devrun\Storage\ImageNameScript;
use Devrun\Storage\ImageStorage;
use JonnyW\PhantomJs\Client;
use JonnyW\PhantomJs\Http\CaptureRequest;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\Component;
use Nette\Utils\Validators;

class PhantomFacade
{

    /** @var Client */
    private $instance;

    /** @var LinkGenerator */
    private $linkGenerator;

    /** @var PhantomRepository */
    private $phantomRepository;

    /** @var ImageStorage */
    private $imageStorage;

    /** @var Component */
    private $_presenter;

    /** @var string [webTemp/preview.jpg] */
    private $tempImage;

    private $capturePageOptions = [
        'width'          => 1920,
        'height'         => 1280,
        'captureDelay'   => 1,
        'captureTimeOut' => 1000,
    ];


    /**
     * PhantomFacade constructor.
     *
     * @param string $phantomBin
     * @param string $tempImage
     * @param int $width
     * @param int $height
     * @param LinkGenerator $linkGenerator
     * @param PhantomRepository $phantomRepository
     * @param ImageStorage $imageStorage
     */
    public function __construct(string $phantomBin, string $tempImage, int $width, int $height, LinkGenerator $linkGenerator, PhantomRepository $phantomRepository, ImageStorage $imageStorage)
    {
        $this->setInstance($phantomBin);
        $this->phantomRepository            = $phantomRepository;
        $this->linkGenerator                = $linkGenerator;
        $this->imageStorage                 = $imageStorage;
        $this->tempImage                    = $tempImage;
        $this->capturePageOptions['width']  = $width;
        $this->capturePageOptions['height'] = $height;
    }


    protected function setInstance(string $phantomBin)
    {
        $this->instance = Client::getInstance();
        if (!file_exists($phantomBin)) {
            throw new FileNotFoundException($phantomBin);
        }

        $this->instance->getEngine()->setPath($phantomBin);
    }

    /**
     * @return Client
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @deprecated use linkGenerator instead
     * @return Component
     */
    private function getPresenter(): Component
    {
        if (null === $this->_presenter) {
            throw new InvalidStateException("setPresenter first, this is necessary for link generate.");
        }

        return $this->_presenter;
    }


    /**
     * @param Component $presenter
     *
     * @return PhantomFacade
     */
    public function setPresenter(Component $presenter): PhantomFacade
    {
        $this->_presenter = $presenter;
        return $this;
    }

    /**
     * @param array $capturePageOptions
     * @throws \Nette\Utils\AssertionException
     */
    public function setCapturePageOptions(array $capturePageOptions)
    {
        foreach ($this->capturePageOptions as $key => $capturePageOption) {
            Validators::assertField($capturePageOptions, $key, null, "captureOption item '%' in array");
        }

        $this->capturePageOptions = array_merge($this->capturePageOptions, $capturePageOptions);
    }


    /**
     * @param int $width
     *
     * @return PhantomFacade
     */
    public function setWidth(int $width): PhantomFacade
    {
        $this->capturePageOptions['width'] = $width;
        return $this;
    }

    /**
     * @param int $height
     *
     * @return PhantomFacade
     */
    public function setHeight(int $height): PhantomFacade
    {
        $this->capturePageOptions['height'] = $height;
        return $this;
    }


    /**
     * @param ImageEntity $imageEntity
     */
    private function updateIdentifier(ImageEntity & $imageEntity)
    {
        if ($imageEntity->getIdentifier()) {
            $this->imageStorage->delete($imageEntity->getIdentifier());
        }

        $routeEntity = $imageEntity->getRoute();

        // $link = $this->getPresenter()->link("//" . $routeEntity->getUri(), $routeEntity->getParams());
        $link = $this->linkGenerator->link(ltrim($routeEntity->getUri(), ':'), $routeEntity->getParams());

        $client = $this->getInstance();

        /** @var CaptureRequest $request */
        $request = $client->getMessageFactory()->createCaptureRequest($link);

        if ($this->capturePageOptions['captureDelay'] > 0) {
            $request
                ->setDelay($this->capturePageOptions['captureDelay'])// Depends on how long it takes for the webpage to fully load. this vs setTimeout(x) ?
                ->setTimeout($this->capturePageOptions['captureTimeOut']);
        }

        if (isset($this->capturePageOptions['captureDimension'])) {
            list($w, $h, $top, $left) = $this->capturePageOptions['captureDimension'];
            $request->setCaptureDimensions($w, $h, $top, $left);
        }

        $request
            ->setOutputFile(($this->tempImage))
            ->setViewportSize($this->capturePageOptions['width'], $this->capturePageOptions['height']);


        /** @var \JonnyW\PhantomJs\Http\Response $response */
        $response = $client->getMessageFactory()->createResponse();

        // Send the request
        $client->send($request, $response);

        if (!file_exists($this->tempImage)) {
            throw new FileNotFoundException($this->tempImage);
        }

        $url     = $routeEntity->getUrl();
        $content = file_get_contents($this->tempImage);

        $script = ImageNameScript::fromIdentifier($referenceIdentifier = "capture/$url/preview.jpg");

        $fileName  = implode('.', [$script->name, $script->extension]);
        $namespace = implode('/', [$script->namespace, $script->prefix]);

        $img       = \Nette\Utils\Image::fromFile($this->tempImage);
        $image     = $this->imageStorage->saveContent($content, $fileName, $namespace);
        $scriptNew = ImageNameScript::fromIdentifier($image->identifier);

        unlink($this->tempImage);

        $imageEntity
            ->setReferenceIdentifier($referenceIdentifier)
            ->setCaptureName($script->name)
            ->setNamespace($scriptNew->namespace)
            ->setName($image->name)
            ->setAlt($image->name)
            ->setSha($image->sha)
            ->setIdentifier($image->identifier)
            ->setPath($image->createLink())
            ->setWidth($img->getWidth())
            ->setHeight($img->getHeight())
            ->setType(finfo_file(finfo_open(FILEINFO_MIME_TYPE), $image->createLink()));

        $this->phantomRepository->getEntityManager()->persist($imageEntity)->flush();
    }


    /**
     * @param RouteEntity $routeEntity
     *
     * @return ImageEntity
     */
    private function createIdentifierFromRoute(RouteEntity $routeEntity): ImageEntity
    {
        $imageEntity = new ImageEntity($routeEntity);
        $this->updateIdentifier($imageEntity);

        return $imageEntity;
    }


    private function checkIdentifierFromRoute(ImageEntity $imageEntity)
    {
        return file_exists($imageEntity->getPath());
    }


    public function getIdentifierFromRoute(RouteEntity $routeEntity)
    {
        /** @var ImageEntity $imageEntity */
        if (!$imageEntity = $this->phantomRepository->findOneBy(['route' => $routeEntity])) {
            $imageEntity = $this->createIdentifierFromRoute($routeEntity);
        }

        if (!$this->checkIdentifierFromRoute($imageEntity)) {
            $this->updateIdentifier($imageEntity);
        }

        return $imageEntity;
    }


}