<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/*
    - L'inscription d'un client : l'entreprise ET son premier administrateur, en une seule saisie.
      Les deux vont ensemble et c'est le point du formulaire — créer l'entreprise seule donne un
      client que personne ne peut ouvrir, et qu'il faut rattraper par une seconde manipulation.

    - Les noms de champs sont ceux de 'RegisterInput' côté API, à la lettre : le contrôleur poste
      'getData()' tel quel, et une clé qui diverge se traduirait par un champ silencieusement ignoré.

    - Les contraintes recopient celles de l'API. Elles ne la remplacent pas — elle revalide tout —
      elles évitent un aller-retour pour une faute qui se voit à la saisie.
*/
class InscriptionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            /* ---- L'entreprise ---------------------------------------------------------------- */
            ->add('nomEntreprise', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'constraints' => [
                    new NotBlank(message: 'Le nom de l\'entreprise est obligatoire'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('codeentreprise', TextType::class, [
                'label' => 'Code de l\'entreprise',
                'help' => 'Il ne pourra plus changer : c\'est l\'identifiant remis au client.',
                'constraints' => [
                    new NotBlank(message: 'Le code entreprise est obligatoire'),
                    new Regex(
                        pattern: '/^[A-Za-z0-9]{2,20}$/',
                        message: 'Le code ne peut contenir que des lettres et des chiffres, de 2 à 20 caractères'
                    ),
                ],
            ])
            ->add('contact1', TextType::class, [
                'label' => 'Contact principal',
                'constraints' => [
                    new NotBlank(message: 'Un contact est obligatoire'),
                    new Length(min: 8, max: 30),
                ],
            ])
            ->add('contact2', TextType::class, [
                'label' => 'Autre contact',
                'required' => false,
                'constraints' => [new Length(min: 8, max: 30)],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('emailEntreprise', EmailType::class, [
                'label' => 'Adresse email de l\'entreprise',
                'required' => false,
                'help' => 'Celle du standard, pas celle de l\'administrateur.',
                'constraints' => [new Email(message: 'Cette adresse e-mail n\'est pas valide')],
            ])

            /* ---- Son premier administrateur --------------------------------------------------- */
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'help' => 'C\'est avec elle qu\'il se connecte.',
                'constraints' => [
                    new NotBlank(message: 'L\'adresse e-mail est obligatoire'),
                    new Email(message: 'Cette adresse e-mail n\'est pas valide'),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [new Length(min: 8, max: 30)],
            ])
            /*
                - Les règles de composition sont celles de l'API, y compris la longueur minimale de
                  dix caractères : ce compte autorise des virements.
                - L'API refuse en plus les mots de passe figurant dans des fuites connues, ce qui
                  demande un appel distant et ne peut donc pas être vérifié ici.
            */
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'help' => 'À communiquer à la personne. Elle pourra le changer depuis son profil.',
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire'),
                    new Length(min: 10, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'inscription',
        ]);
    }
}
