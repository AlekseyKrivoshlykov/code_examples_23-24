<?php

namespace App\Controller\Admin;

use SomeClasses;

class ReceiptCrudController extends AbstractCrudController
{
    // выпилено всё, кроме кастомных методов

    /**
     * @Route("route", name="route_name")
     */
    public function checkUserBrand(
        Request $request, UserRepository $userRepo, BrandRepository $brandRepo, ActionRepository $actionRepo,
    ): JsonResponse
    {
        if (!$this->container->get('permissions')->hasAccessTo('Receipt', 'edit')) {
            return new RedirectResponse('/admin/access/denied');
        }

        $whiteId      = $request->get('white_id');
        $actionName   = $request->get('action_name');

        if (!$whiteId || !$actionName) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером, попробуйте снова',
            ]);
        }

        $user         = $userRepo->findOneBy(['whiteId' => $whiteId]);
        $hasUserBrand = $user->getUserData()->getBrand();

        if ($hasUserBrand) {
            return $this->json([
                'status'  => 'success',
                'message' => 'userHasBrand',
            ]);
        }

        $action = $actionRepo->findOneBy(['name' => $actionName]);
        $brand  = $action->getBrand();

        if ($brand) {
            if ($brand->getTitle() !== 'text') {
                $brands[] = $brand;
            } else {
                $brands = $brandRepo->findAll();
            }
        } else {
            $brands = null;
        }
        
        return $this->json([
            'status'  => 'success',
            'message' => 'userHasNotBrand',
            'brands'  => $brands,
            ],
            200,
            [],
            ['groups' => 'user-brand']
        );
    }

    /**
     * @Route("route", name="route_name")
     */
    public function addBrandToUser(
        Request $request, UserRepository $userRepo, BrandRepository $brandRepo, CrmSolApi $solApi,
        ManagerRegistry $doctrine
    ): JsonResponse
    {
        $brandId = $request->get('brand_id');
        $whiteId = $request->get('white_id');

        if (!$brandId || !$whiteId) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером. Пожалуйста, попробуйте отправить их снова',
            ]);
        }

        $brand = $brandRepo->find($brandId);
        $brandSolId = $brand->getSolId();

        try {
            $authToken   = $solApi->auth();
            $solResponse = $solApi->setBrand($authToken, $whiteId, $brandSolId);

            if (isset($solResponse['code']) && $solResponse['code'] === 10053 ||
                !isset($solResponse['userData'])
            ) {
                return $this->json([
                    'status'  => 'error',
                    'message' => 'Пользователь не найден, попробуйте отправить данные снова',
                ]);
            }
        } catch (Error $error) {
            return $this->json([
                'status'  => 'error',
                'message' => $error->getMessage(),
            ]);
        }

       $user     = $userRepo->findOneBy(['whiteId' => $whiteId]);
       $userData = $user->getUserData();
       $userData->setBrand($brand);

       $eManager = $doctrine->getManager();
       $eManager->persist($userData);
       $eManager->flush($userData);

        if (is_null($userData->getBrand())) {
            return $this->json(['status' => 'error']);
        }

        return $this->json(['status' => 'success']);
    }
}
