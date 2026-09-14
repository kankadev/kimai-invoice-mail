<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

use App\Configuration\ConfigurationService;
use App\Entity\Configuration;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class Settings
{
    private const PREFIX = 'kanka_invoice_mail.template.';

    public function __construct(private ConfigurationService $configuration, private EntityManagerInterface $em)
    {
    }

    public function defaults(string $language): ?array
    {
        return match ($language) {
            'de' => ['greeting' => 'Guten Tag,', 'subject' => 'Rechnung {invoice_number}', 'body' => "anbei die Rechnung {invoice_number} über {total} für die erbrachten Leistungen.\n\nVielen Dank für das Vertrauen.\n\nFreundliche Grüße"],
            'en' => ['greeting' => 'Hello,', 'subject' => 'Invoice {invoice_number}', 'body' => "Please find attached invoice {invoice_number} for {total} for the services provided.\n\nThank you for your business.\n\nKind regards"],
            default => null,
        };
    }

    public function template(string $language): ?array
    {
        $raw = $this->configuration->getConfiguration(self::PREFIX.$language)?->getValue();
        if ($raw !== null) {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        // Locale variants inherit only their own base language, never a different language.
        return $this->defaults($language) ?? (str_contains($language, '_') ? $this->template(explode('_', $language)[0]) : null);
    }

    public function languages(): array
    {
        $result = [];
        foreach ($this->em->getRepository(Customer::class)->findAll() as $customer) {
            $language = $customer->getLanguage() ?: 'en';
            $result[$language] = ($result[$language] ?? 0) + 1;
        }
        foreach ($this->configuration->getConfigurations() as $key => $value) {
            if (str_starts_with($key, self::PREFIX)) {
                $language = substr($key, strlen(self::PREFIX));
                $result[$language] ??= 0;
            }
        }
        ksort($result);
        return $result;
    }

    public function saveTemplate(string $language, array $data): void
    {
        if (!array_key_exists($language, $this->languages())) {
            throw new \InvalidArgumentException('kanka_mail.error.language');
        }
        $this->save(self::PREFIX.$language, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function senderName(): string
    {
        return $this->configuration->getConfiguration('kanka_invoice_mail.sender_name')?->getValue() ?? '';
    }

    public function sendingEnabled(): bool
    {
        return $this->configuration->getConfiguration('kanka_invoice_mail.sending_enabled')?->getValue() === '1';
    }

    public function saveGlobal(string $name, bool $enabled): void
    {
        $this->save('kanka_invoice_mail.sender_name', $name);
        $this->save('kanka_invoice_mail.sending_enabled', $enabled ? '1' : '0');
    }

    private function save(string $key, string $value): void
    {
        $setting = $this->configuration->getConfiguration($key) ?? (new Configuration())->setName($key);
        $setting->setValue($value);
        $this->configuration->saveConfiguration($setting);
    }
}
