<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/*
    - Le produit pesé. Le pont bascule ne se choisit qu'à la création : le déplacer détacherait les
      pesées déjà enregistrées de leur référentiel local, d'où l'option 'creation'.
*/
class ProduitFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'help' => 'Tel qu\'il apparaît sur le poste de pesée.',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('codeproduit', TextType::class, [
                'label' => 'Code',
                'help' => 'Le code du poste, s\'il en a un. Il sert à rapprocher les deux référentiels.',
                'required' => false,
                'constraints' => [new Length(max: 50)],
            ])
            ->add('prix', IntegerType::class, [
                'label' => 'Prix au kilo (F CFA)',
                'help' => 'À zéro, la pesée s\'enregistre mais le versement est refusé.',
                'constraints' => [
                    new NotNull(),
                    new GreaterThanOrEqual(0),
                ],
            ])
        ;

        if($options['creation']) {
            $builder->add('site', ChoiceType::class, [
                'label' => 'Pont bascule',
                'choices' => array_flip($options['sites']), // 'options()' rend 'id => libellé', ChoiceType attend l'inverse
                'placeholder' => '— Choisir un pont bascule —',
                'constraints' => [new NotNull()],
            ]);

            return; // Un produit naît actif : le désactiver est une décision qui vient ensuite
        }

        $builder->add('actif', CheckboxType::class, [
            'label' => 'Actif',
            'help' => 'Un produit inactif reste dans l\'historique mais n\'est plus proposé.',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'produit',
            'sites' => [],
            'creation' => false,
        ]);
    }
}
