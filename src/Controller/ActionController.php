<?php

namespace App\Controller;

use SomeClasses;

class ActionController extends AbstractController
{

    /**
     * @param \Doctrine\Persistence\ManagerRegistry $managerRegistry
     * @param \Twig\Environment $twigEnv
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag
     */
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        protected Environment $twigEnv,
        protected ParameterBagInterface $parameterBag,
        protected CrmSolApi $solApi,
    )
    {
    }

    //многое убрано, кроме самих методов

    /**
     * @Route("route", name="route_name")
     */
    public function completeActionSurvey(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Чтобы принять участие, необходимо авторизоваться',
            ]);
        }

        // если что подправить получаемые параметры из гугл-формы
        $sourceId = $request->request->get('source-id');
        $userHash = $request->request->get('hash');

        if (!$sourceId) {
            return $this->json([
                'status'  => 'error',
                'code'    => 'pa-g-er-1',
                'message' => 'SourceID  не может быть пустым',
            ]);
        }

        $actionRepo = $this->managerRegistry->getRepository(Action::class);
        $action = $actionRepo->findOneBy(['crmsolId' => $sourceId]);
        if (!$action) {
            return $this->json([
                'status'  => 'error',
                'code'    => 'pa-g-er-2',
                'message' => 'Нет акции с указанным SourceID',
            ]);
        }

        if (!$userHash) {
            return $this->json([
                'status'  => 'error',
                'code'    => 'pa-g-er-3',
                'message' => 'Не указан хэш',
            ]);
        }

        $asrRepo = $this->managerRegistry->getRepository(ActionSurveyResult::class);
        $actionSurveyResult = $asrRepo->findOneBy(['action' => $action, 'user' => $user]);
        if (!$actionSurveyResult) {
            return $this->json([
                'status'  => 'error',
                'code'    => 'pa-g-er-4',
                'message' => 'Нет опроса с указанным хэшем',
            ]);
        } else if (!is_null($actionSurveyResult->getEndedAt())) {
            return $this->json([
                'status'  => 'error',
                'code'    => 'pa-g-er-5',
                'message' => 'Опрос уже был пройден ранее',
            ]);

        } else {
            $now = time();
            $actionSurveyResult->setEndedAt($now);

            return $this->json([
                'status'  => 'ok',
                'code'    => 'pa-g-ok-1',
                'message' => 'Прохождение опроса зафиксировано',
            ]);
        }
    }
    
    /**
     * @Route("route", name="route_name")
     */
    public function registerActionSurvey(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Чтобы принять участие, необходимо авторизоваться',
            ]);
        }

        $actionId = $request->request->get('action-id');
        $userHash = $request->request->get('hash');
        $source   = $request->request->get('source');

        if (!$actionId || !$userHash || !$source) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером, попробуйте заново выполнить действие',
            ]);
        }

        $actionRepo = $this->managerRegistry->getRepository(Action::class);
        $asrRepo    = $this->managerRegistry->getRepository(ActionSurveyResult::class);
        $eManager   = $this->managerRegistry->getManager();

        $action = $actionRepo->find($actionId);
        if (!$action) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Нет акции с указанным ID',
            ]);
        }

        $asrObject = ActionSurveyResult::create($user, $action, $source, $userHash);
        $eManager->persist($asrObject);
        $user->addAction($action);
        $eManager->flush();

        if ($asrObject->getId()) {
            return $this->json([
                'status'  => 'ok',
                'message' => 'Опрос зарегистрирован',
            ]);
        } else {
            return $this->json([
                'status'  => 'error',
                'message' => 'Опрос не зарегистрирован, попробуйте снова',
            ]);
        }
    }
}
