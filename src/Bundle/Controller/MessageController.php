<?php

/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Endroid\CmSms\Bundle\Controller;

use Doctrine\ORM\EntityManager;
use Endroid\CmSms\Bundle\Entity\Status;
use Endroid\CmSms\Bundle\Repository\MessageRepository;
use Endroid\CmSms\Bundle\Repository\StatusRepository;
use Endroid\CmSms\Client;
use Endroid\CmSms\Exception\RequestException;
use Endroid\CmSms\Message;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/")
 */
class MessageController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     *
     * @return array
     */
    public function indexAction()
    {
        // Disable web profiler when using React
        if ($this->has('profiler')) {
            $this->get('profiler')->disable();
        }

        return [];
    }

    /**
     * @Route("/state")
     * @Template()
     *
     * @return Response
     */
    public function stateAction()
    {
        $messages = $this->getMessageRepository()->findAll();

        $serializer = $this->get('jms_serializer');

        return new Response(
            $serializer->serialize(['messages' => $messages], 'json'),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @Route("/update-status")
     *
     * @param Request $request
     * @return Response
     */
    public function updateStatusAction(Request $request)
    {
        $data = $request->getMethod() === Request::METHOD_GET ? $request->query->all() : $request->request->all();

        try {
            $status = Status::fromArray($data);
            $this->getStatusRepository()->save($status);
        } catch (Exception $exception) {
            // do nothing
        }

        return new Response();
    }

    /**
     * @Route("/send-test/{phoneNumber}")
     *
     * @param string $phoneNumber
     * @return Response|array
     */
    public function testAction($phoneNumber)
    {
        $message = new Message();
        $message->addTo($phoneNumber);
        $message->setBody('Test message');

        $client = $this->getSmsClient();

        try {
            $this->getMessageRepository()->save($message);
            $client->sendMessage($message);
            $message->setSent(true);
            $this->getMessageRepository()->save($message);
        } catch (RequestException $exception) {
            // ...
        }

        return new JsonResponse($message);
    }

    /**
     * @return Client
     */
    protected function getSmsClient()
    {
        return $this->get('endroid.cm_sms.client');
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getDoctrine()->getManager();
    }

    /**
     * @return MessageRepository
     */
    protected function getMessageRepository()
    {
        return $this->getDoctrine()->getRepository('EndroidCmSmsBundle:Message');
    }

    /**
     * @return StatusRepository
     */
    protected function getStatusRepository()
    {
        return $this->getDoctrine()->getRepository('EndroidCmSmsBundle:Status');
    }
}