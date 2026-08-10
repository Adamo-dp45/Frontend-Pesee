<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/*
    - La mise en service d'un poste. Le code et l'entreprise ne se choisissent qu'à la création :
      le code est la clé de rapprochement avec le poste, et changer d'entreprise déplacerait la
      caisse et tout l'historique.
*/
class SiteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libellesite', TextType::class, [
                'label' => 'Libellé',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('localite', TextType::class, [
                'label' => 'Localité',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
        ;

        if(!$options['creation']) {
            return;
        }

        $builder
            ->add('code', TextType::class, [
                'label' => 'Code du poste',
                'help' => 'La clé de rapprochement avec le logiciel de pesée. Il ne pourra plus changer.',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 2, max: 50),
                ],
            ])
            ->add('entreprise', ChoiceType::class, [
                'label' => 'Entreprise',
                'choices' => array_flip($options['entreprises']), // 'options()' rend 'id => libellé', ChoiceType attend l'inverse
                'placeholder' => '— Choisir une entreprise —',
                'constraints' => [new NotNull()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'site',
            'entreprises' => [],
            'creation' => false,
        ]);
    }
}
