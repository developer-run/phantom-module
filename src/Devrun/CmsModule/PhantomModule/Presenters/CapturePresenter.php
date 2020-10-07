<?php


namespace Devrun\CmsModule\PhantomModule\Presenters;

use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\CmsModule\Repositories\RouteRepository;
use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\PhantomModule\Facades\PhantomFacade;
use Devrun\Storage\ImageStorage;
use JonnyW\PhantomJs\Http\ResponseInterface;
use Nette\Application\UI\Presenter;

/**
 * Class CapturePresenter
 * @package Devrun\CmsModule\PhantomModule\Presenters
 */
class CapturePresenter extends Presenter
{

    /** @var PhantomFacade @inject */
    public $phantomFacade;

    /** @var RouteRepository @inject */
    public $routeRepository;

    /** @var ImageStorage @inject */
    public $storage;


    /**
     * @param $id
     * @param array $params
     * @throws \Contributte\ImageStorage\Exception\ImageResizeException
     * @throws \Contributte\ImageStorage\Exception\ImageStorageException
     * @throws \JonnyW\PhantomJs\Exception\NotWritableException
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\UI\InvalidLinkException
     * @throws \Nette\Utils\ImageException
     * @throws \Nette\Utils\UnknownImageFileException
     * @throws \Ublaboo\ImageStorage\ImageResizeException
     */
    public function renderUpdate($id, $params = [])
    {

        /*
         * fix param from ajax
         */
        if (!$id)
            $id = $this->getHttpRequest()->getQuery('id');

        /** @var RouteEntity $routeEntity */
        if ($id && ($routeEntity = $this->routeRepository->find($id))) {

            $result = $this->phantomFacade->updateFromRoute($routeEntity);

            /** @var ImageEntity $imageEntity */
            $imageEntity = $result['image'];

            /** @var ResponseInterface $response */
            $response = $result['response'];

            if ($response->getStatus() == 200) {
                $this->payload->image  = $this->storage->fromIdentifier($params ? array_merge([$imageEntity->getIdentifier()], $params) : $imageEntity->getIdentifier());
            }

            $this->payload->result = $response->getStatus() == 200;
            $this->payload->reason = $response->getStatus();

        } else {
            $this->payload->result = false;
            $this->payload->reason = "id not found";
        }

        $this->sendPayload();
    }


}