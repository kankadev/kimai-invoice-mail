<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

final class SmtpSender implements InvoiceSender
{
    public function __construct(private ?TransportInterface $transport = null, private string $dsn = '') {}

    public function send(Email $message): void
    {
        try { $this->transport ??= \Symfony\Component\Mailer\Transport::fromDsn($this->dsn); }
        catch (\Throwable) { throw new SendFailure(true, 'smtp_connect'); }
        // A queue or automatic failover cannot provide the synchronous result needed here.
        if (!$this->transport instanceof SmtpTransport) throw new SendFailure(true, 'unsupported_transport');
        $this->transport->setRestartThreshold(PHP_INT_MAX);
        try {
            $this->transport->stop();
            $this->transport->start();
        } catch (\Throwable $e) {
            // No message has been submitted at this stage. Never expose raw SMTP diagnostics.
            $reason = in_array((int) $e->getCode(), [530, 534, 535, 538], true) ? 'smtp_auth' : 'smtp_connect';
            throw new SendFailure(true, $reason);
        }
        try {
            if ($this->transport->send($message) === null) throw new SendFailure(true, 'smtp_rejected');
        } catch (SendFailure $e) {
            throw $e;
        } catch (UnexpectedResponseException $e) {
            $code = (int) $e->getCode();
            $smtpResponse = false;
            foreach ($e->getTrace() as $frame) {
                if (($frame['class'] ?? '') === SmtpTransport::class && ($frame['function'] ?? '') === 'assertResponseCode') $smtpResponse = true;
            }
            if ($smtpResponse && $code >= 400 && $code <= 599) {
                $reason = match (true) {
                    in_array($code, [530, 534, 535, 538], true) => 'smtp_auth',
                    in_array($code, [452, 552], true) => 'smtp_capacity',
                    $code < 500 => 'smtp_temporary',
                    default => 'smtp_rejected',
                };
                throw new SendFailure(true, $reason);
            }
            throw new SendFailure(false, 'smtp_uncertain');
        } catch (\Throwable) {
            throw new SendFailure(false, 'smtp_uncertain');
        }
    }
}
