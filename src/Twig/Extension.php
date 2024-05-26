<?php
namespace App\Twig\Common;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Сборище различных функций, используемых проектом Promonado
 */
class Extension extends AbstractExtension
{

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(
        protected ContainerInterface $container,
        protected ParameterBagInterface $parameterBag,
    )
    {
        $container->get('twig')->addGlobal('bodyjs', new \ArrayObject());
        $container->get('twig')->addGlobal('stylesheets', new \ArrayObject());

        $siteSettings = [];
        $filePath = $parameterBag->get('kernel.project_dir') . '/config/editable/site.php';
        if (file_exists($filePath)) {
            $siteSettings = include $filePath;
        }
        $container->get('twig')->addGlobal('site', new \ArrayObject($siteSettings));
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('makeMD5Hash', [$this, 'makeMD5Hash']),
        ];
    }

    /**
     * @param string $title
     * @return string
     */
    public function makeMD5Hash(string $title): string
    {
        $hashedStr = md5($title);

        return $hashedStr;
    }
}
