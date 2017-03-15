<?php

namespace Shopsys\ShopBundle\Form\Front\Cart;

use Shopsys\ShopBundle\Component\Constraints\ConstraintValue;
use Shopsys\ShopBundle\Form\FormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class CartFormType extends AbstractType
{
    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('quantities', FormType::COLLECTION, [
                'allow_add' => true,
                'allow_delete' => true,
                'type' => FormType::TEXT,
                'constraints' => [
                    new Constraints\All([
                        'constraints' => [
                            new Constraints\NotBlank(['message' => 'Please enter quantity']),
                            new Constraints\GreaterThan(['value' => 0, 'message' => 'Quantity must be greater than {{ compared_value }}']),
                            new Constraints\LessThanOrEqual([
                                'value' => ConstraintValue::INTEGER_MAX_VALUE,
                                'message' => 'Please enter valid quantity',
                            ]),
                        ],
                    ]),
                ],
            ])
            ->add('submit', FormType::SUBMIT);
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}