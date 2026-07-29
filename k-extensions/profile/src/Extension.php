<?php

declare(strict_types=1);

namespace Kopling\Profile;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Profile\Ux\ProfileLink;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasIcons
{
    public static function name(): string
    {
        return 'Profile';
    }

    public static function description(): string
    {
        return 'A detail page per person, making people linkable the same way discussions makes a moment linkable.';
    }

    public function models(): array
    {
        return [
            new Model(Person::class)
                ->linksTo('kopling-core::community/profile.show'),
        ];
    }

    /**
     * The profile page (`/p/{person}`) renders inside Community's chrome, so its routes are
     * attached to the Community portal, same as discussions' `/m/{moment}`.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }

    public function icons(): array
    {
        return [
            new Icon(id: 'person-profile', label: __('kopling-profile::messages.person-profile'), default: 'fas-id-badge')
        ];
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add(ProfileLink::class, [
                'label' => __('kopling-profile::messages.person-profile'),
                'icon' => 'kopling-profile::person-profile',
            ])
            ->in(UserMenu::SLOT)
            ->as('person-profile');
    }
}
