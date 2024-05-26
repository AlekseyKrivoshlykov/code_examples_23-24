<?php
namespace App\Controller\Admin;

use App\Entity\Tooltip;
use App\Repository\TooltipRepository;
use App\Service\AdminPermissionService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Обработка запросов из JS-скрипта создания и подгрузки подсказок
 */
class TooltipAjaxController extends AbstractController
{
    public function __construct(
        protected TooltipRepository $tooltipRepo,
        protected ManagerRegistry $doctrine,
    )
    {
    }

     // выпилено всё, кроме кастомных методов

    /**
     * @Route("route", name="route_name")
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request): Response
    {
        if (!$this->container->get('permissions')->hasAccessTo('Tooltip', 'edit')) {
            return new RedirectResponse('/admin/access/denied');
        }

        $title = $request->request->get('tooltip-title');
        $text  = $request->request->get('tooltip-text');
        $path  = $request->request->get('tooltip-path');
        $link  = $request->request->get('tooltip-link');
        // на данный момент не используется режим тултипа, но может будет в будущем
        // $mode  = $request->request->get('tooltip-mode');

        if (!$title || !$text || !$path || !$link) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Что-то пошло не так, попробуйте заново добавить подсказку',
            ]);
        }

        $tooltip = Tooltip::create($title, $text, $path, $link);

        $em = $this->doctrine->getManager();
        $em->persist($tooltip);
        $em->flush();

        if ($tooltip->getId()) {
            return $this->json([
                'status'  => 'ok',
                'message' => 'Подсказка успешно добавлена',
            ]);
        } else {
            return $this->json([
                'status'  => 'error',
                'message' => 'Что-то пошло не так, попробуйте заново добавить подсказку',
            ]);
        }
    }

    /**
     * @Route("route", name="route_name")
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loadAction(Request $request): Response
    {
        $link  = $request->query->get('link');
        $crud  = $request->query->get('crudControllerFqcn');
        $route = $request->query->get('routeName');
        
        if(!$link || !$crud || !$route) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером, перезагрузите страницу',
            ]);
        }

        $url   = $link . '&crudControllerFqcn=' . $crud . '&routeName=' . $route;
        $tooltips = $this->tooltipRepo->findBy(['url' => $url]);

        if (empty($tooltips)) {
            return $this->json([
                'status'   => 'ok',
                'message'  => 'Для данной страницы подсказок нет',
            ]);
        }

        return $this->json([
            'status'   => 'ok',
            'message'  => 'Подсказки загружены',
            'tooltips' => $tooltips,
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'permissions' => '?' . AdminPermissionService::class,
        ]);
    }
}
