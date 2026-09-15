<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
final class InvoiceStatus
{
    public function __construct(private Settings $settings, private EntityManagerInterface $em, private AuthorizationCheckerInterface $auth) {}
    public function afterAcceptance(Invoice $invoice): string
    {
        if (!$this->settings->markPending() || !$invoice->isNew()) return 'kanka_mail.sent';
        if (!$this->auth->isGranted('edit_invoice', $invoice)) return 'kanka_mail.sent_status_permission';
        try {
            // Compare-and-set preserves a concurrent payment or cancellation.
            $changed = $this->em->createQuery('UPDATE '.Invoice::class.' i SET i.status = :pending WHERE i.id = :id AND i.status = :new')
                ->setParameters(['pending' => Invoice::STATUS_PENDING, 'id' => $invoice->getId(), 'new' => Invoice::STATUS_NEW])->execute();
            return $changed ? 'kanka_mail.sent_pending' : 'kanka_mail.sent';
        } catch (\Throwable) { return 'kanka_mail.sent_status_failed'; }
    }
}
