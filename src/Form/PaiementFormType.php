<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

/*
    - Le versement à un planteur, par mobile money. Rien n'est automatique : c'est un opérateur qui
      le déclenche, le planteur devant lui.

    - Le montant n'est pas borné ici au reste à payer : c'est l'API qui tranche, et elle seule
      connaît les versements EN VOL — ceux déjà sortis de la caisse mais pas encore confirmés. Une
      borne calculée à l'affichage laisserait engager deux fois la même somme.
*/
class PaiementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', IntegerType::class, [
                'label' => 'Montant à verser (F CFA)',
                'help' => 'Un planteur peut être payé en plusieurs fois : la pesée passe alors de « non payée » à « partielle », puis « soldée ».',
                'constraints' => [
                    new NotNull(),
                    new Positive(message: 'Le montant doit être supérieur à zéro'),
                ],
            ])
            ->add('numeroDestinataire', TextType::class, [
                'label' => 'Numéro mobile money',
                'help' => 'Celui du planteur, pré-rempli. Vérifiez-le avec lui avant d\'engager.',
                'constraints' => [
                    new NotBlank(message: 'Le numéro du destinataire est obligatoire'),
                    new Length(max: 30),
                ],
            ])
            ->add('reseau', ChoiceType::class, [
                'label' => 'Réseau',
                'choices' => array_combine($options['reseaux'], $options['reseaux']),
                'placeholder' => '— Choisir un réseau —',
                'constraints' => [new NotNull(message: 'Le réseau est obligatoire : sans lui, la passerelle ne sait pas où envoyer l\'argent')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'paiement',
            'reseaux' => [],
        ]);
    }
}
