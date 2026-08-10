<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/*
    - L'identité d'un client déjà inscrit. Uniquement la MODIFICATION : une entreprise naît avec son
      administrateur, par 'InscriptionFormType', parce que créer l'entreprise seule laisse un client
      que personne ne peut ouvrir.
    - Le code n'y figure donc pas : il se saisit à l'inscription et ne change plus jamais, tout le
      reste s'y réfère, y compris les codes de ses postes.
*/
class EntrepriseFormType extends AbstractType
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
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('contact1', TextType::class, [
                'label' => 'Contact principal',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
            ->add('contact2', TextType::class, [
                'label' => 'Autre contact',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'required' => false,
                'constraints' => [new Email()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'entreprise',
        ]);
    }
}
