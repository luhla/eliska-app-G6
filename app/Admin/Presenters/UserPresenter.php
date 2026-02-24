<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use App\Model\SettingsRepository;
use App\Model\UserRepository;
use Nette\Application\UI\Form;

final class UserPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly SettingsRepository $settingsRepo,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->users = $this->userRepo->findAll();
    }

    public function actionEdit(?string $uid = null): void
    {
        if ($uid !== null) {
            $user = $this->userRepo->findByUid($uid);
            if (!$user) {
                $this->error('Uživatel nenalezen.', 404);
            }
            $data = $user->toArray();
            unset($data['password_hash']);
            $this['editForm']->setDefaults($data);
            $this->template->editUser = $user;
        } else {
            $this->template->editUser = null;
        }
    }

    public function handleDeleteUser(string $uid): void
    {
        if ($uid === $this->getUserUid()) {
            $this->sendJson(['ok' => false, 'error' => 'Nelze smazat vlastní účet.']);
        }

        $this->userRepo->delete($uid);
        $this->sendJson(['ok' => true]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form;

        $form->addHidden('uid');

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno.')
            ->setMaxLength(64)
            ->setHtmlAttribute('class', 'form-control');

        $form->addText('display_name', 'Zobrazované jméno:')
            ->setMaxLength(128)
            ->setHtmlAttribute('class', 'form-control');

        $form->addPassword('password', 'Heslo:')
            ->setHtmlAttribute('class', 'form-control')
            ->setOption('description', 'Při editaci nechte prázdné pro zachování stávajícího hesla.');

        $form->addCheckbox('is_active', 'Aktivní účet')
            ->setDefaultValue(true);

        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = $this->editFormSucceeded(...);

        return $form;
    }

    private function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $uid = $values->uid ?: null;

        $data = [
            'username'     => $values->username,
            'display_name' => $values->display_name,
            'is_active'    => $values->is_active ? 1 : 0,
        ];

        if ($uid !== null) {
            // Editing existing user
            $existing = $this->userRepo->findByUid($uid);
            if (!$existing) {
                $form->addError('Uživatel nenalezen.');
                return;
            }
            // Verify username is unique (except own)
            $byUsername = $this->userRepo->findByUsername($values->username);
            if ($byUsername && $byUsername->uid !== $uid) {
                $form['username']->addError('Toto uživatelské jméno je již obsazeno.');
                return;
            }
            if ($values->password !== '') {
                $data['password'] = $values->password;
            }
            $this->userRepo->update($uid, $data);
            $this->flashMessage('Uživatel byl uložen.', 'success');
        } else {
            // New user
            if ($values->password === '') {
                $form['password']->addError('Pro nového uživatele zadejte heslo.');
                return;
            }
            $byUsername = $this->userRepo->findByUsername($values->username);
            if ($byUsername) {
                $form['username']->addError('Toto uživatelské jméno je již obsazeno.');
                return;
            }
            $data['password'] = $values->password;
            $newUser = $this->userRepo->create($data);
            $this->settingsRepo->initDefaults($newUser->uid);
            $this->flashMessage('Uživatel byl vytvořen.', 'success');
        }

        $this->redirect('default');
    }
}
