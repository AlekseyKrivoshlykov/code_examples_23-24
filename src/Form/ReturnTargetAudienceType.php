<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Фильтр статистики возврата ЦА из предыдущей акции
 */
class ReturnTargetAudienceType extends AbstractType
{

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actionId', ChoiceType::class, [
                'label'       => 'Акция',
                'required'    => false,
                'choices'     => $options['activeActionsChoices'],
                'constraints' => [new Type(['type' => 'string'])],
            ])
            ->add('brandId', ChoiceType::class, [
                'label'       => 'Бренд',
                'required'    => false,
                'choices'     => $options['brandsChoices'],
                'constraints' => [new Type(['type' => 'string'])],
            ])
            ->add('dateStart', DateType::class, [
                'label'       => 'Дата начала',
                'widget'      => 'single_text',
                'required'    => false,
                'empty_data'  => $this->getDefaultDateStart(),
                'input'       => 'string',
                'row_attr'    => ['class' => 'field-date col-md-3',],
                'constraints' => [new Type(['type' => 'string'])],
            ])
            ->add('dateEnd', DateType::class, [
                'label'       => 'Дата окончания',
                'widget'      => 'single_text',
                'required'    => false,
                'empty_data'  => $this->getDefaultDateEnd(),
                'input'       => 'string',
                'row_attr'    => ['class' => 'field-date col-md-3',],
                'constraints' => [new Type(['type' => 'string'])],
            ])
        ;
        $castToString = new CallbackTransformer(
            function ($value) {
                return (string) $value;
            },
            function ($value) {
                return (string) $value;
            }
        );
        $builder->get('actionId')->addModelTransformer($castToString);
        $builder->get('brandId')->addModelTransformer($castToString);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data' => [
                'actionId'  => '',
                'brandId'   => '',
                'dateStart' => $this->getDefaultDateStart(),
                'dateEnd'   => $this->getDefaultDateEnd(),
            ],
        ]);
        $resolver->setRequired([
            'activeActionsChoices',
            'brandsChoices',
        ]);
        $resolver->setAllowedTypes('activeActionsChoices', 'array');
        $resolver->setAllowedTypes('brandsChoices', 'array');
    }

    /**
     * @return string
     */
    protected function getDefaultDateStart() : string
    {
        return date('Y-m-d', strtotime(date('Y-m-d') . ' -1 month'));
    }

    /**
     * @return string
     */
    protected function getDefaultDateEnd() : string
    {
        return date('Y-m-d');
    }
}
