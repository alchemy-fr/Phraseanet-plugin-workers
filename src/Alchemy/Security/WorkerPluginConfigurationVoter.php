<?php

namespace Alchemy\WorkerPlugin\Security;

use Alchemy\Phrasea\Authorization\BaseVoter;
use Alchemy\Phrasea\Model\Repositories\UserRepository;
use Alchemy\Phrasea\Model\Entities\User;

class WorkerPluginConfigurationVoter extends BaseVoter
{
    const VIEW = 'view';

    public function __construct(UserRepository $userRepository) {
        parent::__construct(
            $userRepository,
            [self::VIEW],
            [
                'Alchemy\WorkerPlugin\Configuration\ConfigurationTab',
            ]
        );
    }

    protected function isGranted($attribute, $tab, User $user = null)
    {
        switch ($attribute)
        {
            case self::VIEW:
                return true;
        }

        return false;
    }
}
