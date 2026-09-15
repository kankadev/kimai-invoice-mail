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
        foreach (['subject' => TextType::class, 'greeting' => TextType::class, 'body' => TextareaType::class] as $key => $type) {
            $options = ['translation_domain' => 'messages', 'block_name' => 'kanka_mail_'.$key];
            if ($key === 'subject') $options['block_prefix'] = 'kanka_mail_group';
            $field = (new CustomerMeta())->setName('kanka_mail_'.$key)->setLabel('kanka_mail.customer.'.$key)->setType($type)->setIsRequired(false)->setIsVisible(false)->setOrder(['subject' => 100, 'greeting' => 101, 'body' => 102][$key])->setOptions($options);
            if ($key !== 'subject') $field->setSection('kanka_invoice_mail');
            $event->getEntity()->setMetaField($field);
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
