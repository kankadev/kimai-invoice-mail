<?php
declare(strict_types=1);

// Host adapters for a local-only unit test; integration tests use real Kimai services.
namespace App\Utils {
    final class FileHelper {
        public function __construct(private string $root) {}
        public function getDataDirectory(string $child): string {
            $path = $this->root.'/'.$child.'/';
            if (!is_dir($path)) mkdir($path, 0700, true);
            return $path;
        }
    }
}
namespace KimaiPlugin\KankaInvoiceMailBundle\Service {
    final class Settings { public function sendingEnabled(): bool { return true; } }
}
namespace {
    require $argv[1];
    foreach (['InvoiceSender', 'SendFailure', 'ReceiptStore', 'Delivery'] as $file) require __DIR__.'/../Service/'.$file.'.php';
    use KimaiPlugin\KankaInvoiceMailBundle\Service\{InvoiceSender, SendFailure, ReceiptStore, Delivery, Settings};
    use Symfony\Component\Mime\Email;

    $root = sys_get_temp_dir().'/invoice-mail-test-'.bin2hex(random_bytes(8));
    $files = new App\Utils\FileHelper($root);
    $store = new ReceiptStore($files);
    $sender = new class implements InvoiceSender {
        public int $calls = 0;
        public ?SendFailure $failure = null;
        public function send(Email $message): void { ++$this->calls; if ($this->failure) throw $this->failure; }
    };
    $delivery = new Delivery($sender, $store, new Settings());
    $mail = (new Email())->from('sender@example.invalid')->to('recipient@example.invalid')->subject('Test')->text('Synthetic');
    $count = 0;
    $check = static function (bool $ok, string $label) use (&$count): void { if (!$ok) throw new RuntimeException($label); ++$count; };
    $reject = static function (callable $action, string $key) use ($check): void {
        try { $action(); throw new RuntimeException('Expected '.$key); }
        catch (InvalidArgumentException $e) { $check($e->getMessage() === $key, $e->getMessage()); }
    };
    $send = static function (int $id, string $nonce, bool $repeat = false) use ($delivery, $mail, $store): void {
        $delivery->send($id, 1, $nonce, $mail, $repeat, $store->fingerprint($store->read($id)));
    };
    try {
        $old = $store->fingerprint(null);
        $send(1, 'first');
        $check($store->hasAccepted($store->read(1)), 'Accepted marker missing');
        $reject(fn() => $delivery->send(1, 2, 'parallel', $mail, true, $old), 'kanka_mail.error.stale_receipt');
        $sender->failure = new SendFailure(true, 'smtp_auth');
        $reject(fn() => $send(1, 'repeat', true), 'kanka_mail.error.smtp_auth');
        $check($store->hasAccepted($store->read(1)), 'Failed repeat lost acceptance');
        $reject(fn() => $send(1, 'unconfirmed'), 'kanka_mail.error.duplicate');
        $reject(fn() => $send(1, 'repeat-again', true), 'kanka_mail.error.smtp_auth');
        $check($store->hasAccepted($store->read(1)), 'Repeated failure lost acceptance');
        $sender->failure = new SendFailure(false, 'smtp_uncertain');
        $reject(fn() => $send(2, 'uncertain'), 'kanka_mail.error.smtp_uncertain');
        $beforeRecovery = $store->fingerprint($store->read(2));
        $store->resolve(2, $beforeRecovery, 1, 'retryable', 'Checked synthetic provider');
        $reject(fn() => $delivery->send(2, 2, 'other-user', $mail, false, $beforeRecovery), 'kanka_mail.error.stale_receipt');
        $sender->failure = null;
        $send(2, 'fresh');
        $check($store->read(2)['status'] === 'accepted', 'Fresh review could not send');
        foreach ([1, 2] as $id) {
            $receipt = $store->read($id); $receipt['time'] = '2020-01-01';
            $store->locked($id, fn() => $store->write($id, $receipt));
        }
        file_put_contents($root.'/customer.json', 'sentinel');
        file_put_contents($root.'/invoice.pdf', 'sentinel');
        $check($store->compact(50) === 2, 'Compaction count');
        $check(file_get_contents($root.'/customer.json') === 'sentinel' && file_get_contents($root.'/invoice.pdf') === 'sentinel', 'Unrelated data changed');
        $check(!isset($store->read(1)['to']) && $store->hasAccepted($store->read(1)), 'Compaction lost marker');
        $reject(fn() => $send(1, 'after-cleanup'), 'kanka_mail.error.duplicate');
        $store->locked(3, fn() => $store->write(3, ['nonce'=>'legacy', 'time'=>'2020-01-01', 'status'=>'failed', 'previous'=>['status'=>'accepted']]));
        $store->compact(50);
        $check($store->hasAccepted($store->read(3)), 'Legacy acceptance lost');
        $lock = fopen($files->getDataDirectory('kanka-invoice-mail').'4.json.lock', 'c+');
        flock($lock, LOCK_EX);
        $reject(fn() => $send(4, 'busy'), 'kanka_mail.error.busy');
        flock($lock, LOCK_UN); fclose($lock);
        file_put_contents($files->getDataDirectory('kanka-invoice-mail').'5.json', 'broken');
        $reject(fn() => $send(5, 'broken'), 'kanka_mail.error.receipt');
        echo $count." delivery-state checks passed\n";
    } finally {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }
}
