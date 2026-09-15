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
        $languages = $this->settings->languages();
        $builder = $this->forms->createNamedBuilder('invoice_mail', FormType::class)
            ->add('sender_name', TextType::class, ['data' => $this->settings->senderName(), 'label' => 'kanka_mail.sender_name', 'required' => false, 'constraints' => [new Length(max: 100)]])
            ->add('enabled', CheckboxType::class, ['data' => $this->settings->sendingEnabled(), 'label' => 'kanka_mail.enable', 'required' => false]);
        $sections = [];
        foreach ($languages as $language => $count) {
            $template = $this->settings->template($language);
            $section = $builder->create('template_'.$language, FormType::class, ['data' => $template ?? ['greeting' => '', 'subject' => '', 'body' => '']])
                ->add('greeting', TextType::class, ['label' => 'kanka_mail.greeting', 'required' => false, 'constraints' => [new Length(max: 500)]])
                ->add('subject', TextType::class, ['label' => 'kanka_mail.subject', 'required' => false, 'constraints' => [new Length(max: 255)]])
                ->add('body', TextareaType::class, ['label' => 'kanka_mail.body', 'required' => false, 'attr' => ['rows' => 9], 'constraints' => [new Length(max: 20000)]]);
            $builder->add($section);
            $sections[] = ['language' => $language, 'count' => $count, 'missing' => $template === null];
        }
        $form = $builder->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $templates = [];
            foreach ($sections as $section) {
                $key = 'template_'.$section['language'];
                $template = array_map(static fn ($value) => trim($value ?? ''), $data[$key]);
                // An untouched language without defaults can remain unconfigured.
                if ($section['missing'] && implode('', $template) === '') continue;
                try {
                    foreach ($template as $value) $this->text->validate($value);
                    if ($template['subject'] === '' || $template['body'] === '' || preg_match('/[\r\n]/', $template['subject'])) {
                        throw new \InvalidArgumentException('kanka_mail.error.fields');
                    }
                    $templates[$section['language']] = $template;
                } catch (\InvalidArgumentException $e) {
                    $form->get($key)->addError(new FormError($this->translator->trans($e->getMessage())));
                }
            }
            if ($form->isValid()) {
                $this->settings->saveAll(trim($data['sender_name'] ?? ''), $data['enabled'], $templates);
                $this->addFlash('kanka_mail_saved', $this->translator->trans('kanka_mail.saved'));
                return $this->redirectToRoute('kanka_invoice_mail_settings');
            }
        }
        return $this->render('@KankaInvoiceMail/settings.html.twig', ['form' => $form->createView(), 'sections' => $sections, 'placeholders' => TemplateText::KEYS]);
    }
}
