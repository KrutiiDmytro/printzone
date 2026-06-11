<?php

namespace App\Controller\Admin;

use App\Catalog\Domain\Entity\PrinterModel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PrinterModelCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PrinterModel::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Printer Model')
            ->setEntityLabelInPlural('Printer Models')
            ->setPageTitle('index', 'Manage Printer Models')
            ->setPageTitle('new', 'Add Printer Model')
            ->setPageTitle('edit', 'Edit Printer Model')
            ->setDefaultSort(['brand' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'slug'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $a) => $a->setLabel('Add Model'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            AssociationField::new('brand', 'Brand')
                ->setRequired(true)
                ->setColumns(4),
            TextField::new('name', 'Model Name')
                ->setRequired(true)
                ->setColumns(5),
            SlugField::new('slug', 'URL Slug')
                ->setTargetFieldName('name')
                ->setRequired(true)
                ->setColumns(3),
        ];
    }
}
