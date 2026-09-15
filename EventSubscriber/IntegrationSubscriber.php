<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\EventSubscriber;

use App\Entity\CustomerMeta;
use App\Event\ConfigureMainMenuEvent;
use App\Event\CustomerMetaDefinitionEvent;
use App\Event\PageActionsEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IntegrationSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuthorizationCheckerInterface $auth, private UrlGeneratorInterface $urls, private TranslatorInterface $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ConfigureMainMenuEvent::class => 'menu', CustomerMetaDefinitionEvent::class => 'fields', 'actions.invoice' => ['actions', 0]];
    }

    public function menu(ConfigureMainMenuEvent $event): void
    {
        if ($this->auth->isGranted('system_configuration')) {
            $event->getSystemMenu()->addChild(new MenuItemModel('kanka_invoice_mail', 'kanka_mail.title', 'kanka_invoice_mail_settings', [], 'mail'));
        }
    }

    public function fields(CustomerMetaDefinitionEvent $event): void
    {
        foreach (['greeting' => TextType::class, 'subject' => TextType::class, 'body' => TextareaType::class] as $key => $type) {
            $event->getEntity()->setMetaField((new CustomerMeta())->setName('kanka_mail_'.$key)->setLabel('kanka_mail.customer.'.$key)->setType($type)->setIsRequired(false)->setIsVisible(false)->setOptions(['help' => $key === 'body' ? 'kanka_mail.customer.help' : 'kanka_mail.customer.help_short', 'help_attr' => ['class' => 'alert alert-info mt-2'], 'translation_domain' => 'messages']));
        }
    }

    public function actions(PageActionsEvent $event): void
    {
        $invoice = $event->getPayload()['invoice'] ?? null;
        if ($invoice === null || $invoice->getId() === null || !$this->auth->isGranted('view_invoice', $invoice) || !$this->auth->isGranted('create_invoice')) {
            return;
        }
        $event->addAction('kanka_invoice_mail', ['url' => $this->urls->generate('kanka_invoice_mail_prepare', ['id' => $invoice->getId()]), 'title' => $this->translator->trans('kanka_mail.prepare'), 'icon' => 'fas fa-envelope']);
    }
}
