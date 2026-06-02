<?php

namespace App\Controller\Admin;

use App\Catalog\Domain\Entity\Brand;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\PrinterModel;
use App\Catalog\Domain\Entity\Product;
use App\Order\Domain\Entity\Order;
use App\User\Domain\Entity\User;
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
            'July', 'August', 'September', 'October', 'November', 'December'
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
        yield MenuItem::linkToCrud('Products', 'fa fa-box', Product::class);
        yield MenuItem::linkToCrud('Categories', 'fa fa-folder', Category::class);
        yield MenuItem::linkToCrud('Brands', 'fa fa-tag', Brand::class);
        yield MenuItem::linkToCrud('Printer Models', 'fa fa-print', PrinterModel::class);
        yield MenuItem::section('Orders');
        yield MenuItem::linkToCrud('Orders', 'fa fa-shopping-cart', Order::class);
        yield MenuItem::section('Users');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
        yield MenuItem::section('Tools');
        yield MenuItem::linkToRoute('Export', 'fa fa-download', 'admin_export');
    }
}