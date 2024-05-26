<?php

namespace App\Controller;

use SomeClasses;

/**
 * @method \App\Entity\User getUser()
 */
class ProfileController extends AbstractController
{

    /**
     * @param \Doctrine\Persistence\ManagerRegistry $managerRegistry
     * @param \App\Service\CrmSolApi $solApi
     * @param \App\Repository\RegionRepository $regionRepo
     */
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        protected CrmSolApi $solApi,
        protected RegionRepository $regionRepo,
    )
    {
    }

    //многое убрано, кроме самих методов

    /**
     * @Route("route", name="route_name")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function loginDispatcherAction(Request $request)
    {
        $referrer = $request->headers->get('referer');
        $isUserCameFromSurvey = str_contains($referrer, 'signIn=1');

        $url = $request->getSession()->get('_security.user_secured_area.target_path');
        $isClubMember = $this->getUser()->getUserData()->isClubMember();

        if ($isUserCameFromSurvey && $isClubMember && $url) {
            return $this->redirect($url);
        }

        if ($isUserCameFromSurvey && $isClubMember === false) {
            return $this->redirectToRoute('app_profile_settings_club_edit');
        }

        return $this->redirectToRoute('app_profile_show');
    }

    /**
     * Добавляет по ссылке URL элемента акции в массив $linksByActivity
     * 
     * @param mixed $entity
     * @param array $itemLinks
     */
    public function addUrlToActionItem($entity, array $itemLinks): void
    {
        $ids = array_keys($itemLinks);
        $repo = $this->managerRegistry->getRepository($entity);
        $actionItems = $repo->findBy(['id' => $ids]);

        foreach ($actionItems as $aitem) {
            if ($aitem instanceof Receipt && $aitem->isFileRemoved()) {
                continue;
            }
            $itemLinks[$aitem->getId()] = '/' . $aitem->getFile();
        }
    }

    /**
     * @Route("route", name="route_name")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function settingsClubEditAction(Request $request, UserDataRepository $repo)
    {
        /** @var \App\Service\ClubFormHandler $clubFormHandler */
        $clubFormHandler = $this->container->get('clubformhandler');

        if ($clubFormHandler->saveForm($request)) {
            // это для аутентифицированного пользователя, который перешёл по ссылке опроса и дал согласие на опросы
            if($slug = $request->cookies->get('slug')) {
                $request->cookies->remove('slug');
                $url = $this->generateUrl('app_profile_survey', ['slug' => $slug]);
                return $this->redirect($url);
            }
            
            // это для неаутентифицированного пользователя, который перешёл по ссылке опроса
            $path = $request->getSession()->get('_security.user_secured_area.target_path');
            if(str_contains($path, 'profile/surveys')) {
                $request->getSession()->remove('_security.user_secured_area.target_path');
                return $this->redirect($path);
            }

            return $this->redirectToRoute('app_profile_settings');
        }
        return $this->render('club_edit.html.twig', [
            'form' => $clubFormHandler->showForm(),
        ]);
    }

    /**
     * @Route("route", name="route_name")
     */
    public function sendFeedbackAction(Request $request, ManagerRegistry $doctrine): JsonResponse 
    {
        $this->solApi->auth();
        $name      = $request->request->get('name');
        $email     = $request->request->get('email');
        $message   = $request->request->get('message');
        $info      = $request->headers->get('User-Agent');
        $actionId  = $request->request->getInt('action_id');

        if (gettype($name) !== 'string') {
            $name = (string) $name;
        }
        if (gettype($email) !== 'string') {
            $email = (string) $email;
        }
        $escapedMess = addslashes($message);
                
        $entityManager = $doctrine->getManager();
        $user = $this->getUser();
        $action = $doctrine->getRepository(Action::class)->find($actionId);

        $solRes = $this->solApi->addLead([
            'givenName' => $name,
            'email'     => $email,
            'theme'     => $action->getName(),
            'message'   => $message,
        ]);

        if ($solRes['code'] === 0) {
            $item = new Feedback();
            $item->setName($name);
            $item->setEmail($email);
            $item->setMessage($escapedMess);
            $item->setUser($user);
            $item->setAction($action);
            $item->setInfo($info);

            $entityManager->persist($item);
            $entityManager->flush();

            if ($item->getId() > 0) {
                return $this->json(['status' => 'success']);
            }
        }

        return $this->json(['status' => 'error']);
    }

    /**
     * Возвращает сообщение об ошибке при некорректной отправке данных в SOL
     * @param mixed $solResponse ответ SOL
     * @param string $flashType тип сообщения для адресации на нужную страницу
     * @param string $unknownErrorContext контекст для неизвестной ошибки
     * @param string $errorContext контекст для известной ошибки
     */
    protected function checkForErrorSolApi(
        $solResponse, string $flashType, string $unknownErrorContext, string $errorContext
    )
    {
        if (empty($solResponse) || !isset($solResponse['code'])) {
            $this->addFlash($flashType, 'Неизвестная ошибка API при сохранении'. $unknownErrorContext);
            return false;
        }

        if ($solResponse['code'] !== 0) {
            $this->addFlash(
                $flashType, 
                $errorContext . ': ' . $this->solApi->describeError($solResponse['code'])
            );
            return false;
        }
    }

    /**
     * @Route("route", name="route_name")
     */
    public function sendReceiptFeedbackAction(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $isUpdateProfile = $this->updateProfileFromSol();
        if (!$isUpdateProfile) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Что-то пошло не так, попробуйте отправить сообщение ещё раз.',
            ]);
        }

        $user      = $this->getUser();
        $whiteId   = $user->getWhiteId();
        $message   = $request->request->get('message-error-text');
        $actionId  = $request->request->get('action_id');
        $info      = $request->headers->get('User-Agent');
        $status    = ReceiptFeedbackStatusesRegistry::STATUS_NEW;

        if (empty($message)) {
            $this->json([
                'status'  => 'error',
                'message' => 'Введите текст сообщения перед отправкой',
            ]);
        }
        if (empty($actionId)) {
            $this->json([
                'status'  => 'error',
                'message' => 'Сообщение должно быть связано с акцией',
            ]);
        }
        $object = new ReceiptFeedback();
        $object->setUser($user);
        $object->setWhiteId($whiteId);
        $object->setMessage($message);
        $object->setInfo($info);
        $object->setStatus($status);
        if (!empty($actionId)) {
            $actionRepo = $doctrine->getRepository(Action::class);
            if ($action = $actionRepo->find($actionId)) {
                $object->setAction($action);
            }
        }

        $eManager = $doctrine->getManager();
        $eManager->persist($object);
        $eManager->flush();

        if ($object->getId() > 0) {
            return $this->json(['status' => 'success']);
        }
        
        return $this->json(['status' => 'error']);
    }

    /**
     * @Route("route", name="route_name")
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function sendSurveyAfterReceiptAction(
        Request $request, 
        ActionRepository $actionRepository,
    ): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'status'  => 'error',
                'message' => 'noAuth',
            ]);
        }

        $whiteId = $this->getUser()->getWhiteId();
        if (empty($whiteId)) {
            return $this->json([
                'status'  => 'error',
                'message' => 'noAuth',
            ]);
        }

        $actionId    = $request->request->get('actionId');
        $rating      = $request->request->get('rating');
        $reason      = $request->request->get('reason');
        $source      = $request->request->get('source');

        if (!$rating || !$reason || !$source) {
            return $this->json([
                'status'  => 'error',
                'message' => 'noData',
            ]);
        }

        $action      = $actionRepository->find($actionId);
        $eManager    = $this->managerRegistry->getManager();

        $object = new SurveyAfterReceipt();
        $object->setUser($user);
        $object->setWhiteId($whiteId);
        $object->setAction($action);
        $object->setRating($rating);
        $object->setReasonForParticipation(implode('; ', (array) $reason));
        $object->setSourceInfo(implode('; ', (array) $source));

        $eManager->persist($object);
        $eManager->flush($object);

        if ($object->getId() > 0) {
            return $this->json(['status' => 'success']);
        }

        return $this->json(['status' => 'error']);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'clubformhandler' => '?' . ClubFormHandler::class,
        ]);
    }
}
