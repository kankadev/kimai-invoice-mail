<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

final class SendFailure extends \RuntimeException
{
    public function __construct(public readonly bool $definite, public readonly string $reason)
    {
        parent::__construct('kanka_mail.error.'.$reason);
    }
}
