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
            /*
                - La caisse AVANT le montant : « il me manque 200 000 » ne veut pas dire la même
                  chose selon qu'il s'agit de billets ou de flotte, et l'administrateur dote
                  exactement celle qu'on lui désigne. Une demande muette sur ce point l'obligeait à
                  deviner, ou à doter les deux.
            */
            ->add('caisse', ChoiceType::class, [
                'label' => 'Caisse à réapprovisionner',
                'help' => 'Celle qui est à sec. Être à court de billets pendant que le flotte déborde est courant.',
                'choices' => array_flip($options['caisses']),
                'placeholder' => '— Choisir une caisse —',
                'constraints' => [new NotNull(message: 'La caisse à réapprovisionner est obligatoire')],
            ])
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
            'caisses' => [],
            'creation' => false,
        ]);
    }
}
