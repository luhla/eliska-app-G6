<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use App\Model\SettingsRepository;
use Nette\Application\UI\Form;

final class SettingsPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly SettingsRepository $settingsRepo,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $uid = $this->getUserUid();
        $settings = $this->settingsRepo->getAll($uid);
        $this['settingsForm']->setDefaults($settings);
    }

    protected function createComponentSettingsForm(): Form
    {
        $form = new Form;

        $form->addInteger('max_sentence_blocks', 'Maximální počet bloků ve větě:')
            ->setRequired()
            ->setDefaultValue(7)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('min', 1)
            ->setHtmlAttribute('max', 20);

        $form->addSubmit('save', 'Uložit nastavení')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = $this->settingsFormSucceeded(...);

        return $form;
    }

    private function settingsFormSucceeded(Form $form, \stdClass $values): void
    {
        $uid = $this->getUserUid();
        $this->settingsRepo->set($uid, 'max_sentence_blocks', (string) $values->max_sentence_blocks);
        $this->flashMessage('Nastavení bylo uloženo.', 'success');
        $this->redirect('default');
    }
}
