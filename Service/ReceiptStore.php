<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use App\Utils\FileHelper;

final class ReceiptStore
{
    public function __construct(private FileHelper $files) {}
    private function path(int $id): string
    {
        if ($id < 1) throw new \InvalidArgumentException('kanka_mail.error.fields');
        try { return $this->files->getDataDirectory('kanka-invoice-mail').$id.'.json'; }
        catch (\Throwable) { throw new \InvalidArgumentException('kanka_mail.error.storage'); }
    }
    public function read(int $id): ?array
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            if (file_exists($path)) throw new \InvalidArgumentException('kanka_mail.error.receipt');
            return null;
        }
        try {
            if (filesize($path) > 65536) throw new \RuntimeException();
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['nonce'], $data['time'], $data['status']) || !is_string($data['nonce']) || !is_string($data['time']) || strtotime($data['time']) === false || !in_array($data['status'], ['accepted', 'uncertain', 'failed', 'retryable'], true)) throw new \RuntimeException();
            return $data;
        } catch (\Throwable) { throw new \InvalidArgumentException('kanka_mail.error.receipt'); }
    }
    public function locked(int $id, callable $operation): mixed
    {
        $handle = @fopen($this->path($id).'.lock', 'c+');
        if (!$handle) throw new \InvalidArgumentException('kanka_mail.error.storage');
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); throw new \InvalidArgumentException('kanka_mail.error.busy'); }
        chmod($this->path($id).'.lock', 0600);
        try { return $operation(); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    /** Call only while holding this invoice's lock. */
    public function write(int $id, array $data): void
    {
        $path = $this->path($id);
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            $content = json_encode($data, JSON_THROW_ON_ERROR);
            $stream = @fopen($temporary, 'x');
            if (!$stream) throw new \RuntimeException();
            chmod($temporary, 0600);
            try {
                if (fwrite($stream, $content) !== strlen($content) || !fflush($stream) || !fsync($stream)) throw new \RuntimeException();
            } finally { fclose($stream); }
            if (!@rename($temporary, $path)) throw new \RuntimeException();
        } catch (\Throwable) { throw new \InvalidArgumentException('kanka_mail.error.storage'); }
        finally { if (is_file($temporary)) @unlink($temporary); }
    }
    public function fingerprint(array $receipt): string { return hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)); }
    public function resolve(int $id, string $fingerprint, int $user, string $decision, string $reason): void
    {
        $this->locked($id, function () use ($id, $fingerprint, $user, $decision, $reason): void {
            $receipt = $this->read($id);
            if ($receipt === null || $receipt['status'] !== 'uncertain' || !hash_equals($this->fingerprint($receipt), $fingerprint)) throw new \InvalidArgumentException('kanka_mail.error.stale_receipt');
            if (!in_array($decision, ['accepted', 'retryable'], true) || trim($reason) === '' || strlen($reason) > 1000) throw new \InvalidArgumentException('kanka_mail.error.fields');
            $receipt['status'] = $decision;
            $receipt['recovery'] = ['user' => $user, 'time' => gmdate(DATE_ATOM), 'decision' => $decision, 'reason' => trim($reason)];
            $this->write($id, $receipt);
        });
    }
    /** Remove old personal details, never the duplicate-protection marker or lock. */
    public function compact(int $days): int
    {
        if ($days < 30 || $days > 36500) throw new \InvalidArgumentException('kanka_mail.error.fields');
        $cutoff = time() - $days * 86400; $count = 0;
        foreach (new \DirectoryIterator($this->files->getDataDirectory('kanka-invoice-mail')) as $file) {
            if (!$file->isFile() || !preg_match('/^([1-9][0-9]*)\.json$/D', $file->getFilename(), $match)) continue;
            $id = (int) $match[1];
            try {
                $count += $this->locked($id, function () use ($id, $cutoff): int {
                    $receipt = $this->read($id);
                    if ($receipt === null || !in_array($receipt['status'], ['accepted', 'failed'], true) || isset($receipt['compacted'])) return 0;
                    $latest = max(strtotime($receipt['time']) ?: time(), strtotime($receipt['recovery']['time'] ?? '') ?: 0);
                    if ($latest >= $cutoff) return 0;
                    unset($receipt['to'], $receipt['user'], $receipt['previous']);
                    if (isset($receipt['recovery'])) unset($receipt['recovery']['user'], $receipt['recovery']['reason']);
                    $receipt['compacted'] = gmdate(DATE_ATOM); $this->write($id, $receipt); return 1;
                });
            } catch (\InvalidArgumentException $e) { if ($e->getMessage() !== 'kanka_mail.error.busy') throw $e; }
            if ($count >= 1000) break;
        }
        return $count;
    }
}
