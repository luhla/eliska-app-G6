<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use App\Model\ArasaacService;
use App\Model\BlockGroupRepository;
use App\Model\BlockRepository;
use App\Model\FileUploadService;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;

final class BlockPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly BlockRepository $blockRepo,
        private readonly BlockGroupRepository $groupRepo,
        private readonly FileUploadService $fileService,
        private readonly ArasaacService $arasaacService,
    ) {
        parent::__construct();
    }

    // ---------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------

    public function renderDefault(): void
    {
        $uid = $this->getUserUid();
        $this->template->treeJson = json_encode($this->groupRepo->getTree($uid));
        $this->template->initialItems = json_encode($this->loadGroupItems($uid, null));
        $this->template->flatGroupsJson = json_encode($this->groupRepo->getFlatList($uid));
    }

    public function actionEdit(?int $id = null): void
    {
        $uid = $this->getUserUid();
        $groups = $this->groupRepo->getFlatList($uid);

        $this['editForm']['group_id']->setItems(
            ['' => '— Kořen (bez skupiny) —'] + $groups
        );

        if ($id !== null) {
            $block = $this->blockRepo->findById($id);
            if (!$block || $block->user_uid !== $uid) {
                $this->error('Blok nenalezen.', 404);
            }
            $this['editForm']->setDefaults($block->toArray());
            $this->template->block = $block;
        } else {
            $this->template->block = null;
        }

        $this->template->groups = $groups;
        $this->template->blockTypes = $this->getBlockTypes();
    }

    // ---------------------------------------------------------------
    // Signals (AJAX) – Library management
    // ---------------------------------------------------------------

    /** AJAX: load items for a group (null = root) */
    public function handleLoadGroup(): void
    {
        $uid = $this->getUserUid();
        $groupIdParam = $this->getHttpRequest()->getQuery('groupId');
        $groupId = ($groupIdParam !== null && $groupIdParam !== '') ? (int) $groupIdParam : null;

        if ($groupId !== null) {
            $group = $this->groupRepo->findById($groupId);
            if (!$group || $group->user_uid !== $uid) {
                $this->sendJson(['error' => 'Skupina nenalezena.']);
            }
        }

        $this->sendJson([
            'items'      => $this->loadGroupItems($uid, $groupId),
            'breadcrumb' => $this->groupRepo->getAncestors($uid, $groupId),
            'groupId'    => $groupId,
        ]);
    }

    /** AJAX: save mixed sort order (blocks + groups) */
    public function handleSaveOrder(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        $items = $body['items'] ?? [];
        $uid = $this->getUserUid();

        foreach ($items as $item) {
            $type = $item['type'] ?? 'block';
            $id = (int) ($item['id'] ?? 0);
            $order = (int) ($item['sort_order'] ?? 0);

            if ($type === 'group') {
                $g = $this->groupRepo->findById($id);
                if ($g && $g->user_uid === $uid) {
                    $this->groupRepo->update($g->id, ['sort_order' => $order]);
                }
            } else {
                $b = $this->blockRepo->findById($id);
                if ($b && $b->user_uid === $uid) {
                    $this->blockRepo->update($b->id, ['sort_order' => $order]);
                }
            }
        }

        $this->sendJson(['ok' => true]);
    }

    /** AJAX: move block or group to another parent */
    public function handleMoveItem(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        $type = $body['type'] ?? '';
        $id = (int) ($body['id'] ?? 0);
        $targetGroupId = isset($body['targetGroupId']) && $body['targetGroupId'] !== null
            ? (int) $body['targetGroupId']
            : null;
        $uid = $this->getUserUid();

        if ($type === 'group') {
            $group = $this->groupRepo->findById($id);
            if (!$group || $group->user_uid !== $uid) {
                $this->sendJson(['ok' => false, 'error' => 'Skupina nenalezena.']);
            }
            // Prevent moving group into its own subtree
            if ($targetGroupId !== null && ($targetGroupId === $id || $this->groupRepo->isDescendant($id, $targetGroupId))) {
                $this->sendJson(['ok' => false, 'error' => 'Nelze přesunout skupinu do její vlastní podskupiny.']);
            }
            // Assign max sort_order in target
            $maxOrder = $this->getMaxSortOrder($uid, $targetGroupId);
            $this->groupRepo->update($id, ['parent_id' => $targetGroupId, 'sort_order' => $maxOrder + 1]);
        } else {
            $block = $this->blockRepo->findById($id);
            if (!$block || $block->user_uid !== $uid) {
                $this->sendJson(['ok' => false, 'error' => 'Blok nenalezen.']);
            }
            if ($targetGroupId !== null) {
                $group = $this->groupRepo->findById($targetGroupId);
                if (!$group || $group->user_uid !== $uid) {
                    $this->sendJson(['ok' => false, 'error' => 'Cílová skupina nenalezena.']);
                }
            }
            $maxOrder = $this->getMaxSortOrder($uid, $targetGroupId);
            $this->blockRepo->update($id, ['group_id' => $targetGroupId, 'sort_order' => $maxOrder + 1]);
        }

        $this->sendJson(['ok' => true]);
    }

    /** AJAX: copy a block to another group */
    public function handleCopyBlock(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        $blockId = (int) ($body['blockId'] ?? 0);
        $targetGroupId = isset($body['targetGroupId']) && $body['targetGroupId'] !== null
            ? (int) $body['targetGroupId']
            : null;
        $uid = $this->getUserUid();

        $block = $this->blockRepo->findById($blockId);
        if (!$block || $block->user_uid !== $uid) {
            $this->sendJson(['ok' => false, 'error' => 'Blok nenalezen.']);
        }

        if ($targetGroupId !== null) {
            $group = $this->groupRepo->findById($targetGroupId);
            if (!$group || $group->user_uid !== $uid) {
                $this->sendJson(['ok' => false, 'error' => 'Cílová skupina nenalezena.']);
            }
        }

        // Create copy (shared file references)
        $newBlock = $this->blockRepo->create([
            'user_uid'   => $uid,
            'group_id'   => $targetGroupId,
            'text'       => $block->text,
            'image_path' => $block->image_path,
            'block_type' => $block->block_type,
            'audio_path' => $block->audio_path,
            'arasaac_id' => $block->arasaac_id,
        ]);

        $this->sendJson(['ok' => true, 'newId' => $newBlock->id]);
    }

    /** AJAX: delete group */
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

    /** AJAX: delete block */
    public function handleDeleteBlock(int $id): void
    {
        $uid = $this->getUserUid();
        $block = $this->blockRepo->findById($id);

        if (!$block || $block->user_uid !== $uid) {
            $this->sendJson(['ok' => false, 'error' => 'Blok nenalezen.']);
        }

        if ($block->image_path) {
            $this->fileService->deleteFile($block->image_path);
        }
        if ($block->audio_path) {
            $this->fileService->deleteFile($block->audio_path);
        }

        $this->blockRepo->delete($id);
        $this->sendJson(['ok' => true]);
    }

    /** AJAX: proxy ARASAAC search */
    public function handleArasaacSearch(): void
    {
        $q = trim($this->getHttpRequest()->getQuery('q') ?? '');
        if ($q === '') {
            $this->sendJson([]);
        }
        $results = $this->arasaacService->search($q);
        $this->sendJson($results);
    }

    /** AJAX: download ARASAAC pictogram to local server */
    public function handleDownloadArasaac(): void
    {
        $id = (int) ($this->getHttpRequest()->getQuery('arasaac_id') ?? 0);
        if ($id <= 0) {
            $this->sendJson(['error' => 'Neplatné ID.']);
        }

        try {
            $path = $this->fileService->downloadArasaacImage($id, $this->getUserUid());
            $response = [
                'imagePath'  => $path,
                'previewUrl' => $this->fileService->getPublicPath($path),
            ];
            $this->sendJson($response);
        } catch (\ErrorException $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    // ---------------------------------------------------------------
    // Edit Form
    // ---------------------------------------------------------------

    protected function createComponentEditForm(): Form
    {
        $form = new Form;

        $form->addHidden('id');

        $form->addText('text', 'Text / popis:')
            ->setRequired('Zadejte text bloku.')
            ->setMaxLength(128)
            ->setHtmlAttribute('class', 'form-control');

        $form->addSelect('block_type', 'Typ bloku:', $this->getBlockTypes())
            ->setRequired()
            ->setHtmlAttribute('class', 'form-select');

        $form->addSelect('group_id', 'Skupina:', ['' => '— Kořen (bez skupiny) —'])
            ->setHtmlAttribute('class', 'form-select');

        $form->addUpload('image_file', 'Obrázek (soubor):')
            ->setRequired(false)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('accept', 'image/*');

        $form->addHidden('image_base64');
        $form->addHidden('arasaac_id');

        $form->addUpload('audio_file', 'Zvuk (soubor):')
            ->setRequired(false)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('accept', 'audio/*');

        $form->addHidden('audio_base64');

        $form->addSubmit('save', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = $this->editFormSucceeded(...);

        return $form;
    }

    private function editFormSucceeded(Form $form, \stdClass $values): void
    {
        $uid = $this->getUserUid();
        $id = $values->id ? (int) $values->id : null;

        $existing = null;
        if ($id !== null) {
            $existing = $this->blockRepo->findById($id);
            if (!$existing || $existing->user_uid !== $uid) {
                $form->addError('Blok nenalezen.');
                return;
            }
        }

        $data = [
            'user_uid'   => $uid,
            'text'       => $values->text,
            'block_type' => $values->block_type,
            'group_id'   => $values->group_id !== '' ? (int) $values->group_id : null,
        ];

        // Handle image
        $imagePath = $existing->image_path ?? null;

        /** @var FileUpload $imageFile */
        $imageFile = $values->image_file;
        if ($imageFile->isOk() && $imageFile->getSize() > 0) {
            try {
                if ($imagePath) {
                    $this->fileService->deleteFile($imagePath);
                }
                $imagePath = $this->fileService->saveUploadedImage($imageFile, $uid);
            } catch (\InvalidArgumentException $e) {
                $form->addError('Obrázek: ' . $e->getMessage());
                return;
            }
        } elseif ($values->image_base64 !== '') {
            $val = $values->image_base64;
            if (str_starts_with($val, 'data:')) {
                try {
                    if ($imagePath) {
                        $this->fileService->deleteFile($imagePath);
                    }
                    $imagePath = $this->fileService->saveBase64Image($val, $uid);
                } catch (\Exception $e) {
                    $form->addError('Webcam obrázek: ' . $e->getMessage());
                    return;
                }
            } elseif (str_starts_with($val, 'uploads/')) {
                if ($imagePath && $imagePath !== $val) {
                    $this->fileService->deleteFile($imagePath);
                }
                $imagePath = $val;
            }
        }

        if ($imagePath !== null) {
            $data['image_path'] = $imagePath;
            $data['arasaac_id'] = $values->arasaac_id !== '' ? (int) $values->arasaac_id : null;
        }

        // Handle audio
        $audioPath = $existing->audio_path ?? null;

        /** @var FileUpload $audioFile */
        $audioFile = $values->audio_file;
        if ($audioFile->isOk() && $audioFile->getSize() > 0) {
            try {
                if ($audioPath) {
                    $this->fileService->deleteFile($audioPath);
                }
                $audioPath = $this->fileService->saveUploadedAudio($audioFile, $uid);
            } catch (\InvalidArgumentException $e) {
                $form->addError('Audio: ' . $e->getMessage());
                return;
            }
        } elseif ($values->audio_base64 !== '') {
            try {
                if ($audioPath) {
                    $this->fileService->deleteFile($audioPath);
                }
                $audioPath = $this->fileService->saveBase64Audio($values->audio_base64, $uid);
            } catch (\Exception $e) {
                $form->addError('Nahrávka: ' . $e->getMessage());
                return;
            }
        }

        $data['audio_path'] = $audioPath;

        if ($id !== null) {
            $this->blockRepo->update($id, $data);
            $this->flashMessage('Blok byl uložen.', 'success');
        } else {
            $this->blockRepo->create($data);
            $this->flashMessage('Blok byl přidán.', 'success');
        }

        $this->redirect('default');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Load merged groups + blocks for a given parent group, sorted by sort_order.
     * @return array<int, array<string, mixed>>
     */
    private function loadGroupItems(string $uid, ?int $groupId): array
    {
        $items = [];

        // Child groups
        foreach ($this->groupRepo->getChildren($uid, $groupId) as $g) {
            $hasChildren = count($this->groupRepo->getChildren($uid, $g->id)) > 0
                || $this->blockRepo->findByUser($uid, $g->id)->count() > 0;
            $items[] = [
                'type'        => 'group',
                'id'          => $g->id,
                'name'        => $g->name,
                'bg_color'    => $g->bg_color,
                'sort_order'  => $g->sort_order,
                'has_children' => $hasChildren,
            ];
        }

        // Blocks in this group
        foreach ($this->blockRepo->findByUser($uid, $groupId) as $b) {
            $items[] = [
                'type'       => 'block',
                'id'         => $b->id,
                'text'       => $b->text,
                'image_path' => $b->image_path,
                'block_type' => $b->block_type,
                'audio_path' => $b->audio_path,
                'sort_order' => $b->sort_order,
            ];
        }

        // Sort by sort_order, then by type (groups first within same order)
        usort($items, function (array $a, array $b): int {
            $cmp = $a['sort_order'] <=> $b['sort_order'];
            if ($cmp !== 0) return $cmp;
            // groups before blocks within same sort_order
            return ($a['type'] === 'group' ? 0 : 1) <=> ($b['type'] === 'group' ? 0 : 1);
        });

        return $items;
    }

    /** Get max sort_order among siblings in a given parent group */
    private function getMaxSortOrder(string $uid, ?int $groupId): int
    {
        $maxGroup = 0;
        foreach ($this->groupRepo->getChildren($uid, $groupId) as $g) {
            if ($g->sort_order > $maxGroup) $maxGroup = $g->sort_order;
        }
        $maxBlock = (int) ($this->blockRepo->findByUser($uid, $groupId)->max('sort_order') ?? -1);
        return max($maxGroup, $maxBlock);
    }

    private function getBlockTypes(): array
    {
        return [
            'noun'      => 'Podstatné jméno',
            'verb'      => 'Sloveso',
            'adjective' => 'Přídavné jméno',
            'adverb'    => 'Příslovce',
            'other'     => 'Jiné',
        ];
    }
}
