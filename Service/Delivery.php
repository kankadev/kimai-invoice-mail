<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use App\Mail\KimaiMailer;
use App\Utils\FileHelper;
use Symfony\Component\Mime\Email;

final class Delivery
{
    public function __construct(private KimaiMailer $mailer, private FileHelper $files, private Settings $settings, private string $testRecipientPattern = '')
    {
    }

    public function receipt(int $invoiceId): ?array
    {
        $path = $this->files->getDataDirectory('kanka-invoice-mail').$invoiceId.'.json';
        return is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function send(int $invoiceId, int $userId, string $nonce, Email $message, bool $resend): void
    {
        if (!$this->settings->sendingEnabled()) {
            throw new \InvalidArgumentException('kanka_mail.error.disabled');
        }
        foreach ([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()] as $recipient) {
            if ($this->testRecipientPattern !== '' && preg_match('~'.$this->testRecipientPattern.'~D', $recipient->getAddress()) !== 1) {
                throw new \InvalidArgumentException('kanka_mail.error.test_recipient');
            }
        }
        $path = $this->files->getDataDirectory('kanka-invoice-mail').$invoiceId.'.json';
        $handle = fopen($path.'.lock', 'c+');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) fclose($handle);
            throw new \InvalidArgumentException('kanka_mail.error.busy');
        }
        chmod($path.'.lock', 0600);
        try {
            $raw = is_file($path) ? file_get_contents($path) : '';
            $previous = $raw !== '' ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
            if ($previous !== null && ($previous['nonce'] === $nonce || $previous['status'] !== 'accepted' || !$resend)) {
                throw new \InvalidArgumentException('kanka_mail.error.duplicate');
            }
            $receipt = ['nonce' => $nonce, 'user' => $userId, 'time' => gmdate(DATE_ATOM), 'status' => 'uncertain', 'to' => $message->getTo()[0]->getAddress(), 'previous' => $previous ? ['time' => $previous['time'], 'status' => $previous['status']] : null];
            $write = static function (array $value) use ($path): void {
                $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
                $content = json_encode($value, JSON_THROW_ON_ERROR);
                $stream = fopen($temporary, 'x');
                if (!$stream) throw new \RuntimeException('Cannot create delivery receipt');
                chmod($temporary, 0600);
                try {
                    if (fwrite($stream, $content) !== strlen($content) || !fflush($stream) || !fsync($stream)) {
                        throw new \RuntimeException('Cannot persist delivery receipt');
                    }
                } finally {
                    fclose($stream);
                }
                if (!rename($temporary, $path)) throw new \RuntimeException('Cannot replace delivery receipt');
            };
            // Persist ambiguity before SMTP: a crash must never trigger an automatic resend.
            $write($receipt);
            try {
                $this->mailer->send($message);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('kanka_mail.error.transport');
            }
            $receipt['status'] = 'accepted';
            $write($receipt);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
