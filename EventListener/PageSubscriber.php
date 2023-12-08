<?php

namespace MauticPlugin\MauticFocusBundle\EventListener;

use Mautic\CoreBundle\Helper\BuilderTokenHelperFactory;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\PageBundle\Event\PageBuilderEvent;
use Mautic\PageBundle\Event\PageDisplayEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\MauticFocusBundle\Model\FocusModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class PageSubscriber implements EventSubscriberInterface
{
    private $regex = '{focus=(.*?)}';

<<<<<<< HEAD
    public function __construct(private CorePermissions $security, private FocusModel $model, private RouterInterface $router, private BuilderTokenHelperFactory $builderTokenHelperFactory)
    {
=======
    private \MauticPlugin\MauticFocusBundle\Model\FocusModel $model;

    private \Symfony\Component\Routing\RouterInterface $router;

    private \Mautic\CoreBundle\Security\Permissions\CorePermissions $security;

    private \Mautic\CoreBundle\Helper\BuilderTokenHelperFactory $builderTokenHelperFactory;

    public function __construct(
        CorePermissions $security,
        FocusModel $model,
        RouterInterface $router,
        BuilderTokenHelperFactory $builderTokenHelperFactory
    ) {
        $this->security                  = $security;
        $this->router                    = $router;
        $this->model                     = $model;
        $this->builderTokenHelperFactory = $builderTokenHelperFactory;
>>>>>>> 11b4805f88 ([type-declarations] Re-run rector rules on plugins, Report, Sms, User, Lead, Dynamic, Config bundles)
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PageEvents::PAGE_ON_DISPLAY => ['onPageDisplay', 0],
            PageEvents::PAGE_ON_BUILD   => ['onPageBuild', 0],
        ];
    }

    /**
     * Add forms to available page tokens.
     */
    public function onPageBuild(PageBuilderEvent $event): void
    {
        if ($event->tokensRequested($this->regex)) {
            $tokenHelper = $this->builderTokenHelperFactory->getBuilderTokenHelper('focus', $this->model->getPermissionBase(), 'MauticFocusBundle', 'mautic.focus');
            $event->addTokensFromHelper($tokenHelper, $this->regex, 'name');
        }
    }

    public function onPageDisplay(PageDisplayEvent $event): void
    {
        $content = $event->getContent();
        $regex   = '/'.$this->regex.'/i';

        preg_match_all($regex, $content, $matches);

        if (count($matches[0])) {
            foreach ($matches[1] as $id) {
                $focus = $this->model->getEntity($id);
                if (null !== $focus
                    && (
                        $focus->isPublished()
                        || $this->security->hasEntityAccess(
                            'focus:items:viewown',
                            'focus:items:viewother',
                            $focus->getCreatedBy()
                        )
                    )
                ) {
                    $script = '<script src="'.$this->router->generate('mautic_focus_generate', ['id' => $id], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)
                        .'" type="text/javascript" charset="utf-8" async="async"></script>';
                    $content = preg_replace('#{focus='.$id.'}#', $script, $content);
                } else {
                    $content = preg_replace('#{focus='.$id.'}#', '', $content);
                }
            }
        }
        $event->setContent($content);
    }
}
