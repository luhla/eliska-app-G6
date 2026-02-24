<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use App\Model\BlockGroupRepository;
use Nette\Application\UI\Form;

final class GroupPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly BlockGroupRepository $groupRepo,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->tree = $this->groupRepo->getTree($this->getUserUid());
    }

    public function actionEdit(?int $id = null): void
    {
        $uid = $this->getUserUid();

        if ($id !== null) {
            $group = $this->groupRepo->findById($id);
            if (!$group || $group->user_uid !== $uid) {
                $this->error('Skupina nenalezena.', 404);
            }
            $this['editForm']->setDefaults($group->toArray());
            $this->template->group = $group;
        } else {
            $this->template->group = null;
        }

        $this->template->parentOptions = $this->groupRepo->getFlatList($uid, $id);
    }

    public function handleSaveOrder(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        $items = $body['items'] ?? [];
        $uid = $this->getUserUid();

        foreach ($items as $item) {
            $group = $this->groupRepo->findById((int) $item['id']);
            if ($group && $group->user_uid === $uid) {
                $this->groupRepo->update($group->id, ['sort_order' => (int) $item['sort_order']]);
            }
        }

        $this->sendJson(['ok' => true]);
    }

    public function handleDeleteGroup(int $id): void
    {
        $uid = $this->getUserUid();
        $group = $this->groupRepo->findById($id);

        if (!$group || $group->user_uid !== $uid) {
            $this->sendJson(['ok' => false, 'error' => 'Skupina nenalezena.']);
        }

        $this->groupRepo->delete($id);
        $this->sendJson(['ok' => true]);
    }

    protected function createComponentEditForm(): Form
    {
        $form = new Form;

        $form->addHidden('id');

        $form->addText('name', 'Název skupiny:')
            ->setRequired('Zadejte název skupiny.')
            ->setMaxLength(128)
            ->setHtmlAttribute('class', 'form-control');

        $form->addSelect('parent_id', 'Nadřazená skupina:', ['' => '— Kořen (bez nadřazené) —'])
            ->setHtmlAttribute('class', 'form-select');

        $form->addText('bg_color', 'Barva pozadí:')
            ->setDefaultValue('#4A90D9')
            ->setHtmlAttribute('class', 'form-control form-control-color')
            ->setHtmlAttribute('type', 'color');

        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = $this->editFormSucceeded(...);

        return $form;
    }

    private function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $uid = $this->getUserUid();
        $id = $values->id ? (int) $values->id : null;

        if ($id !== null) {
            $existing = $this->groupRepo->findById($id);
            if (!$existing || $existing->user_uid !== $uid) {
                $form->addError('Skupina nenalezena.');
                return;
            }
        }

        $data = [
            'user_uid'  => $uid,
            'name'      => $values->name,
            'parent_id' => $values->parent_id !== '' ? (int) $values->parent_id : null,
            'bg_color'  => $values->bg_color,
        ];

        if ($id !== null) {
            $this->groupRepo->update($id, $data);
            $this->flashMessage('Skupina byla uložena.', 'success');
        } else {
            $this->groupRepo->create($data);
            $this->flashMessage('Skupina byla přidána.', 'success');
        }

        $this->redirect('Block:default');
    }
}
