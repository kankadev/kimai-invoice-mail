<?php
declare(strict_types=1);
require __DIR__.'/../Service/TemplateText.php';

use KimaiPlugin\KankaInvoiceMailBundle\Service\TemplateText;

$renderer = new TemplateText();
$count = 0;
$check = static function (bool $ok) use (&$count): void { if (!$ok) throw new RuntimeException('Assertion failed'); ++$count; };
$check($renderer->render('Invoice {invoice_number}: {total}', ['invoice_number' => 'TEST-001', 'total' => '150,00 €']) === 'Invoice TEST-001: 150,00 €');
$check($renderer->render('Hello Alex,', []) === 'Hello Alex,');
$check($renderer->render('{contact}', ['contact' => 'Alex Example']) === 'Alex Example');
foreach (['{unknown}', '{{ contact }}', '{contact', 'contact}', '{ contact }', '{}', '{invoice_number}'] as $invalid) {
    try { $renderer->render($invalid, []); throw new RuntimeException('Invalid or empty placeholder accepted'); }
    catch (InvalidArgumentException) { ++$count; }
}
$check($renderer->render('{contact}', ['contact' => '{not_executable}']) === '{not_executable}');
echo $count." template checks passed\n";
