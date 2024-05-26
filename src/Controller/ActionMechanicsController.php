<?php

namespace App\Controller;

use SomeClasses;

class ActionMechanicsController extends AbstractController
{
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        protected ActionContestItemRepository $actionContestItemRepo,
        protected ActionContestVoteRepository $actionContestVoteRepo,
        protected ActionRepository $actionRepo,
    )
    {
    }

    //многое убрано, кроме самих методов

    /**
     * @Route("route", methods={"POST"}, name="route_name")
     */
    public function actionContestItemUpload(
        int $actionId,
        Request $request,
    ): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // проверка пользователя на блокировку
        $isUserEnabled = $user->getEnabled();

        if (!$isUserEnabled) {
            return $this->json([
                'status'  => 'error',
                'message' => 'userBlocked',
            ], 403);
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file  = $request->files->get('file');
        $title = $request->request->get('title');

        if (!$file) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Что-то пошло не так. Попробуйте снова',
            ]);
        }

        if (filesize($file) >= 10*1000000) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Файл не должен превышать 10мб',
            ]);
        }

        if (!in_array($file->getClientMimeType(), ['image/jpg', 'image/jpeg', 'image/png'])) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Файл не является изображением допустимого формата. Допустимы только форматы jpg, png',
            ]);
        }

        $action  = $this->actionRepo->find($actionId);
        
        if ($actionLimitDaily = $action->getLimitDaily()) {
            $countOfDownloads = 
                $this->getDailyCountOfActionItemDownloads(ActionContestItem::class, $action, $user);

            if ($countOfDownloads >= $actionLimitDaily) {
                return $this->json([
                    'status'  => 'error',
                    'message' => 'Достигнут дневной лимит акции',
                ]);
            }
        }

        if ($actionLimitTotal = $action->getLimitTotal()) {
            $countOfDownloads = 
                $this->getCountOfActionItemDownloads(ActionContestItem::class, $action, $user);
            
            if ($countOfDownloads >= $actionLimitTotal) {
                return $this->json([
                    'status' => 'error',
                    'message'  => 'Достигнут лимит акции',
                ]);
            }
        }

        $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $this->getParameter('kernel.project_dir') . 'customdirectory';
        $file->move($filePath, $fileName);

        if (empty($title)) {
            $title = $file->getClientOriginalName();
        }
        $actionContestItem = ActionContestItem::create($user, $action, $fileName, $title);
        $this->actionContestItemRepo->add($actionContestItem, true);

        if ($actionContestItem->getId() > 0) {
            return $this->json([
                'status'   => 'success',
                'filename' => $fileName,
                'path'     => $actionContestItem->getFile(),
            ]);
        }
    }

    /**
     * @Route("route", methods={"POST"}, name="route_name")
     */
    public function actionContestVoteUpload(
        int $contestActionId, 
        Request $request
    ): JsonResponse
    {
        $actionId = $request->request->get('actionId');

        if (!$contestActionId || !$actionId) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Что-то пошло не так. Попробуйте снова',
            ]);
        }

        $userIp = $request->getClientIp();
        $isIpVoted = $this->actionContestVoteRepo->findOneBy([
                        'action'    => $actionId,
                        'ipAddress' => $userIp,
        ]);

        if ($isIpVoted) {
            return $this->json([
                'status'  => 'error',
                'message' => 'С этого IP адреса уже проголосовали', 
            ]);
        }

        $user = $this->getUser();
        $isUserVoted = $this->actionContestVoteRepo->findOneBy([
                        'action' => $actionId,
                        'user'   => $user,
        ]);

        if ($isUserVoted) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Вы уже проголосовали за творческую работу в этой акции', 
            ]);
        }

        $action = $this->actionRepo->find($actionId);
        $actionContestItem = $this->actionContestItemRepo->find($contestActionId);

        $actionContestVote = ActionContestVote::create($user, $actionContestItem, $action, $userIp);
        $this->actionContestVoteRepo->add($actionContestVote, true);

        $this->managerRegistry->resetManager();
        $actionContestItem = $this->actionContestItemRepo->find($contestActionId);

        if ($actionContestVote->getId() > 0) {
            return $this->json([
                'status'   => 'success',
                'message'  => 'Ваш голос учтён!',
                'votes'    => $actionContestItem->getVotes(),
            ]);
        }
    }

    /** 
     * @param $entity
     * @param Action $action
     * @param User $user
     * @return int
     */
    protected function getDailyCountOfActionItemDownloads(
        $entity, Action $action, User $user
    ): int
    {
        $qb = $this->managerRegistry->getManager()->getRepository($entity)->createQueryBuilder('entity');

        $currentDate = new DateTime();
        $dateStart = $currentDate->format('Y-m-d 00:00:00');
        $dateEnd   = $currentDate->format('Y-m-d 23:59:59');

        $quantity = 
                    $qb
                        ->andWhere('entity.field1 = :action')
                        ->andWhere('entity.field2 = :user')
                        ->andWhere('entity.createdAt >= :dateStart')
                        ->andWhere('entity.createdAt <= :dateEnd')
                        ->setParameters([
                            'action'    => $action,
                            'user'      => $user,
                            'dateStart' => strtotime($dateStart),
                            'dateEnd'   => strtotime($dateEnd),
                        ])
                        ->select('count(entity.id)')
                        ->getQuery()
                        ->getSingleScalarResult()
        ;
        
        return $quantity;
    }

    /** 
     * @param $entity
     * @param Action $action
     * @param User $user
     * @return int
     */
    protected function getCountOfActionItemDownloads(
        $entity, Action $action, User $user
    ): int
    {
        $qb = $this->managerRegistry->getManager()->getRepository($entity)->createQueryBuilder('entity');
     
        $quantity = 
                    $qb
                        ->andWhere('entity.field1 = :action')
                        ->andWhere('entity.field2 = :user')
                        ->setParameters([
                            'action'    => $action,
                            'user'      => $user,
                        ])
                        ->select('count(entity.id)')
                        ->getQuery()
                        ->getSingleScalarResult()
        ;
        
        return $quantity;
    }
}
