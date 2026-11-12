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
    - La demande de mise en compte, déposée par l'opérateur pour le planteur qu'il a devant lui. Le
      planteur ne se choisit qu'au dépôt : déplacer une demande ouvrirait l'épargne de quelqu'un
      d'autre.

    - Le taux est en francs PAR KILO, et non un pourcentage ni une somme fixe. C'est ce que le
      planteur annonce au guichet, et c'est ce que l'administrateur approuvera tel quel.
*/
class DemandePrelevementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if($options['creation']) {
            $builder
                ->add('fournisseur', ChoiceType::class, [
                    'label' => 'Planteur',
                    'help' => 'Une seule demande en attente par planteur et par produit.',
                    'choices' => array_flip($options['planteurs']), // 'options()' rend 'id => libellé', ChoiceType attend l'inverse
                    'placeholder' => '— Choisir un planteur —',
                    'constraints' => [new NotNull()],
                ])
                /*
                    - Le produit ne se choisit qu'au dépôt, comme le planteur : c'est son prix qui
                      borne le taux, et déplacer une demande d'une culture à l'autre ferait approuver
                      un chiffre contre un prix que personne n'a regardé.
                */
                ->add('produit', ChoiceType::class, [
                    'label' => 'Produit',
                    'help' => 'Le prélèvement ne touchera que les pesées de ce produit. Un planteur peut en avoir plusieurs, à des taux différents.',
                    'choices' => array_flip($options['produits']),
                    'placeholder' => '— Choisir un produit —',
                    'constraints' => [new NotNull()],
                ])
            ;
        }

        $builder
            ->add('tauxDemande', IntegerType::class, [
                'label' => 'Prélèvement par kilo (F CFA)',
                'help' => 'Sur une pesée de 500 kg, un taux de 20 F met 10 000 F de côté. Il doit rester INFÉRIEUR au prix du kilo du produit, sinon le planteur repartirait les mains vides.',
                'constraints' => [
                    new NotNull(),
                    new Positive(message: 'Le taux demandé doit être supérieur à zéro'),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'help' => 'Ce que votre administrateur lit avant de décider. Ce que le planteur vous a dit suffit.',
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
            'planteurs' => [],
            'produits' => [],
            'creation' => false,
        ]);
    }
}
