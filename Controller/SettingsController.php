<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Controller;

use KimaiPlugin\KankaInvoiceMailBundle\Service\Settings;
use KimaiPlugin\KankaInvoiceMailBundle\Service\TemplateText;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/{_locale}/invoice-mail', requirements: ['_locale' => '%app_locales%'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('system_configuration')]
final class SettingsController extends AbstractController
{
    public function __construct(private Settings $settings, private TemplateText $text, private FormFactoryInterface $forms, private TranslatorInterface $translator)
    {
    }

    #[Route('/settings', name: 'kanka_invoice_mail_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $global = $this->forms->createNamedBuilder('mail_global', FormType::class, ['sender_name' => $this->settings->senderName(), 'enabled' => $this->settings->sendingEnabled()])
            ->add('sender_name', TextType::class, ['label' => 'kanka_mail.sender_name', 'required' => false, 'constraints' => [new Length(max: 100)]])
            ->add('enabled', CheckboxType::class, ['label' => 'kanka_mail.enable', 'required' => false])->getForm();
        $global->handleRequest($request);
        if ($global->isSubmitted() && $global->isValid()) {
            $data = $global->getData();
            $this->settings->saveGlobal(trim($data['sender_name'] ?? ''), $data['enabled']);
            return $this->redirectToRoute('kanka_invoice_mail_settings');
        }
        $sections = [];
        foreach ($this->settings->languages() as $language => $count) {
            $template = $this->settings->template($language);
            $form = $this->forms->createNamedBuilder('mail_template_'.$language, FormType::class, $template ?? ['greeting' => '', 'subject' => '', 'body' => ''])
                ->add('greeting', TextType::class, ['label' => 'kanka_mail.greeting', 'required' => false, 'constraints' => [new Length(max: 500)]])
                ->add('subject', TextType::class, ['label' => 'kanka_mail.subject', 'constraints' => [new NotBlank(), new Length(max: 255)]])
                ->add('body', TextareaType::class, ['label' => 'kanka_mail.body', 'attr' => ['rows' => 9], 'constraints' => [new NotBlank(), new Length(max: 20000)]])->getForm();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();$data['greeting'] ??= '';
                try {
                    foreach ($data as $value) $this->text->validate($value);
                    if (preg_match('/[\r\n]/', $data['subject'])) throw new \InvalidArgumentException('kanka_mail.error.fields');
                    $this->settings->saveTemplate($language, $data);
                    return $this->redirectToRoute('kanka_invoice_mail_settings');
                } catch (\InvalidArgumentException $e) {
                    $form->addError(new FormError($this->translator->trans($e->getMessage())));
                }
            }
            $sections[] = ['language' => $language, 'count' => $count, 'missing' => $template === null, 'form' => $form->createView()];
        }
        return $this->render('@KankaInvoiceMail/settings.html.twig', ['global' => $global->createView(), 'sections' => $sections, 'placeholders' => TemplateText::KEYS]);
    }
}
