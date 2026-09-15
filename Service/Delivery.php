<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use Symfony\Component\Mime\Email;

final class Delivery
{
    public function __construct(private InvoiceSender $mailer, private ReceiptStore $receipts, private Settings $settings, private string $testRecipientPattern = '') {}
    public function receipt(int $invoiceId): ?array { return $this->receipts->read($invoiceId); }
    public function version(?array $receipt): string { return $this->receipts->fingerprint($receipt); }
    public function hasAccepted(?array $receipt): bool { return $this->receipts->hasAccepted($receipt); }
    public function send(int $invoiceId, int $userId, string $nonce, Email $message, bool $resend, string $expectedVersion): void
    {
        if (!$this->settings->sendingEnabled()) throw new \InvalidArgumentException('kanka_mail.error.disabled');
        foreach ([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()] as $recipient) {
            if ($this->testRecipientPattern !== '' && preg_match('~'.$this->testRecipientPattern.'~D', $recipient->getAddress()) !== 1) throw new \InvalidArgumentException('kanka_mail.error.test_recipient');
        }
        $this->receipts->locked($invoiceId, function () use ($invoiceId, $userId, $nonce, $message, $resend, $expectedVersion): void {
            $previous = $this->receipt($invoiceId);
            if (!hash_equals($this->version($previous), $expectedVersion)) throw new \InvalidArgumentException('kanka_mail.error.stale_receipt');
            if ($previous !== null && ($previous['nonce'] === $nonce || $previous['status'] === 'uncertain' || ($this->hasAccepted($previous) && !$resend))) throw new \InvalidArgumentException('kanka_mail.error.duplicate');
            $receipt = ['nonce' => $nonce, 'user' => $userId, 'time' => gmdate(DATE_ATOM), 'status' => 'uncertain', 'to' => $message->getTo()[0]->getAddress(), 'previous' => $previous ? ['time' => $previous['time'], 'status' => $previous['status'], 'recovery' => $previous['recovery'] ?? null] : null];
            $receipt['has_accepted'] = $this->hasAccepted($previous);
            $this->receipts->write($invoiceId, $receipt);
            try { $this->mailer->send($message); }
            catch (SendFailure $e) {
                $receipt['status'] = $e->definite ? 'failed' : 'uncertain'; $receipt['error'] = $e->getMessage();
                $this->receipts->write($invoiceId, $receipt);
                throw new \InvalidArgumentException($e->getMessage());
            } catch (\Throwable) { throw new \InvalidArgumentException('kanka_mail.error.smtp_uncertain'); }
            $receipt['status'] = 'accepted';
            $receipt['has_accepted'] = true;
            try { $this->receipts->write($invoiceId, $receipt); }
            catch (\InvalidArgumentException) { throw new \InvalidArgumentException('kanka_mail.error.accepted_storage'); }
        });
    }
}
