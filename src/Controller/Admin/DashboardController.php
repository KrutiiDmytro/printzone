<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder
    ) {
    }

    public function index(): Response
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        $chart->setData([
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Sales',
                    'backgroundColor' => 'rgb(255, 99, 132)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => [0, 10, 5, 2, 20, 30, 45, 35, 40, 50, 45, 60],
                ],
            ],
        ]);

        // Форматирование валюты (EUR) через PHP
        $chart->setOptions([
            'scales' => [
                'y' => [
                    'suggestedMin' => 0,
                    'suggestedMax' => 100,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return new Intl.NumberFormat("de-DE", {
                                style: "currency",
                                currency: "EUR"
                            }).format(value);
                        }',
                    ],
                ],
            ],
            'plugins' => [
                'zoom' => [
                    'zoom' => [
                        'wheel' => ['enabled' => true],
                        'pinch' => ['enabled' => true],
                        'mode' => 'xy',
                    ],
                ],
            ],
        ]);

        return $this->render('admin/dashboard.html.twig', [
            'chart' => $chart,
        ]);
    }

    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            ->addHtmlContentToHead('<style>.datagrid td img { max-height: 48px; width: auto; }</style>');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Admin Panel')
            ->setFaviconPath('favicon.ico')
            ->setTranslationDomain('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Catalogue');
        // Product/Category/Brand are proxied to catalog-service over HTTP (no local entity).
        yield MenuItem::linkToRoute('Products', 'fa fa-box', 'admin_catalog_products');
        yield MenuItem::linkToRoute('Categories', 'fa fa-folder', 'admin_catalog_categories');
        yield MenuItem::linkToRoute('Brands', 'fa fa-tag', 'admin_catalog_brands');
        yield MenuItem::linkTo(PrinterModelCrudController::class, 'Printer Models', 'fa fa-print');
        yield MenuItem::section('Orders');
        yield MenuItem::linkTo(OrderCrudController::class, 'Orders', 'fa fa-shopping-cart');
        yield MenuItem::section('Users');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::section('Tools');
        yield MenuItem::linkToRoute('Export', 'fa fa-download', 'admin_export');
    }
}
