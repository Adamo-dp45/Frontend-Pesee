<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
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
            /*
                - Le mode EN PREMIER : il décide de la caisse qui se vide et de tout ce qui suit.
                  Les espèces sont proposées en tête parce que c'est le geste le plus simple — rien
                  à joindre, rien à attendre — et parce que c'est le seul recours pour un planteur
                  sans téléphone.
                - L'attribut 'data-mode-reglement' est lu par 'modules/mode_reglement.js' : il
                  masque les deux champs mobiles quand on choisit les espèces. Le formulaire reste
                  valable sans JavaScript, les champs sont alors simplement visibles et ignorés par
                  l'API.
            */
            ->add('modeReglement', ChoiceType::class, [
                'label' => 'Mode de règlement',
                'choices' => array_flip($options['modes']),
                'placeholder' => false,
                'constraints' => [new NotNull(message: 'Le mode de règlement est obligatoire')],
                'attr' => ['data-mode-reglement' => 'mode'],
            ])
            ->add('montant', IntegerType::class, [
                'label' => 'Montant à verser (F CFA)',
                'help' => 'Un planteur peut être payé en plusieurs fois : la pesée passe alors de « non payée » à « partielle », puis « soldée ».',
                'constraints' => [
                    new NotNull(),
                    new Positive(message: 'Le montant doit être supérieur à zéro'),
                ],
            ])
            /*
                - Les deux champs qui suivent ne servent qu'au MOBILE MONEY. Ils ne sont donc plus
                  obligatoires au niveau du formulaire : exiger un numéro pour remettre des billets
                  à quelqu'un qui est devant soi n'aurait servi qu'à rendre le mode espèces
                  inutilisable.
                - C'est le CONTRÔLEUR qui les exige quand le mode choisi est mobile : la règle vit à
                  un seul endroit, et l'erreur se pose sous le champ concerné plutôt qu'en bandeau.
            */
            ->add('numeroDestinataire', TextType::class, [
                'label' => 'Numéro mobile money',
                'help' => 'Celui du planteur, pré-rempli. Vérifiez-le avec lui avant d\'engager.',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
            ->add('reseau', ChoiceType::class, [
                'label' => 'Réseau',
                'choices' => array_combine($options['reseaux'], $options['reseaux']),
                'placeholder' => '— Choisir un réseau —',
                'required' => false,
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
            'modes' => [],
        ]);
    }
}
