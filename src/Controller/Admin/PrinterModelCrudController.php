<?php

namespace App\Controller\Admin;

use App\Catalog\Client\CatalogClient;
use App\Catalog\Domain\Entity\PrinterModel;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PrinterModelCrudController extends AbstractCrudController
{
    public function __construct(private readonly CatalogClient $catalogClient)
    {
    }

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
            ->setDefaultSort(['brandName' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'slug', 'brandName'])
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
        $isForm = in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);

        yield IdField::new('id')->hideOnForm()->hideOnIndex();

        // Brand lives in catalog-service: pick the slug from the live list on the
        // form, show the stored snapshot name elsewhere.
        if ($isForm) {
            yield ChoiceField::new('brandSlug', 'Brand')
                ->setChoices($this->brandChoices())
                ->setRequired(true)
                ->setColumns(4);
        } else {
            yield TextField::new('brandName', 'Brand')->setColumns(4);
        }

        yield TextField::new('name', 'Model Name')
            ->setRequired(true)
            ->setColumns(5);
        yield SlugField::new('slug', 'URL Slug')
            ->setTargetFieldName('name')
            ->setRequired(true)
            ->setColumns(3);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncBrandName($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncBrandName($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Resolve the brand display name from the chosen slug (snapshot).
     */
    private function syncBrandName(mixed $entity): void
    {
        if ($entity instanceof PrinterModel) {
            $nameBySlug = array_flip($this->brandChoices());
            $entity->setBrandName($nameBySlug[$entity->getBrandSlug()] ?? $entity->getBrandSlug());
        }
    }

    /**
     * @return array<string, string> map of brand name => slug (EasyAdmin choice format)
     */
    private function brandChoices(): array
    {
        $choices = [];
        foreach ($this->catalogClient->brands() as $brand) {
            $choices[$brand->getName()] = $brand->getSlug();
        }

        return $choices;
    }
}
