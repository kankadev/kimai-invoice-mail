<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Service;

final class TemplateText
{
    public const KEYS = ['customer_name', 'company', 'contact', 'invoice_number', 'invoice_date', 'due_date', 'total', 'currency'];

    public function validate(string $text): void
    {
        preg_match_all('/\{([^{}]*)\}/u', $text, $matches);
        foreach ($matches[1] as $key) {
            if (!in_array($key, self::KEYS, true)) {
                throw new \InvalidArgumentException('kanka_mail.error.placeholder');
            }
        }
        if (str_contains(preg_replace('/\{[a-z_]+\}/', '', $text), '{') || str_contains(preg_replace('/\{[a-z_]+\}/', '', $text), '}')) {
            throw new \InvalidArgumentException('kanka_mail.error.placeholder');
        }
    }

    public function render(string $text, array $values): string
    {
        $this->validate($text);
        return preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m) use ($values): string {
            if (!isset($values[$m[1]]) || trim((string) $values[$m[1]]) === '') {
                throw new \InvalidArgumentException('kanka_mail.error.missing_value');
            }
            return (string) $values[$m[1]];
        }, $text);
    }
}
