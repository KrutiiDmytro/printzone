<?php

namespace App\Controller\Admin;

use App\Catalog\Domain\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Категория')
            ->setEntityLabelInPlural('Категории')
            ->setPageTitle('index', 'Управление категориями')
            ->setPageTitle('new', 'Создать новую категорию')
            ->setPageTitle('edit', 'Редактировать категорию')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Создать категорию');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('parent', 'Родительская категория'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->hideOnIndex(),
            TextField::new('name', 'Название')
                ->setRequired(true)
                ->setColumns(6),
            SlugField::new('slug', 'URL')
                ->setTargetFieldName('name')
                ->setRequired(true)
                ->setColumns(6),
            AssociationField::new('parent', 'Родительская категория')
                ->setColumns(12),
        ];
    }
}