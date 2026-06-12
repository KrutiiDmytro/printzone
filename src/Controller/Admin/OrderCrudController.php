<?php

namespace App\Controller\Admin;

use App\Order\Domain\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Заказ')
            ->setEntityLabelInPlural('Заказы')
            ->setPageTitle('index', 'Управление заказами')
            ->setPageTitle('detail', 'Детали заказа')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['status', 'userEmail'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW) // Заказы создаются пользователями, не администраторами
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setLabel('Редактировать заказ');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('userEmail', 'Пользователь'))
            ->add(TextFilter::new('status', 'Статус'))
            ->add(DateTimeFilter::new('createdAt', 'Дата создания'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->hideOnIndex(),
            TextField::new('userEmail', 'Пользователь')
                ->setColumns(6),
            ChoiceField::new('status', 'Статус')
                ->setChoices([
                    'PENDING' => 'PENDING',
                    'PAID' => 'PAID',
                    'FAILED' => 'FAILED',
                    'PROCESSING' => 'PROCESSING',
                    'SHIPPED' => 'SHIPPED',
                    'DELIVERED' => 'DELIVERED',
                    'CANCELLED' => 'CANCELLED',
                ])
                ->setRequired(true)
                ->setColumns(6),
            DateTimeField::new('createdAt', 'Дата создания')
                ->setFormat('dd.MM.yyyy HH:mm')
                ->hideOnForm()
                ->setColumns(6),
            MoneyField::new('totalAmount', 'Общая сумма')
                ->setCurrency('USD')
                ->setStoredAsCents(true)
                ->setRequired(true)
                ->setColumns(6)
                ->formatValue(function ($value) {
                    return $value ? $value / 100 : 0;
                }),
            CollectionField::new('items', 'Товары в заказе')
               ->onlyOnDetail()
               ->setTemplatePath('admin/order/items.html.twig'),
        ];
    }
}
