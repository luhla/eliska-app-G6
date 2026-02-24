<?php

declare(strict_types=1);

namespace App\User\Presenters;

use App\Model\BlockGroupRepository;
use App\Model\BlockRepository;
use App\Model\SettingsRepository;
use App\Model\UserRepository;
use Nette\Application\UI\Presenter;

final class GoPresenter extends Presenter
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly BlockRepository $blockRepo,
        private readonly BlockGroupRepository $groupRepo,
        private readonly SettingsRepository $settingsRepo,
    ) {
        parent::__construct();
    }

    public function actionDefault(string $uid): void
    {
        $user = $this->userRepo->findByUid($uid);

        if (!$user || !$user->is_active) {
            $this->error('Uživatel nenalezen.', 404);
        }

        $blocks = $this->blockRepo->findAllForUser($uid);
        $groups = $this->groupRepo->findByUser($uid)->fetchAll();
        $settings = $this->settingsRepo->getAll($uid);

        $groupsArray = [];
        foreach ($groups as $group) {
            $groupsArray[] = [
                'id'         => $group->id,
                'parent_id'  => $group->parent_id,
                'name'       => $group->name,
                'bg_color'   => $group->bg_color,
                'sort_order' => $group->sort_order,
            ];
        }

        $this->template->appData = json_encode([
            'blocks'   => $blocks,
            'groups'   => $groupsArray,
            'settings' => [
                'max_sentence_blocks' => (int) ($settings['max_sentence_blocks'] ?? 7),
            ],
            'uid' => $uid,
        ], JSON_UNESCAPED_UNICODE);

        $this->template->displayName = $user->display_name ?: $user->username;
    }
}
