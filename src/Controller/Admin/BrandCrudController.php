<?php

namespace App\Controller\Admin;

use App\Catalog\Domain\Entity\Brand;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BrandCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Brand::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Brand')
            ->setEntityLabelInPlural('Brands')
            ->setPageTitle('index', 'Manage Brands')
            ->setPageTitle('new', 'Create Brand')
            ->setPageTitle('edit', 'Edit Brand')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Create Brand'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->hideOnIndex(),
            TextField::new('name', 'Name')
                ->setRequired(true)
                ->setColumns(6),
            SlugField::new('slug', 'URL Slug')
                ->setTargetFieldName('name')
                ->setRequired(true)
                ->setColumns(4),
            ColorField::new('color', 'Color')
                ->setColumns(2),
        ];
    }
}
