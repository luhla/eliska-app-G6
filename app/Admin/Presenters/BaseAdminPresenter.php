<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use Nette\Application\UI\Presenter;

abstract class BaseAdminPresenter extends Presenter
{
    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Login:signIn', ['backlink' => $this->storeRequest()]);
        }
    }

    protected function getUserUid(): string
    {
        return (string) $this->getUser()->getId();
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $identity = $this->getUser()->getIdentity();
        $this->template->loggedUser = $identity?->getData()['display_name'] ?? $identity?->getData()['username'] ?? '';
        $this->template->userUid = $this->getUserUid();
        $this->template->activePresenter = $this->getName();
    }
}
