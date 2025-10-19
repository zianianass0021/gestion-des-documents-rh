<?php

namespace App\Form;

use App\Entity\OrganisationEmployeeContrat;
use App\Entity\Organisation;
use App\Entity\EmployeeContrat;
use App\Repository\OrganisationRepository;
use App\Repository\EmployeeContratRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganisationEmployeeContratType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('organisation', EntityType::class, [
                'label' => 'Organisation',
                'class' => Organisation::class,
                'choice_label' => 'dossierDesignation',
                'query_builder' => function(OrganisationRepository $repository) {
                    return $repository->createQueryBuilder('o')
                        ->orderBy('o.dossierDesignation', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('employeeContrat', EntityType::class, [
                'label' => 'Contrat Employé',
                'class' => EmployeeContrat::class,
                'choice_label' => function(EmployeeContrat $contrat) {
                    $employe = $contrat->getEmploye();
                    return $employe->getPrenom() . ' ' . $employe->getNom() . ' (' . $contrat->getNatureContrat()->getDesignation() . ')';
                },
                'query_builder' => function(EmployeeContratRepository $repository) {
                    return $repository->createQueryBuilder('ec')
                        ->join('ec.employe', 'e')
                        ->join('ec.natureContrat', 'nc')
                        ->orderBy('e.nom', 'ASC')
                        ->addOrderBy('e.prenom', 'ASC');
                },
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de Début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de Fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrganisationEmployeeContrat::class,
        ]);
    }
}
