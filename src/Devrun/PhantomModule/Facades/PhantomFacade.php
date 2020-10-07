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
use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\PhantomModule\Repositories\PhantomRepository;
use Devrun\Storage\ImageNameScript;
use Devrun\Storage\ImageStorage;
use JonnyW\PhantomJs\Client;
use JonnyW\PhantomJs\Http\CaptureRequest;
use Nette\Application\LinkGenerator;
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

    /** @var string [webTemp/preview.jpg] */
    private $tempImage;

    /** @var string [www] */
    private $wwwDir;

    /** @var bool */
    private $syncLoad = true;

    private $capturePageOptions = [
        'width'          => 1920,
        'height'         => 1280,
        'captureDelay'   => 3,
        'captureTimeOut' => 1000,
    ];


    /**
     * PhantomFacade constructor.
     *
     * @param string $wwwDir
     * @param string $phantomBin
     * @param string $tempImage
     * @param int $width
     * @param int $height
     * @param LinkGenerator $linkGenerator
     * @param PhantomRepository $phantomRepository
     * @param ImageStorage $imageStorage
     * @throws \Nette\Utils\AssertionException
     */
    public function __construct(array $config, LinkGenerator $linkGenerator, PhantomRepository $phantomRepository, ImageStorage $imageStorage)
    {
        Validators::assert($config['wwwDir'], 'string');
        Validators::assert($config['phantom-bin'], 'string');
        Validators::assert($config['tempImage'], 'string');
        Validators::assert($config['width'], 'int');
        Validators::assert($config['height'], 'int');
        Validators::assert($config['syncLoad'], 'bool');

        $wwwDir     = $config['wwwDir'];
        $phantomBin = $config['phantom-bin'];
        $tempImage  = $config['tempImage'];
        $width      = $config['width'];
        $height     = $config['height'];
        $syncLoad   = $config['syncLoad'];

        $this->setInstance($phantomBin);
        $this->phantomRepository            = $phantomRepository;
        $this->linkGenerator                = $linkGenerator;
        $this->imageStorage                 = $imageStorage;
        $this->tempImage                    = $tempImage;
        $this->wwwDir                       = $wwwDir;
        $this->syncLoad                     = $syncLoad;
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
     *
     * @throws \JonnyW\PhantomJs\Exception\NotWritableException
     * @throws \Nette\Application\UI\InvalidLinkException
     * @throws \Nette\Utils\UnknownImageFileException
     *
     * @return \JonnyW\PhantomJs\Http\ResponseInterface
     */
    private function updateIdentifier(ImageEntity & $imageEntity)
    {
        if ($imageEntity->getIdentifier()) {
            $this->imageStorage->delete($imageEntity->getIdentifier());
        }

        $link   = $this->getRouteLink($routeEntity = $imageEntity->getRoute());
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
        $result = $client->send($request, $response);

        if ($result->getStatus() != 200) {
            return $result;
        }

        if (!file_exists($this->tempImage)) {
            throw new FileNotFoundException($this->tempImage);
        }

        $content = file_get_contents($this->tempImage);
        $referenceIdentifier = $this->generateReferenceName($routeEntity);
        $script = ImageNameScript::fromIdentifier($referenceIdentifier);

        $fileName  = implode('.', [$script->name, $script->extension]);
        $namespace = implode('/', [$script->namespace, $script->prefix]);

        $img       = \Nette\Utils\Image::fromFile($this->tempImage);
        $image     = $this->imageStorage->saveContent($content, $fileName, $namespace);
        $scriptNew = ImageNameScript::fromIdentifier($image->identifier);

        $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $this->tempImage);
        @unlink($this->tempImage);

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
            ->setType($type);

        $this->phantomRepository->getEntityManager()->persist($imageEntity)->flush();
        return $result;
    }


    /**
     * @param RouteEntity $routeEntity
     * @param bool $withOutGenerateDomain
     * @param bool $absolute
     * @return string
     * @throws \Nette\Application\UI\InvalidLinkException
     */
    public function getRouteLink(RouteEntity $routeEntity, bool $withOutGenerateDomain = false, bool $absolute = false): string
    {
        $params = $routeEntity->getParams();
        $params['package'] = $routeEntity->getPackage()->getId();
        if ($withOutGenerateDomain) {
            $params['generateDomain'] = false;
        }
        if (isset($params['id']) && $params['id'] == "?") {
            $params['id'] = 1;
        }

        $filteredUri = ltrim($routeEntity->getUri(), ':');

        return $absolute
            ? $this->linkGenerator->link("//{$filteredUri}", $params)
            : $this->linkGenerator->link("{$filteredUri}", $params);
    }

    /**
     * @param RouteEntity $routeEntity
     *
     * @return ImageEntity
     * @throws \JonnyW\PhantomJs\Exception\NotWritableException
     * @throws \Nette\Application\UI\InvalidLinkException
     * @throws \Nette\Utils\UnknownImageFileException
     */
    private function createIdentifierFromRoute(RouteEntity $routeEntity): ImageEntity
    {
        $imageEntity = new ImageEntity($routeEntity);

        if ($this->syncLoad) {
            $this->updateIdentifier($imageEntity);

        } else {
            $imageEntity->setIdentifier($this->generateReferenceName($routeEntity));
            $imageEntity->setReferenceIdentifier($this->generateReferenceName($routeEntity));
            $imageEntity->setPath('?');
        }

        return $imageEntity;
    }


    /**
     * @param RouteEntity $routeEntity
     * @return ImageEntity
     * @throws \JonnyW\PhantomJs\Exception\NotWritableException
     * @throws \Nette\Application\UI\InvalidLinkException
     * @throws \Nette\Utils\UnknownImageFileException
     */
    public function getIdentifierFromRoute(RouteEntity $routeEntity)
    {
        /** @var ImageEntity $imageEntity */
        if (!$imageEntity = $this->phantomRepository->findOneBy(['route' => $routeEntity])) {
            $imageEntity = $this->createIdentifierFromRoute($routeEntity);
        }

        if ($this->syncLoad) {
            if (!$this->checkIdentifierFromRoute($imageEntity)) {
                $this->updateIdentifier($imageEntity);
            }
        }

        return $imageEntity;
    }

    /**
     * @param RouteEntity $routeEntity
     * @return mixed|object|ImageEntity|null
     * @throws \JonnyW\PhantomJs\Exception\NotWritableException
     * @throws \Nette\Application\UI\InvalidLinkException
     * @throws \Nette\Utils\UnknownImageFileException
     */
    public function updateFromRoute(RouteEntity $routeEntity)
    {
        if (!$imageEntity = $this->phantomRepository->findOneBy(['route' => $routeEntity])) {
            $imageEntity = new ImageEntity($routeEntity);
        }

        $response = $this->updateIdentifier($imageEntity);
        return ['response' => $response, 'image' => $imageEntity];
    }



    private function checkIdentifierFromRoute(ImageEntity $imageEntity)
    {
        return file_exists($this->wwwDir . DIRECTORY_SEPARATOR . $imageEntity->getPath());
    }

    /**
     * @param RouteEntity $routeEntity
     * @return string
     */
    private function generateReferenceName(RouteEntity $routeEntity): string
    {
        $url     = $routeEntity->getUrl();
        return "capture/$url/preview.jpg";
    }


}