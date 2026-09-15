<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\Controller;
use App\Entity\Invoice;
use KimaiPlugin\KankaInvoiceMailBundle\Service\ReceiptStore;
use KimaiPlugin\KankaInvoiceMailBundle\Service\InvoiceStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/{_locale}/invoice-mail', requirements: ['_locale' => '%app_locales%'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('system_configuration')]
final class RecoveryController extends AbstractController
{
    public function __construct(private ReceiptStore $receipts, private InvoiceStatus $status, private TranslatorInterface $translator) {}
    #[Route('/{id}/recovery', name: 'kanka_invoice_mail_recovery', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function recover(Invoice $invoice, Request $request): Response
    {
        $this->denyAccessUnlessGranted('view_invoice', $invoice);
        $this->denyAccessUnlessGranted('access', $invoice->getCustomer());
        try { $receipt = $this->receipts->read($invoice->getId()); }
        catch (\InvalidArgumentException $e) { return $this->render('@KankaInvoiceMail/result.html.twig', ['message' => $e->getMessage(), 'error' => true, 'invoice' => $invoice, 'receipt' => null], new Response('', 422)); }
        if ($receipt === null || $receipt['status'] !== 'uncertain') {
            return $this->render('@KankaInvoiceMail/result.html.twig', ['message' => 'kanka_mail.error.stale_receipt', 'error' => true, 'invoice' => $invoice, 'receipt' => $receipt], new Response('', 409));
        }
        $form = $this->createFormBuilder(['fingerprint' => $this->receipts->fingerprint($receipt)])
            ->add('fingerprint', HiddenType::class, ['constraints' => [new NotBlank(), new Length(exactly: 64)]])
            ->add('decision', ChoiceType::class, ['label' => 'kanka_mail.recovery_decision', 'placeholder' => 'kanka_mail.choose', 'constraints' => [new NotBlank()], 'choices' => ['kanka_mail.confirm_accepted' => 'accepted', 'kanka_mail.confirm_retry' => 'retryable']])
            ->add('reason', TextareaType::class, ['label' => 'kanka_mail.recovery_reason', 'constraints' => [new NotBlank(), new Length(max: 500)]])->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $this->receipts->resolve($invoice->getId(), $data['fingerprint'], $this->getUser()->getId(), $data['decision'], $data['reason']);
                $request->getSession()->remove('kanka_mail_'.$invoice->getId());
                $message = $data['decision'] === 'accepted' ? $this->status->afterAcceptance($invoice) : 'kanka_mail.recovery_done';
                return $this->render('@KankaInvoiceMail/result.html.twig', ['message' => $message, 'error' => false, 'invoice' => $invoice, 'receipt' => $this->receipts->read($invoice->getId())]);
            } catch (\InvalidArgumentException $e) { $form->addError(new FormError($this->translator->trans($e->getMessage()))); }
        }
        return $this->render('@KankaInvoiceMail/recovery.html.twig', ['form' => $form->createView(), 'invoice' => $invoice, 'receipt' => $receipt]);
    }
    #[Route('/maintenance', name: 'kanka_invoice_mail_maintenance', methods: ['GET', 'POST'])]
    public function maintenance(Request $request): Response
    {
        $form = $this->createFormBuilder(['days' => 365])->add('days', IntegerType::class, ['label' => 'kanka_mail.retention_days', 'constraints' => [new NotBlank(), new Range(min: 30, max: 36500)]])->getForm();
        $form->handleRequest($request); $count = null;
        if ($form->isSubmitted() && $form->isValid()) {
            try { $count = $this->receipts->compact($form->getData()['days']); }
            catch (\InvalidArgumentException $e) { $form->addError(new FormError($this->translator->trans($e->getMessage()))); }
        }
        return $this->render('@KankaInvoiceMail/maintenance.html.twig', ['form' => $form->createView(), 'count' => $count]);
    }
}
