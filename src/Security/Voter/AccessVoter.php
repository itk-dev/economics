<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\ProjectBilling;
use App\Entity\User;
use App\Service\ViewService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

// https://symfony.com/doc/current/security/voters.html
/** @extends Voter<string,mixed> */
class AccessVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';

    public function __construct(private readonly ViewService $viewService, private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        $currentView = $this->viewService->getCurrentView();

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        // ROLE_ADMIN can access everything.
        if (!$isAdmin) {
            if (null === $currentView) {
                return false;
            }

            if (!$user->getViews()->contains($currentView)) {
                return false;
            }

            if (null !== $subject) {
                $dataProvider = null;
                $dataProviders = $currentView->getDataProviders();

                if ($subject instanceof Invoice) {
                    $dataProvider = $subject->getProject()?->getDataProvider();
                }

                if ($subject instanceof InvoiceEntry) {
                    $dataProvider = $subject->getInvoice()?->getProject()?->getDataProvider();
                }

                if ($subject instanceof ProjectBilling) {
                    $dataProvider = $subject->getProject()?->getDataProvider();
                }

                if (null === $dataProvider || !$dataProviders->contains($dataProvider)) {
                    return false;
                }
            }
        }

        return true;
    }
}
