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
    - Recharge du solde de l'entreprise : une entrée d'argent venue de l'extérieur, saisie à la main
      par l'administrateur et historisée comme tout le reste.
*/
class RechargeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            /*
                - La caisse EN PREMIER, et sans valeur pré-cochée : c'est elle qui décide de ce que
                  l'argent pourra payer, et une case déjà cochée se valide sans être lue. Les deux
                  caisses ne se transvasent pas — se tromper ici oblige à repasser par la banque ou
                  par l'agrégateur.
            */
            ->add('caisse', ChoiceType::class, [
                'label' => 'Caisse à alimenter',
                'help' => 'Les espèces et le flotte mobile money ne se convertissent pas l\'un en l\'autre.',
                'choices' => array_flip($options['caisses']),
                'placeholder' => '— Choisir une caisse —',
                'constraints' => [new NotNull(message: 'La caisse à alimenter est obligatoire')],
            ])
            ->add('montant', IntegerType::class, [
                'label' => 'Montant (F CFA)',
                'help' => 'Ce que l\'entreprise ajoute à cette caisse, avant d\'en doter ses ponts bascules.',
                'constraints' => [
                    new NotNull(),
                    new Positive(message: 'Le montant doit être supérieur à zéro'),
                ],
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif',
                'help' => 'Ce qui permettra de retrouver l\'origine de cette entrée dans le grand livre.',
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
            'csrf_token_id' => 'caisse',
            'caisses' => [],
        ]);
    }
}
