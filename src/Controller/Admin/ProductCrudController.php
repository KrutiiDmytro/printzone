<?php

namespace App\Controller\Admin;

use App\Catalog\Domain\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Товар')
            ->setEntityLabelInPlural('Товары')
            ->setPageTitle('index', 'Управление товарами')
            ->setPageTitle('new', 'Создать новый товар')
            ->setPageTitle('edit', 'Редактировать товар')
            ->setPageTitle('detail', 'Детали товара')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['name', 'description'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Создать товар');
            })
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action->setLabel('Сохранить');
            })
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action->setLabel('Сохранить');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('category', 'Категория'))
            ->add(BooleanFilter::new('isFeatured', 'Рекомендуемый'))
            ->add(NumericFilter::new('price', 'Цена'))
            ->add(NumericFilter::new('stock', 'Остаток'));
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
            AssociationField::new('category', 'Категория')
                ->setRequired(true)
                ->setColumns(6),
            TextareaField::new('description', 'Описание')
                ->hideOnIndex()
                ->setColumns(12),
            MoneyField::new('price', 'Цена')
                ->setCurrency('EUR')
                ->setStoredAsCents(true)
                ->setRequired(true)
                ->setColumns(4),
            IntegerField::new('stock', 'Остаток')
                ->setRequired(true)
                ->setColumns(4),
            BooleanField::new('isFeatured', 'Рекомендуемый')
                ->setColumns(4),
            ImageField::new('image', 'Изображение')
                ->setBasePath('/img/')
                ->setUploadDir('public/img/')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setColumns(12),
            CollectionField::new('attributes', 'Атрибуты')
                ->hideOnIndex()
                ->hideOnForm(), // Убрать setTemplatePath, использовать стандартное отображение
        ];
    }
}