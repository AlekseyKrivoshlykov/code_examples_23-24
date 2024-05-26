<?php

namespace App\Controller;

use SomeClasses;

class PromocodeProcessingController extends AbstractController
{

    /**
     * @param \App\Repository\PromocodeRepository $promocodeRepo
     * @param \App\Repository\UserPromocodeRepository $userPromocodeRepo
     * @param \Doctrine\Persistence\ManagerRegistry $doctrine
     */
    public function __construct(
        protected PromocodeRepository $promocodeRepo,
        protected UserPromocodeRepository $userPromocodeRepo,
        protected ManagerRegistry $doctrine,
    )
    {
    }
    //многое убрано, кроме самих методов

    /**
     * @Route("route", name="route_name")
     */
    public function promocodeUploadAction(
        Request $request,
        ActionRepository $actionRepo,
    ): JsonResponse
    {
        // проверка пользователя на блокировку
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $isUserEnabled = $user->getEnabled();

        if (!$isUserEnabled) {
            return $this->json([
                'status'  => 'error',
                'message' => 'userBlocked',
            ], 403);
        }

        $userCode = $request->request->get('user_promocode');
        $actionId = $request->request->get('action_id');

        if (!$userCode || !$actionId) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером, попробуйте загрузить промокод заново',
            ]);
        }
        
        $action = $actionRepo->find($actionId);

        if (!$action) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Загрузка не удалась, попробуйте снова',
            ]);
        }

        $actionType = $action->getPromoType();

        if ($actionType === ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE ||
            $actionType === ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE
        ) 
        {
            if ($actionLimitDaily = $action->getLimitDaily()) {
                $countOfDownLoads = $this->getDailyCountOfPromocodes($action, $user);

                if ($countOfDownLoads >= $actionLimitDaily) {
                    return $this->json([
                        'status'  => 'error',
                        'message' => 'Достигнут дневной лимит акции',
                    ]);
                }
            }

            if ($actionLimitTotal = $action->getLimitTotal()) {
                $countOfDownLoads = $this->getCountOfPromocodes($action, $user);

                if ($countOfDownLoads >= $actionLimitTotal) {
                    return $this->json([
                        'status'  => 'error',
                        'message' => 'Достигнут лимит акции',
                    ]);
                }
            }

            $userCodeObject  = UserPromocode::create($user, $action, $userCode);
            $responseStatus  = '';
            $responseMessage = '';
            $invalidLimit = $action->getInvalidPromocodesLimit();

            if ($this->isPromocodeInDB($actionId, $userCode)) {
                if ($this->isNeedToBlock($action, $user)) {
                    return $this->json([
                        'status'  => 'error',
                        'message' => 'Вы нарушили правила акции. Ваш аккаунт заблокирован на 24 часа',
                    ]);
                }

                $conditionPromoType = null;

                switch ($actionType) {
                    case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                        $conditionPromoType = $this->userPromocodeRepo->findOneBy([
                            'user'   => $user,
                            'action' => $action,
                            'code'   => $userCode,
                        ]);
        
                    break;

                    case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                        $conditionPromoType = $this->userPromocodeRepo->findOneBy([
                            'action' => $action,
                            'code'   => $userCode,
                        ]);

                    break;
                }

                if ($conditionPromoType && $invalidLimit) {
                    $userCodeObject->setStatus(UserPromocodeStatusesRegistry::STATUS_REJECTED);
                    $responseStatus  = 'error';
                    $responseMessage = 'Вы ввели повторно промокод, ранее принятый к участию. Это нарушение правил акции, если продолжить это делать, произойдет блокировка вашего аккаунта на 24 часа.';
                } else {
                    $userCodeObject->setStatus(UserPromocodeStatusesRegistry::STATUS_ACCEPTED);
                    $responseStatus  = 'success';
                    $responseMessage = 'Ваш промокод успешно загружен!';
                }
            } else {
                if ($this->isNeedToBlock($action, $user)) {
                    return $this->json([
                        'status'  => 'error',
                        'message' => 'Вы нарушили правила акции. Ваш аккаунт заблокирован на 24 часа',
                    ]);
                }

                $userCodeObject->setStatus(UserPromocodeStatusesRegistry::STATUS_INVALID_CODE);
                $countDownloadCodes = $this->getCountOfUserPromocode($action, $user, $userCode);

                if ($invalidLimit && ($countDownloadCodes == $invalidLimit - 1)) {
                    $responseStatus  = 'error';
                    $responseMessage = 'Вы ввели повторно неверный промокод. Если продолжить это делать, произойдет блокировка вашего аккаунта на 24 часа.';
                } else {
                    $responseStatus  = 'error';
                    $responseMessage = 'Промокод не найден в системе, введите корректный промокод';
                }     
            }

            $this->userPromocodeRepo->add($userCodeObject, true);

            if (!$userCodeObject->getId()) {
                $responseStatus  = 'error';
                $responseMessage = 'Произошла ошибка загрузки промокода, попробуйте снова';
            }

            return $this->json([
                'status'  => $responseStatus,
                'message' => $responseMessage,
            ]);
        } else {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данная акция не предполагает загрузку промокода',
            ]);
        }
    }

    /**
     * @param int $actionId
     * @param string $userCode
     * @return bool
     */
    public function isPromocodeInDB(int $actionId, string $userCode): bool
    {  
        $code = $this->promocodeRepo->findOneBy([
            'action' => $actionId,
            'code'   => $userCode,
        ]);

        if (!$code) {
            return false;
        }

        return true;
    }

    /**
     * @param Action $action
     * @param User $user
     * @return int
     */
    public function getDailyCountOfPromocodes(Action $action, User $user): int
    {
        $currentDate = new DateTime();
        $dateStart   = $currentDate->format('Y-m-d 00:00:00');
        $dateEnd     = $currentDate->format('Y-m-d 23:59:59');

        $quantityOfPromo = 
            $this->userPromocodeRepo->createQueryBuilder('up')
                ->andWhere('up.field1 = :action')
                ->andWhere('up.field2 = :user')
                ->andWhere('up.createdAt >= :dateStart')
                ->andWhere('up.createdAt <= :dateEnd')
                ->setParameters([
                    'action'    => $action,
                    'user'      => $user,
                    'dateStart' => strtotime($dateStart),
                    'dateEnd'   => strtotime($dateEnd),
                ])
                ->select('count(up.id)')
                ->getQuery()
                ->getSingleScalarResult()
        ;

        return $quantityOfPromo;
    }

    /**
     * @param Action $action
     * @param User $user
     * @return int
     */
    public function getCountOfPromocodes(Action $action, User $user): int
    {
        $quantityOfPromo = 
            $this->userPromocodeRepo->createQueryBuilder('up') 
                ->andWhere('up.field1 = :action')
                ->andWhere('up.field2 = :user')
                ->setParameters([
                    'action'    => $action,
                    'user'      => $user,
                ])
                ->select('count(up.id)')
                ->getQuery()
                ->getSingleScalarResult()
        ;
        
        return $quantityOfPromo;
    }

    /**
     * Возвращает кол-во загрузок невалидных промокодов
     * 
     * @param Action $action
     * @param User $user
     * @return int
     */
    public function getCountOfUserPromocode(Action $action, User $user): int
    {
        $currentDate = new DateTime();
        $dateStart   = $currentDate->format('Y-m-d 00:00:00');
        $dateEnd     = $currentDate->format('Y-m-d 23:59:59');

        $quantity = 
            $this->userPromocodeRepo->createQueryBuilder('up') 
                ->andWhere('up.field1 = :action')
                ->andWhere('up.field2 = :user')
                ->andWhere('up.status = :statusInvalid OR up.status = :statusRejected')
                ->andWhere('up.createdAt >= :dateStart')
                ->andWhere('up.createdAt <= :dateEnd')
                ->setParameters([
                    'action'         => $action,
                    'user'           => $user,
                    'statusInvalid'  => UserPromocodeStatusesRegistry::STATUS_INVALID_CODE,
                    'statusRejected' => UserPromocodeStatusesRegistry::STATUS_REJECTED,
                    'dateStart'      => strtotime($dateStart),
                    'dateEnd'        => strtotime($dateEnd),
                ])
                ->select('count(up.id)')
                ->getQuery()
                ->getSingleScalarResult()
        ;
        
        return $quantity;
    }

    /**
     * Нужно ли блокировать пользователя
     * 
     * @param Action $action
     * @param User $user
     * @param string $userCode
     * @return bool
     */
    public function isNeedToBlock(Action $action, User $user): bool
    {
        if (
            $action->getInvalidPromocodesLimit() &&
            ($this->getCountOfUserPromocode($action, $user) >= $action->getInvalidPromocodesLimit())
        ) {
            $date = new DateTime('now');
            $date->modify('1day');
            $user->setEnabled(false);
            $user->setDisabledBefore($date);
            $em = $this->doctrine->getManager();
            $em->persist($user);
            $em->flush();

            return true;
        } else {
            return false;
        }
    }
}
