<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

final class LoginPresenter extends Presenter
{
    public function actionSignIn(?string $backlink = null): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $this->redirect('Dashboard:default');
        }
        $this->template->backlink = $backlink;
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form;

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.')
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('autocomplete', 'username');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo.')
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('autocomplete', 'current-password');

        $form->addHidden('backlink');

        $form->addSubmit('send', 'Přihlásit se')
            ->setHtmlAttribute('class', 'btn btn-primary w-100');

        $form->onSuccess[] = $this->signInFormSucceeded(...);

        return $form;
    }

    private function signInFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->getUser()->login($values->username, $values->password);

            if ($values->backlink) {
                $this->restoreRequest($values->backlink);
            }
            $this->redirect('Dashboard:default');
        } catch (AuthenticationException $e) {
            $form->addError('Nesprávné přihlašovací údaje.');
        }
    }

    public function actionSignOut(): void
    {
        $this->getUser()->logout(true);
        $this->redirect('signIn');
    }
}
