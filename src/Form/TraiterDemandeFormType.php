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
    - La décision de l'administrateur sur une demande. Un seul formulaire pour les deux issues :
      elles portent sur le même objet et s'excluent, les présenter séparément ferait chercher
      « où est le bouton refuser ».

    - Ni le montant ni le motif ne sont obligatoires ICI : chacun ne l'est que pour SON issue, et
      une contrainte statique refuserait l'autre. Le contrôleur tranche selon la décision, et l'API
      revérifie de toute façon — c'est elle qui fait autorité.
*/
class TraiterDemandeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('decision', ChoiceType::class, [
                'label' => 'Décision',
                'choices' => [
                    'Approuver et doter la caisse' => 'approbation',
                    'Rejeter la demande' => 'rejet',
                ],
                'expanded' => true, // Deux boutons radio : la décision doit être lisible d'un coup d'œil, pas dépliée d'une liste
                'constraints' => [new NotNull()],
            ])
            ->add('montantAccorde', IntegerType::class, [
                'label' => 'Montant accordé (F CFA)',
                'help' => 'Il peut être inférieur au montant demandé. L\'argent quitte le solde de l\'entreprise.',
                'required' => false,
                'constraints' => [new Positive(message: 'Le montant accordé doit être supérieur à zéro')],
            ])
            ->add('motifRejet', TextareaType::class, [
                'label' => 'Motif du rejet',
                'help' => 'Obligatoire en cas de rejet : sans explication, l\'opérateur redemandera la même chose.',
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
        ]);
    }
}
