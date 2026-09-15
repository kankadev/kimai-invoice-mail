<?php
declare(strict_types=1);
require $argv[1] ?? dirname(__DIR__, 4).'/vendor/autoload.php';
require_once dirname(__DIR__).'/Service/InvoiceSender.php';
require_once dirname(__DIR__).'/Service/SendFailure.php';
require_once dirname(__DIR__).'/Service/SmtpSender.php';
use KimaiPlugin\KankaInvoiceMailBundle\Service\SmtpSender;
use KimaiPlugin\KankaInvoiceMailBundle\Service\SendFailure;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
$cases = ['accepted' => null, 'auth' => [true, 'smtp_auth'], 'recipient' => [true, 'smtp_rejected'], 'temporary' => [true, 'smtp_temporary'], 'capacity' => [true, 'smtp_capacity'], 'reject_data' => [true, 'smtp_rejected'], 'disconnect' => [false, 'smtp_uncertain'], 'tls' => [true, 'smtp_connect']];
foreach ($cases as $mode => $expected) {
    $process = proc_open([PHP_BINARY, __DIR__.'/Fixtures/smtp-server.php', $mode], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Fixture could not start');
    $address = trim((string) fgets($pipes[1]));
    [$host, $port] = explode(':', $address);
    $transport = new EsmtpTransport($host, (int) $port, false);
    $transport->getStream()->setTimeout(2);
    if ($mode === 'auth') { $transport->setUsername('example'); $transport->setPassword(str_repeat('x', 8)); }
    $actual = null;
    try {
        (new SmtpSender($transport))->send((new Email())->from('sender@example.invalid')->to('recipient@example.invalid')->subject('Fixture')->text('Synthetic test'));
    } catch (SendFailure $e) { $actual = [$e->definite, $e->reason]; }
    finally {
        $transport->stop();
        foreach ($pipes as $pipe) fclose($pipe);
        proc_terminate($process); proc_close($process);
    }
    if ($actual !== $expected) throw new RuntimeException($mode.' classification mismatch: '.json_encode($actual));
    echo $mode." passed\n";
}

foreach (['null://null' => 'unsupported_transport', 'invalid://fixture' => 'smtp_connect'] as $dsn => $expected) {
    try { (new SmtpSender(null, $dsn))->send((new Email())->from('sender@example.invalid')->to('recipient@example.invalid')->subject('Test')->text('Synthetic')); throw new RuntimeException('Expected configuration failure'); }
    catch (SendFailure $e) { if (!$e->definite || $e->reason !== $expected) throw new RuntimeException('Configuration classification mismatch'); }
}
echo "Unsupported and invalid transport configuration passed\n";
