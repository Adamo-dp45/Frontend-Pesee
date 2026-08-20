<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/*
    - Le planteur. Ce que le web apporte et que le poste ne connaît pas : le numéro mobile money et
      son réseau. Sans eux la pesée s'enregistre, mais le versement est refusé.
*/
class FournisseurFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('codefournisseur', TextType::class, [
                'label' => 'Code',
                'help' => 'Le code du poste, s\'il en a un. Il sert à rapprocher les deux référentiels.',
                'required' => false,
                'constraints' => [new Length(max: 50)],
            ])
            ->add('contact1', TextType::class, [
                'label' => 'Numéro mobile money',
                'help' => 'C\'est sur ce numéro que part le versement. Dix chiffres, par exemple 07 01 02 03 04.',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
            ->add('contact2', TextType::class, [
                'label' => 'Autre numéro',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
            ->add('reseau', ChoiceType::class, [
                'label' => 'Réseau',
                'help' => 'Sans réseau, la passerelle ne sait pas où envoyer l\'argent.',
                'choices' => array_combine($options['reseaux'], $options['reseaux']),
                'placeholder' => '— Aucun —',
                'required' => false,
            ])
            ->add('prixspeciale', IntegerType::class, [
                'label' => 'Prix spécial au kilo (F CFA)',
                'help' => 'Laisser vide pour appliquer le prix du produit. Renseigné, il prime dessus.',
                'required' => false,
                'constraints' => [new GreaterThan(0)],
            ])
        ;

        // Le pont bascule ne se choisit qu'à la création : déplacer un planteur détacherait ses pesées de leur référentiel local
        if($options['creation']) {
            $builder->add('site', ChoiceType::class, [
                'label' => 'Pont bascule',
                'choices' => array_flip($options['sites']), // 'options()' rend 'id => libellé', ChoiceType attend l'inverse
                'placeholder' => '— Choisir un pont bascule —',
                'constraints' => [new NotNull()],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'fournisseur',
            'sites' => [],
            'reseaux' => [],
            'creation' => false,
        ]);
    }
}
