<?php

declare(strict_types=1);

namespace App\Admin\Presenters;

use App\Model\BlockGroupRepository;
use App\Model\BlockRepository;

final class DashboardPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly BlockRepository $blockRepo,
        private readonly BlockGroupRepository $groupRepo,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $uid = $this->getUserUid();
        $this->template->blockCount = $this->blockRepo->findByUser($uid)->count('*');
        $this->template->groupCount = $this->groupRepo->findByUser($uid)->count('*');
        $this->template->userUrl = $this->link('//:User:Go:default', ['uid' => $uid]);
    }
}
