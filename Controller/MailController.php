<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Controller;

use App\Entity\Invoice;
use KimaiPlugin\KankaInvoiceMailBundle\Service\Delivery;
use KimaiPlugin\KankaInvoiceMailBundle\Service\MessageFactory;
use KimaiPlugin\KankaInvoiceMailBundle\Service\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/{_locale}/invoice-mail', requirements: ['_locale' => '%app_locales%'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('create_invoice')]
final class MailController extends AbstractController
{
    public function __construct(private MessageFactory $messages, private Delivery $delivery, private Settings $settings, private TranslatorInterface $translator)
    {
    }

    private function access(Invoice $invoice): void
    {
        $this->denyAccessUnlessGranted('view_invoice', $invoice);
        $this->denyAccessUnlessGranted('access', $invoice->getCustomer());
    }

    #[Route('/{id}/prepare', name: 'kanka_invoice_mail_prepare', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function prepare(Invoice $invoice, Request $request): Response
    {
        $this->access($invoice);
        try {
            $data = $this->messages->prepare($invoice);
            $form = $this->createFormBuilder($data)
                ->add('to', EmailType::class, ['label' => 'kanka_mail.to'])
                ->add('subject', TextType::class, ['label' => 'kanka_mail.subject', 'attr' => ['maxlength' => 255]])
                ->add('greeting', TextType::class, ['label' => 'kanka_mail.greeting', 'required' => false, 'attr' => ['maxlength' => 500]])
                ->add('body', TextareaType::class, ['label' => 'kanka_mail.body', 'attr' => ['rows' => 12, 'maxlength' => 20000]])->getForm();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $data['greeting'] ??= '';
                $this->messages->validate($data);
                $this->messages->create($invoice, $data);
                $nonce = bin2hex(random_bytes(24));
                $request->getSession()->set('kanka_mail_'.$invoice->getId(), ['nonce' => $nonce, 'created' => time(), 'data' => $data]);
                return $this->render('@KankaInvoiceMail/preview.html.twig', ['invoice' => $invoice, 'data' => $data, 'nonce' => $nonce, 'sender' => $this->messages->sender()->toString(), 'receipt' => $this->delivery->receipt($invoice->getId()), 'enabled' => $this->settings->sendingEnabled()]);
            }
            return $this->render('@KankaInvoiceMail/prepare.html.twig', ['invoice' => $invoice, 'form' => $form->createView(), 'language' => $data['language'], 'sender' => $this->messages->sender()->toString()]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Route('/{id}/deliver', name: 'kanka_invoice_mail_deliver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deliver(Invoice $invoice, Request $request): Response
    {
        $this->access($invoice);
        if (!$this->isCsrfTokenValid('kanka_mail_'.$invoice->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $prepared = $request->getSession()->get('kanka_mail_'.$invoice->getId());
        if (!is_array($prepared) || !hash_equals($prepared['nonce'], $request->request->getString('nonce')) || time() - $prepared['created'] > 1800) {
            return $this->error('kanka_mail.error.expired');
        }
        try {
            $message = $this->messages->create($invoice, $prepared['data']);
            $action = $request->request->getString('action');
            if ($action === 'download') {
                $message->getHeaders()->addTextHeader('X-Unsent', '1');
                return new Response($message->toString(), 200, ['Content-Type' => 'message/rfc822', 'Content-Disposition' => 'attachment; filename="invoice-email-'.$invoice->getId().'.eml"', 'Cache-Control' => 'private, no-store']);
            }
            if ($action !== 'send') throw new \InvalidArgumentException('kanka_mail.error.fields');
            $this->delivery->send($invoice->getId(), $this->getUser()->getId(), $prepared['nonce'], $message, $request->request->getBoolean('resend'));
            $request->getSession()->remove('kanka_mail_'.$invoice->getId());
            return $this->render('@KankaInvoiceMail/result.html.twig', ['message' => 'kanka_mail.sent', 'error' => false]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    private function error(string $key): Response
    {
        return $this->render('@KankaInvoiceMail/result.html.twig', ['message' => str_starts_with($key, 'kanka_mail.') ? $key : 'kanka_mail.error.fields', 'error' => true], new Response('', 422));
    }
}
