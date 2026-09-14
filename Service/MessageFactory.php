<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use App\Configuration\MailConfiguration;
use App\Entity\Invoice;
use App\Invoice\InvoiceService;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MessageFactory
{
    public function __construct(private Settings $settings, private TemplateText $text, private InvoiceService $invoices, private MailConfiguration $mail)
    {
    }

    public function prepare(Invoice $invoice): array
    {
        $customer = $invoice->getCustomer();
        if ($customer === null) {
            throw new \InvalidArgumentException('kanka_mail.error.customer');
        }
        $language = $customer->getLanguage() ?: 'en';
        $template = $this->settings->template($language);
        if ($template === null) {
            throw new \InvalidArgumentException('kanka_mail.error.language');
        }
        $formatter = new \NumberFormatter($language, \NumberFormatter::CURRENCY);
        $values = ['customer_name' => $customer->getName(), 'company' => $customer->getCompany() ?: $customer->getName(), 'contact' => $customer->getContact(), 'invoice_number' => $invoice->getInvoiceNumber(), 'invoice_date' => $invoice->getCreatedAt()?->format('Y-m-d'), 'due_date' => $invoice->getDueDate()?->format('Y-m-d'), 'total' => $formatter->formatCurrency($invoice->getTotal(), $invoice->getCurrency()), 'currency' => $invoice->getCurrency()];
        $result = ['to' => trim($customer->getInvoiceEmail() ?? ''), 'language' => $language, 'sender' => $this->sender()->toString()];
        foreach (['greeting', 'subject', 'body'] as $key) {
            $override = trim((string) $customer->getMetaField('kanka_mail_'.$key)?->getValue());
            $result[$key] = $this->text->render($override !== '' ? $override : ($template[$key] ?? ''), $values);
        }
        $result['attachment_hash'] = hash_file('sha256', $this->file($invoice)->getPathname());
        return $result;
    }

    public function file(Invoice $invoice): \SplFileInfo
    {
        $file = $this->invoices->getInvoiceFile($invoice);
        if ($file === null || strtolower($file->getExtension()) !== 'pdf') {
            throw new \InvalidArgumentException('kanka_mail.error.attachment');
        }
        return $file;
    }

    public function sender(): Address
    {
        $address = $this->mail->getFromAddress();
        if (!$address) {
            throw new \InvalidArgumentException('kanka_mail.error.sender');
        }
        return new Address($address, $this->settings->senderName());
    }

    public function create(Invoice $invoice, array $data): Email
    {
        $file = $this->file($invoice);
        if ($data['sender'] !== $this->sender()->toString() || !hash_equals($data['attachment_hash'], hash_file('sha256', $file->getPathname()))) {
            throw new \InvalidArgumentException('kanka_mail.error.changed');
        }
        $this->validate($data);
        return (new Email())->from($this->sender())->to(new Address($data['to']))->subject($data['subject'])->text(trim($data['greeting'])."\n\n".trim($data['body']))->attachFromPath($file->getPathname(), $file->getFilename(), 'application/pdf');
    }

    public function validate(array $data): void
    {
        if (preg_match('/[\r\n]/', $data['to']) || preg_match('/[\r\n]/', $data['subject']) || trim($data['subject']) === '' || trim($data['body']) === '' || strlen($data['subject']) > 255 || strlen($data['body']) > 20000 || strlen($data['greeting']) > 500) {
            throw new \InvalidArgumentException('kanka_mail.error.fields');
        }
        try {
            new Address($data['to']);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('kanka_mail.error.recipient');
        }
    }
}
