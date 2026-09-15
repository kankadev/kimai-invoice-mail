<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use Symfony\Component\Mime\Email;

interface InvoiceSender
{
    public function send(Email $message): void;
}
