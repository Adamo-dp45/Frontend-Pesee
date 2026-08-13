<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/*
    - Un compte de l'entreprise. Le rôle et le mot de passe ne figurent qu'à la création : ensuite le
      rôle a son propre verbe ('/utilisateurs/{id}/role'), parce qu'on ne se promeut pas soi-même et
      qu'un agent n'attribue que le rôle opérateur ; et le mot de passe se réinitialise, il ne
      s'édite pas.
*/
class UtilisateurFormType extends AbstractType
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
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'help' => 'C\'est avec elle qu\'il se connecte.',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [new Length(max: 30)],
            ])
        ;

        if(!$options['creation']) {
            return;
        }

        $builder
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                // Le minimum est celui de l'API : annoncer 8 ici alors qu'elle en exigeait 10 faisait passer la saisie pour valide, puis refuser à l'enregistrement
                'help' => '6 caractères minimum, dont une minuscule, une majuscule et un chiffre. À communiquer à la personne, qui pourra le changer depuis son profil.',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 6, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => array_flip($options['roles']), // 'rolesAttribuables()' rend 'ROLE_X => libellé', ChoiceType attend l'inverse
                'placeholder' => '— Choisir un rôle —',
                'constraints' => [new NotNull()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'utilisateur',
            'roles' => [],
            'creation' => false,
        ]);
    }
}
