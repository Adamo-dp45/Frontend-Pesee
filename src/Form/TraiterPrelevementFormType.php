<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

/*
    - La décision de l'administrateur : ouvrir, ou refuser. Les deux dans le même formulaire parce
      qu'elles portent sur le même objet et s'excluent.

    - AUCUN champ de taux, et c'est voulu. Contrairement à une demande de solde, où l'administrateur
      peut accorder moins que demandé, le taux vient du planteur : le retoucher depuis un bureau
      reviendrait à décider de l'épargne de quelqu'un d'autre. On approuve ce qui a été demandé, ou
      on explique pourquoi on refuse.

    - Le motif de rejet n'est pas obligatoire ICI : il ne l'est que pour le rejet, et c'est l'API
      qui tranche, avec un message qui dit pourquoi.
*/
class TraiterPrelevementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('decision', ChoiceType::class, [
                'label' => 'Décision',
                'choices' => [
                    'Ouvrir la mise en compte' => 'approbation',
                    'Rejeter la demande' => 'rejet',
                ],
                'expanded' => true,
                'constraints' => [new NotNull()],
            ])
            ->add('motifRejet', TextareaType::class, [
                'label' => 'Motif du rejet',
                'help' => 'Obligatoire en cas de rejet : sans explication, l\'opérateur ne sait pas quoi dire au planteur.',
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
