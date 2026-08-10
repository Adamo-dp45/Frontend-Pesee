<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

/*
    - La demande de réapprovisionnement de l'opérateur. Le pont bascule ne se choisit qu'au dépôt :
      déplacer une demande la ferait payer par la mauvaise caisse.
*/
class DemandeSoldeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if($options['creation']) {
            $builder->add('site', ChoiceType::class, [
                'label' => 'Pont bascule',
                'help' => 'Une seule demande en attente par poste : corrigez ou annulez la précédente avant d\'en déposer une autre.',
                'choices' => array_flip($options['sites']), // 'options()' rend 'id => libellé', ChoiceType attend l'inverse
                'placeholder' => '— Choisir un pont bascule —',
                'constraints' => [new NotNull()],
            ]);
        }

        $builder
            ->add('montantDemande', IntegerType::class, [
                'label' => 'Montant demandé (F CFA)',
                'constraints' => [
                    new NotNull(),
                    new Positive(message: 'Le montant demandé doit être supérieur à zéro'),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'help' => 'Ce que votre administrateur lit avant de décider. Un motif précis se traite plus vite.',
                'required' => false,
                'constraints' => [new Length(max: 500)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'demande',
            'sites' => [],
            'creation' => false,
        ]);
    }
}
